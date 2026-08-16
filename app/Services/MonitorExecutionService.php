<?php

/**
 * @file MonitorExecutionService.php
 * @brief Atomic UTC check-in lifecycle operations for monitors.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Pulse\Core\Database;
use Pulse\Core\Logger;
use Throwable;

/**
 * @brief Owns scheduling and every persisted runtime-state transition.
 */
final class MonitorExecutionService
{
	private Database $_database;
	private MonitorStateMachine $_stateMachine;
	private Logger $_logger;

	/**
	 * @brief Constructs the lifecycle service.
	 * @param Database $database Database service.
	 * @param MonitorStateMachine $stateMachine Transition validator.
	 * @param Logger $logger Application logger.
	 */
	public function __construct(Database $database, MonitorStateMachine $stateMachine, Logger $logger)
	{
		$this->_database = $database;
		$this->_stateMachine = $stateMachine;
		$this->_logger = $logger;
	}
	/** @brief Returns whether a user has any active monitor configured to record check-in location. */
	public function HasLocationEnabledActiveMonitorForUser(int $userId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT 1
			FROM monitors
			WHERE user_id = :user_id
				AND location_check_in_enabled = 1
				AND is_paused = 0
				AND is_archived = 0
				AND NOT EXISTS
				(
					SELECT 1
					FROM check_cycles active_cc
					WHERE active_cc.monitor_id = monitors.id
						AND active_cc.status = \'escalated\'
				)
			LIMIT 1
		');
		$statement->execute(['user_id' => $userId]);
		return $statement->fetchColumn() !== false;
	}

	/**
	 * @brief Ensures every active monitor has one current cycle and opens cycles whose due time has arrived.
	 * @param int $userId Owner user ID.
	 * @return int Number of cycles moved to awaiting check-in.
	 */
	public function SynchronizeDueCyclesForUser(int $userId): int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$now = $this->UtcNow();
			$monitors = $this->LockActiveMonitorsForUser($connection, $userId);
			$opened = 0;

			foreach ($monitors as $monitor)
			{
				if ($this->SynchronizeMonitor($connection, $monitor, $now))
				{
					$opened++;
				}
			}

			$connection->commit();
			return $opened;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Creates the first current cycle for a newly created monitor.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 */
	public function InitializeMonitorForUser(int $monitorId, int $userId): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$monitor = $this->LockMonitorForUser($connection, $monitorId, $userId);

			if (is_array($monitor) && empty($monitor['is_paused']) && empty($monitor['is_archived']))
			{
				$this->SynchronizeMonitor($connection, $monitor, $this->UtcNow());
			}

			$connection->commit();
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Reconciles an owned monitor after its schedule settings change.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 */
	public function SynchronizeMonitorForUser(int $monitorId, int $userId): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$monitor = $this->LockMonitorForUser($connection, $monitorId, $userId);

			if (is_array($monitor) && empty($monitor['is_paused']) && empty($monitor['is_archived']))
			{
				$this->SynchronizeMonitor($connection, $monitor, $this->UtcNow());
			}

			$connection->commit();
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Confirms all active monitors and starts each monitor's next interval from one UTC instant.
	 * @param int $userId Owner user ID.
	 * @param string $source Authentication/action source recorded in audit context.
	 * @param array{latitude: float, longitude: float, accuracy_meters: float, address_label: string|null}|null $location Optional validated browser location.
	 * @return array{updated: int}
	 */
	public function CheckInAllActiveForUser(int $userId, string $source = 'manual', ?array $location = null): array
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$now = $this->UtcNow();
			$nowValue = $this->FormatUtc($now);
			$monitors = $this->LockActiveMonitorsForUser($connection, $userId);
			$updated = 0;

			foreach ($monitors as $monitor)
			{
				$this->SynchronizeMonitor($connection, $monitor, $now);
				$cycle = $this->FindOpenCycleForUpdate($connection, (int)$monitor['id']);

				if (!is_array($cycle))
				{
					throw new \RuntimeException('Active monitor has no open check cycle.');
				}

				$previousStatus = (string)$cycle['status'];
				$this->_stateMachine->AssertTransition($previousStatus, MonitorStateMachine::CONFIRMED);
				$this->CancelQueuedReminders($connection, (int)$cycle['id'], $nowValue);


				$confirm = $connection->prepare('
					UPDATE check_cycles
					SET status = :status, confirmed_at = :confirmed_at, updated_at = :updated_at
					WHERE id = :id
				');
				$confirm->execute([
					'status' => MonitorStateMachine::CONFIRMED,
					'confirmed_at' => $nowValue,
					'updated_at' => $nowValue,
					'id' => (int)$cycle['id'],
				]);

				$nextDue = $this->AddDays($now, (int)$monitor['check_interval_days']);
				$nextDueValue = $this->FormatUtc($nextDue);
				$this->InsertScheduledCycle($connection, $monitor, $now, $nextDue);

				$updateMonitor = $connection->prepare('
					UPDATE monitors
					SET
						last_confirmed_at = :confirmed_at,
						next_check_due_at = :next_due_at,
						paused_at = NULL,
						updated_at = :updated_at
					WHERE id = :id
				');
				$updateMonitor->execute([
					'confirmed_at' => $nowValue,
					'next_due_at' => $nextDueValue,
					'updated_at' => $nowValue,
					'id' => (int)$monitor['id'],
				]);

				$auditId = $this->WriteAudit(
					$connection,
					$userId,
					'monitor.checked_in',
					(int)$monitor['id'],
					$nowValue,
					[
						'cycle_id' => (int)$cycle['id'],
						'previous_status' => $previousStatus,
						'next_due_at' => $nextDueValue,
						'source' => $source,
					]
				);

				if (!empty($monitor['location_check_in_enabled']) && is_array($location))
				{
					$this->InsertCheckInLocation(
						$connection,
						$auditId,
						(int)$cycle['id'],
						(int)$monitor['id'],
						$userId,
						$location,
						$nowValue
					);
				}
				$updated++;
			}

			$connection->commit();
			$this->_logger->Info('Global check-in completed', ['user_id' => $userId, 'monitor_count' => $updated, 'source' => $source]);

			return ['updated' => $updated];
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Pauses an active owned monitor and cancels its current cycle.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return bool True when the monitor changed state.
	 */
	public function PauseMonitorForUser(int $monitorId, int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$now = $this->UtcNow();
			$nowValue = $this->FormatUtc($now);
			$monitor = $this->LockMonitorForUser($connection, $monitorId, $userId);

			if (!is_array($monitor) || !empty($monitor['is_paused']) || !empty($monitor['is_archived']))
			{
				$connection->commit();
				return false;
			}

			$this->SynchronizeMonitor($connection, $monitor, $now);
			$cycle = $this->FindOpenCycleForUpdate($connection, $monitorId);

			if (is_array($cycle) && (string)$cycle['status'] === MonitorStateMachine::ESCALATED)
			{
				$connection->commit();
				return false;
			}

			if (is_array($cycle))
			{
				$this->_stateMachine->AssertTransition((string)$cycle['status'], MonitorStateMachine::CANCELLED);
				$this->CancelQueuedReminders($connection, (int)$cycle['id'], $nowValue);
				$cancel = $connection->prepare('
					UPDATE check_cycles
					SET status = :status, cancelled_at = :cancelled_at, updated_at = :updated_at
					WHERE id = :id
				');
				$cancel->execute([
					'status' => MonitorStateMachine::CANCELLED,
					'cancelled_at' => $nowValue,
					'updated_at' => $nowValue,
					'id' => (int)$cycle['id'],
				]);
			}

			$pause = $connection->prepare('
				UPDATE monitors
				SET is_paused = 1, paused_at = :paused_at, next_check_due_at = NULL, updated_at = :updated_at
				WHERE id = :id
			');
			$pause->execute(['paused_at' => $nowValue, 'updated_at' => $nowValue, 'id' => $monitorId]);
			$this->WriteAudit($connection, $userId, 'monitor.paused', $monitorId, $nowValue);
			$connection->commit();
			$this->_logger->Info('Monitor paused', ['user_id' => $userId, 'monitor_id' => $monitorId]);

			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Resumes a paused monitor as a fresh confirmation and schedules a new cycle.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return bool True when the monitor changed state.
	 */
	public function ResumeMonitorForUser(int $monitorId, int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$now = $this->UtcNow();
			$nowValue = $this->FormatUtc($now);
			$monitor = $this->LockMonitorForUser($connection, $monitorId, $userId);

			if (!is_array($monitor) || empty($monitor['is_paused']) || !empty($monitor['is_archived']))
			{
				$connection->commit();
				return false;
			}

			$lingeringCycle = $this->FindOpenCycleForUpdate($connection, $monitorId);

			if (is_array($lingeringCycle))
			{
				$this->_stateMachine->AssertTransition((string)$lingeringCycle['status'], MonitorStateMachine::CANCELLED);
				$this->CancelQueuedReminders($connection, (int)$lingeringCycle['id'], $nowValue);
				$cancel = $connection->prepare('
					UPDATE check_cycles
					SET status = :status, cancelled_at = :cancelled_at, updated_at = :updated_at
					WHERE id = :id
				');
				$cancel->execute([
					'status' => MonitorStateMachine::CANCELLED,
					'cancelled_at' => $nowValue,
					'updated_at' => $nowValue,
					'id' => (int)$lingeringCycle['id'],
				]);
			}

			$nextDue = $this->AddDays($now, (int)$monitor['check_interval_days']);
			$nextDueValue = $this->FormatUtc($nextDue);
			$this->InsertScheduledCycle($connection, $monitor, $now, $nextDue);

			$resume = $connection->prepare('
				UPDATE monitors
				SET
					is_paused = 0,
					paused_at = NULL,
					last_confirmed_at = :confirmed_at,
					next_check_due_at = :next_due_at,
					updated_at = :updated_at
				WHERE id = :id
			');
			$resume->execute([
				'confirmed_at' => $nowValue,
				'next_due_at' => $nextDueValue,
				'updated_at' => $nowValue,
				'id' => $monitorId,
			]);
			$this->WriteAudit(
				$connection,
				$userId,
				'monitor.resumed',
				$monitorId,
				$nowValue,
				['next_due_at' => $nextDueValue]
			);
			$connection->commit();
			$this->_logger->Info('Monitor resumed', ['user_id' => $userId, 'monitor_id' => $monitorId]);

			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}


	/**
	 * @brief Resets an escalated or archived monitor and starts a fresh monitoring cycle.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return bool True when the monitor was reset and reactivated.
	 */
	public function ResetAndReactivateMonitorForUser(int $monitorId, int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$now = $this->UtcNow();
			$nowValue = $this->FormatUtc($now);
			$monitor = $this->LockMonitorForUser($connection, $monitorId, $userId);

			if (!is_array($monitor))
			{
				$connection->commit();
				return false;
			}

			$cycle = $this->FindOpenCycleForUpdate($connection, $monitorId);
			$isEscalated = is_array($cycle) && (string)$cycle['status'] === MonitorStateMachine::ESCALATED;

			if (!$isEscalated)
			{
				$connection->commit();
				return false;
			}

			$this->_stateMachine->AssertTransition(MonitorStateMachine::ESCALATED, MonitorStateMachine::CANCELLED);
			$cancel = $connection->prepare('
				UPDATE check_cycles
				SET status = :status, cancelled_at = :cancelled_at, updated_at = :updated_at
				WHERE id = :id
			');
			$cancel->execute([
				'status' => MonitorStateMachine::CANCELLED,
				'cancelled_at' => $nowValue,
				'updated_at' => $nowValue,
				'id' => (int)$cycle['id'],
			]);

			$nextDue = $this->AddDays($now, (int)$monitor['check_interval_days']);
			$nextDueValue = $this->FormatUtc($nextDue);
			$this->InsertScheduledCycle($connection, $monitor, $now, $nextDue);

			$update = $connection->prepare('
				UPDATE monitors
				SET
					is_paused = 0,
					paused_at = NULL,
					is_archived = 0,
					archived_at = NULL,
					last_confirmed_at = :confirmed_at,
					next_check_due_at = :next_due_at,
					updated_at = :updated_at
				WHERE id = :id
			');
			$update->execute([
				'confirmed_at' => $nowValue,
				'next_due_at' => $nextDueValue,
				'updated_at' => $nowValue,
				'id' => $monitorId,
			]);

			$this->WriteAudit(
				$connection,
				$userId,
				'monitor.reset_reactivated',
				$monitorId,
				$nowValue,
				[
					'previous_cycle_id' => (int)$cycle['id'],
					'next_due_at' => $nextDueValue,
				]
			);
			$connection->commit();
			$this->_logger->Info('Escalated monitor reset and reactivated', ['user_id' => $userId, 'monitor_id' => $monitorId]);

			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Archives an escalated monitor without revoking its released recipient portals.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return bool True when the monitor was archived.
	 */
	public function ArchiveEscalatedMonitorForUser(int $monitorId, int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$nowValue = $this->FormatUtc($this->UtcNow());
			$monitor = $this->LockMonitorForUser($connection, $monitorId, $userId);

			if (!is_array($monitor) || !empty($monitor['is_archived']))
			{
				$connection->commit();
				return false;
			}

			$cycle = $this->FindOpenCycleForUpdate($connection, $monitorId);

			if (!is_array($cycle) || (string)$cycle['status'] !== MonitorStateMachine::ESCALATED)
			{
				$connection->commit();
				return false;
			}

			$archive = $connection->prepare('
				UPDATE monitors
				SET is_archived = 1, archived_at = :archived_at, next_check_due_at = NULL, updated_at = :updated_at
				WHERE id = :id
			');
			$archive->execute([
				'archived_at' => $nowValue,
				'updated_at' => $nowValue,
				'id' => $monitorId,
			]);
			$this->WriteAudit(
				$connection,
				$userId,
				'monitor.archived',
				$monitorId,
				$nowValue,
				['cycle_id' => (int)$cycle['id']]
			);
			$connection->commit();
			$this->_logger->Info('Escalated monitor archived', ['user_id' => $userId, 'monitor_id' => $monitorId]);

			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Forces an active monitor into awaiting state for explicit development testing.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return bool True when an owned active monitor is now awaiting check-in.
	 */
	public function ForceDueForUser(int $monitorId, int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$now = $this->UtcNow();
			$nowValue = $this->FormatUtc($now);
			$monitor = $this->LockMonitorForUser($connection, $monitorId, $userId);

			if (!is_array($monitor) || !empty($monitor['is_paused']) || !empty($monitor['is_archived']))
			{
				$connection->commit();
				return false;
			}

			$cycle = $this->FindOpenCycleForUpdate($connection, $monitorId);

			if (!is_array($cycle))
			{
				$cycle = $this->EnsureOpenCycle($connection, $monitor, $now);
			}

			if ((string)$cycle['status'] !== MonitorStateMachine::SCHEDULED)
			{
				$connection->commit();
				return false;
			}

			$this->_stateMachine->AssertTransition(MonitorStateMachine::SCHEDULED, MonitorStateMachine::AWAITING);
			$responseDeadline = $this->AddDays($now, (int)$monitor['response_window_days']);
			$updateCycle = $connection->prepare('
				UPDATE check_cycles
				SET
					status = :status,
					due_at = :due_at,
					response_deadline_at = :response_deadline_at,
					updated_at = :updated_at
				WHERE id = :id
			');
			$updateCycle->execute([
				'status' => MonitorStateMachine::AWAITING,
				'due_at' => $nowValue,
				'response_deadline_at' => $this->FormatUtc($responseDeadline),
				'updated_at' => $nowValue,
				'id' => (int)$cycle['id'],
			]);

			$updateMonitor = $connection->prepare('
				UPDATE monitors
				SET next_check_due_at = :due_at, updated_at = :updated_at
				WHERE id = :id
			');
			$updateMonitor->execute(['due_at' => $nowValue, 'updated_at' => $nowValue, 'id' => $monitorId]);
			$this->WriteAudit($connection, $userId, 'monitor.forced_due', $monitorId, $nowValue);
			$connection->commit();

			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Marks a cycle overdue after notification infrastructure confirms that all owner reminders were sent.
	 * @param int $cycleId Check-cycle ID.
	 * @return bool True when the transition occurred.
	 */
	public function MarkCycleOverdue(int $cycleId): bool
	{
		return $this->AdvanceNotificationCycle($cycleId, MonitorStateMachine::OVERDUE, 'monitor.overdue', 'overdue_at');
	}

	/**
	 * @brief Marks a cycle escalated after recipient delivery has actually begun.
	 * @param int $cycleId Check-cycle ID.
	 * @return bool True when the transition occurred.
	 */
	public function MarkCycleEscalated(int $cycleId): bool
	{
		return $this->AdvanceNotificationCycle($cycleId, MonitorStateMachine::ESCALATED, 'monitor.escalated', 'escalated_at');
	}

	/**
	 * @brief Returns recent user-visible lifecycle activity for the dashboard.
	 * @param int $userId Owner user ID.
	 * @param int $limit Maximum number of entries.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindRecentActivityForUser(int $userId, int $limit = 12): array
	{
		return $this->FindActivityPageForUser($userId, 1, $limit);
	}

	/**
	 * @brief Returns one page of complete lifecycle activity.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindActivityPageForUser(int $userId, int $page, int $perPage = 50): array
	{
		$perPage = max(1, min(100, $perPage));
		$page = max(1, $page);
		$offset = ($page - 1) * $perPage;
		$statement = $this->_database->GetConnection()->prepare('
			SELECT
				a.id AS audit_id,
				a.event_type,
				a.entity_id,
				a.context_json,
				a.created_at,
				m.name AS monitor_name,
				cil.latitude AS location_latitude,
				cil.longitude AS location_longitude,
				cil.accuracy_meters AS location_accuracy_meters,
				cil.address_label AS location_address_label
			FROM audit_log a
			LEFT JOIN monitors m
				ON a.entity_type = \'monitor\'
				AND m.id = a.entity_id
				AND m.user_id = a.user_id
			LEFT JOIN check_in_locations cil ON cil.audit_log_id = a.id
			WHERE a.user_id = :user_id
				AND a.event_type IN
				(
					\'monitor.checked_in\',
					\'monitor.awaiting\',
					\'monitor.safety_requested\',
					\'monitor.safety_expired\',
					\'monitor.safety_confirmed\',
					\'monitor.overdue\',
					\'monitor.escalated\',
					\'monitor.reset_reactivated\',
					\'monitor.archived\',
					\'monitor.paused\',
					\'monitor.resumed\',
					\'monitor.forced_due\',
					\'mail.due_notice_sent\',
					\'mail.reminder_sent\'
					,\'mail.safety_invitation_sent\'
					,\'mail.safety_reminder_sent\'
					,\'mail.recipient_sent\'
					,\'mail.recipient_failed\'
				)
			ORDER BY a.created_at DESC, a.id DESC
			LIMIT ' . $perPage . '
			OFFSET ' . $offset . '
		');
		$statement->execute(['user_id' => $userId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/** @brief Counts complete lifecycle activity for pagination. */
	public function CountActivityForUser(int $userId): int
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT COUNT(*)
			FROM audit_log
			WHERE user_id = :user_id
				AND event_type IN
				(
					\'monitor.checked_in\',
					\'monitor.awaiting\',
					\'monitor.safety_requested\',
					\'monitor.safety_expired\',
					\'monitor.safety_confirmed\',
					\'monitor.overdue\',
					\'monitor.escalated\',
					\'monitor.reset_reactivated\',
					\'monitor.archived\',
					\'monitor.paused\',
					\'monitor.resumed\',
					\'monitor.forced_due\',
					\'mail.due_notice_sent\',
					\'mail.reminder_sent\'
					,\'mail.safety_invitation_sent\'
					,\'mail.safety_reminder_sent\'
					,\'mail.recipient_sent\'
					,\'mail.recipient_failed\'
				)
		');
		$statement->execute(['user_id' => $userId]);
		return (int)$statement->fetchColumn();
	}

	/**
	 * @brief Reconciles one active monitor's scheduled cycle with current settings.
	 * @param PDO $connection Active connection and transaction.
	 * @param array<string, mixed> $monitor Locked monitor row.
	 * @param DateTimeImmutable $now Shared UTC operation time.
	 * @return bool True when the cycle became awaiting.
	 */
	private function SynchronizeMonitor(PDO $connection, array $monitor, DateTimeImmutable $now): bool
	{
		$cycle = $this->FindOpenCycleForUpdate($connection, (int)$monitor['id']);
		$createdCycle = false;

		if (!is_array($cycle))
		{
			$cycle = $this->EnsureOpenCycle($connection, $monitor, $now);
			$createdCycle = true;
		}

		if ((string)$cycle['status'] !== MonitorStateMachine::SCHEDULED)
		{
			return $createdCycle && (string)$cycle['status'] === MonitorStateMachine::AWAITING;
		}

		$startedAt = $this->ParseUtc((string)$cycle['started_at'], $now);
		$dueAt = $this->AddDays($startedAt, (int)$monitor['check_interval_days']);
		$responseDeadline = $this->AddDays($dueAt, (int)$monitor['response_window_days']);
		$status = $dueAt <= $now ? MonitorStateMachine::AWAITING : MonitorStateMachine::SCHEDULED;

		if ($status === MonitorStateMachine::AWAITING)
		{
			$this->_stateMachine->AssertTransition(MonitorStateMachine::SCHEDULED, MonitorStateMachine::AWAITING);
		}

		$dueValue = $this->FormatUtc($dueAt);
		$responseDeadlineValue = $this->FormatUtc($responseDeadline);
		$cycleChanged = (string)$cycle['status'] !== $status
			|| (string)$cycle['due_at'] !== $dueValue
			|| (string)$cycle['response_deadline_at'] !== $responseDeadlineValue
				|| (int)$cycle['reminder_interval_days'] !== (int)$monitor['reminder_interval_days']
				|| (int)$cycle['max_reminders'] !== (int)$monitor['max_reminders']
				|| (string)$cycle['escalation_policy_snapshot'] !== (string)$monitor['escalation_policy']
				|| (int)$cycle['safety_response_window_days'] !== (int)$monitor['safety_response_window_days']
				|| (int)$cycle['safety_reminder_interval_days'] !== (int)$monitor['safety_reminder_interval_days']
				|| (int)$cycle['safety_max_reminders'] !== (int)$monitor['safety_max_reminders']
				|| (int)$cycle['safety_required_confirmations'] !== (int)$monitor['safety_required_confirmations']
				|| (int)$cycle['safety_confirmation_days'] !== $this->SafetyConfirmationDays($monitor);

		if ($cycleChanged)
		{
			$updateCycle = $connection->prepare('
				UPDATE check_cycles
				SET
					status = :status,
					due_at = :due_at,
					response_deadline_at = :response_deadline_at,
					reminder_interval_days = :reminder_interval_days,
					max_reminders = :max_reminders,
					escalation_policy_snapshot = :escalation_policy_snapshot,
					safety_response_window_days = :safety_response_window_days,
					safety_reminder_interval_days = :safety_reminder_interval_days,
					safety_max_reminders = :safety_max_reminders,
					safety_required_confirmations = :safety_required_confirmations,
					safety_confirmation_days = :safety_confirmation_days,
					updated_at = :updated_at
				WHERE id = :id
			');
			$updateCycle->execute([
				'status' => $status,
				'due_at' => $dueValue,
				'response_deadline_at' => $responseDeadlineValue,
				'reminder_interval_days' => (int)$monitor['reminder_interval_days'],
				'max_reminders' => (int)$monitor['max_reminders'],
				'escalation_policy_snapshot' => (string)$monitor['escalation_policy'],
				'safety_response_window_days' => (int)$monitor['safety_response_window_days'],
				'safety_reminder_interval_days' => (int)$monitor['safety_reminder_interval_days'],
				'safety_max_reminders' => (int)$monitor['safety_max_reminders'],
				'safety_required_confirmations' => (int)$monitor['safety_required_confirmations'],
				'safety_confirmation_days' => $this->SafetyConfirmationDays($monitor),
				'updated_at' => $this->FormatUtc($now),
				'id' => (int)$cycle['id'],
			]);
		}

		if ((string)($monitor['next_check_due_at'] ?? '') !== $dueValue)
		{
			$updateMonitor = $connection->prepare('
				UPDATE monitors
				SET next_check_due_at = :next_due_at, updated_at = :updated_at
				WHERE id = :id
			');
			$updateMonitor->execute([
				'next_due_at' => $dueValue,
				'updated_at' => $this->FormatUtc($now),
				'id' => (int)$monitor['id'],
			]);
		}

		if ($status === MonitorStateMachine::AWAITING)
		{
			$this->WriteAudit(
				$connection,
				(int)$monitor['user_id'],
				'monitor.awaiting',
				(int)$monitor['id'],
				$this->FormatUtc($now),
				['cycle_id' => (int)$cycle['id'], 'due_at' => $dueValue]
			);
		}

		return $status === MonitorStateMachine::AWAITING;
	}

	/**
	 * @brief Creates a missing open cycle for an active monitor.
	 * @param PDO $connection Active connection and transaction.
	 * @param array<string, mixed> $monitor Locked monitor row.
	 * @param DateTimeImmutable $now Shared UTC operation time.
	 * @return array<string, mixed> Newly inserted cycle.
	 */
	private function EnsureOpenCycle(PDO $connection, array $monitor, DateTimeImmutable $now): array
	{
		$startedAt = $this->ParseUtc(
			(string)($monitor['last_confirmed_at'] ?? $monitor['created_at'] ?? ''),
			$now
		);
		$dueAt = $this->AddDays($startedAt, (int)$monitor['check_interval_days']);
		$status = $dueAt <= $now ? MonitorStateMachine::AWAITING : MonitorStateMachine::SCHEDULED;
		$responseDeadline = $this->AddDays($dueAt, (int)$monitor['response_window_days']);
		$statement = $connection->prepare('
			INSERT INTO check_cycles
			(
				monitor_id,
				status,
				started_at,
				due_at,
				response_deadline_at,
				reminder_interval_days,
					max_reminders,
					escalation_policy_snapshot,
					safety_response_window_days,
					safety_reminder_interval_days,
					safety_max_reminders,
					safety_required_confirmations,
					safety_confirmation_days,
					reminders_sent,
				updated_at
			)
			VALUES
			(
				:monitor_id,
				:status,
				:started_at,
				:due_at,
				:response_deadline_at,
				:reminder_interval_days,
					:max_reminders,
					:escalation_policy_snapshot,
					:safety_response_window_days,
					:safety_reminder_interval_days,
					:safety_max_reminders,
					:safety_required_confirmations,
					:safety_confirmation_days,
					0,
				:updated_at
			)
		');
		$statement->execute([
			'monitor_id' => (int)$monitor['id'],
			'status' => $status,
			'started_at' => $this->FormatUtc($startedAt),
			'due_at' => $this->FormatUtc($dueAt),
			'response_deadline_at' => $this->FormatUtc($responseDeadline),
			'reminder_interval_days' => (int)$monitor['reminder_interval_days'],
				'max_reminders' => (int)$monitor['max_reminders'],
				'escalation_policy_snapshot' => (string)$monitor['escalation_policy'],
				'safety_response_window_days' => (int)$monitor['safety_response_window_days'],
				'safety_reminder_interval_days' => (int)$monitor['safety_reminder_interval_days'],
				'safety_max_reminders' => (int)$monitor['safety_max_reminders'],
				'safety_required_confirmations' => (int)$monitor['safety_required_confirmations'],
				'safety_confirmation_days' => $this->SafetyConfirmationDays($monitor),
			'updated_at' => $this->FormatUtc($now),
		]);

		$cycleId = (int)$connection->lastInsertId();
		$updateMonitor = $connection->prepare('
			UPDATE monitors
			SET next_check_due_at = :next_due_at
			WHERE id = :id
		');
		$updateMonitor->execute([
			'next_due_at' => $this->FormatUtc($dueAt),
			'id' => (int)$monitor['id'],
		]);

		if ($status === MonitorStateMachine::AWAITING)
		{
			$this->WriteAudit(
				$connection,
				(int)$monitor['user_id'],
				'monitor.awaiting',
				(int)$monitor['id'],
				$this->FormatUtc($now),
				['cycle_id' => $cycleId, 'due_at' => $this->FormatUtc($dueAt)]
			);
		}

		return [
			'id' => $cycleId,
			'monitor_id' => (int)$monitor['id'],
			'status' => $status,
			'started_at' => $this->FormatUtc($startedAt),
			'due_at' => $this->FormatUtc($dueAt),
			'response_deadline_at' => $this->FormatUtc($responseDeadline),
			'reminder_interval_days' => (int)$monitor['reminder_interval_days'],
				'max_reminders' => (int)$monitor['max_reminders'],
				'escalation_policy_snapshot' => (string)$monitor['escalation_policy'],
				'safety_response_window_days' => (int)$monitor['safety_response_window_days'],
				'safety_reminder_interval_days' => (int)$monitor['safety_reminder_interval_days'],
				'safety_max_reminders' => (int)$monitor['safety_max_reminders'],
				'safety_required_confirmations' => (int)$monitor['safety_required_confirmations'],
				'safety_confirmation_days' => $this->SafetyConfirmationDays($monitor),
			'reminders_sent' => 0,
		];
	}

	/**
	 * @brief Inserts the next scheduled cycle after a check-in or resume.
	 * @param PDO $connection Active connection and transaction.
	 * @param array<string, mixed> $monitor Locked monitor row.
	 * @param DateTimeImmutable $startedAt Cycle start.
	 * @param DateTimeImmutable $dueAt Cycle due time.
	 * @return int New cycle ID.
	 */
	private function InsertScheduledCycle(
		PDO $connection,
		array $monitor,
		DateTimeImmutable $startedAt,
		DateTimeImmutable $dueAt
	): int
	{
		$responseDeadline = $this->AddDays($dueAt, (int)$monitor['response_window_days']);
		$statement = $connection->prepare('
			INSERT INTO check_cycles
			(
				monitor_id,
				status,
				started_at,
				due_at,
				response_deadline_at,
				reminder_interval_days,
					max_reminders,
					escalation_policy_snapshot,
					safety_response_window_days,
					safety_reminder_interval_days,
					safety_max_reminders,
					safety_required_confirmations,
					safety_confirmation_days,
					reminders_sent,
				updated_at
			)
			VALUES
			(
				:monitor_id,
				:status,
				:started_at,
				:due_at,
				:response_deadline_at,
				:reminder_interval_days,
					:max_reminders,
					:escalation_policy_snapshot,
					:safety_response_window_days,
					:safety_reminder_interval_days,
					:safety_max_reminders,
					:safety_required_confirmations,
					:safety_confirmation_days,
					0,
				:updated_at
			)
		');
		$statement->execute([
			'monitor_id' => (int)$monitor['id'],
			'status' => MonitorStateMachine::SCHEDULED,
			'started_at' => $this->FormatUtc($startedAt),
			'due_at' => $this->FormatUtc($dueAt),
			'response_deadline_at' => $this->FormatUtc($responseDeadline),
			'reminder_interval_days' => (int)$monitor['reminder_interval_days'],
				'max_reminders' => (int)$monitor['max_reminders'],
				'escalation_policy_snapshot' => (string)$monitor['escalation_policy'],
				'safety_response_window_days' => (int)$monitor['safety_response_window_days'],
				'safety_reminder_interval_days' => (int)$monitor['safety_reminder_interval_days'],
				'safety_max_reminders' => (int)$monitor['safety_max_reminders'],
				'safety_required_confirmations' => (int)$monitor['safety_required_confirmations'],
				'safety_confirmation_days' => $this->SafetyConfirmationDays($monitor),
			'updated_at' => $this->FormatUtc($startedAt),
		]);

		return (int)$connection->lastInsertId();
	}

	/**
	 * @brief Advances an internal notification-driven state with audit logging.
	 * @param int $cycleId Check-cycle ID.
	 * @param string $targetStatus Target state.
	 * @param string $eventType Audit event type.
	 * @param string $timestampColumn Whitelisted transition timestamp column.
	 * @return bool True when the transition occurred.
	 */
	private function AdvanceNotificationCycle(
		int $cycleId,
		string $targetStatus,
		string $eventType,
		string $timestampColumn
	): bool
	{
		if (!in_array($timestampColumn, ['overdue_at', 'escalated_at'], true))
		{
			throw new \InvalidArgumentException('Invalid lifecycle timestamp column.');
		}

		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$statement = $connection->prepare('
				SELECT
					cc.id,
					cc.monitor_id,
					cc.status,
					cc.response_deadline_at,
					cc.reminder_interval_days,
					cc.max_reminders,
					cc.reminders_sent,
					cc.due_notice_sent_at,
					m.user_id
				FROM check_cycles cc
				INNER JOIN monitors m ON m.id = cc.monitor_id
				WHERE cc.id = :id
				FOR UPDATE
			');
			$statement->execute(['id' => $cycleId]);
			$cycle = $statement->fetch(PDO::FETCH_ASSOC);

			if (!is_array($cycle))
			{
				$connection->commit();
				return false;
			}

			$now = $this->UtcNow();

			if ($targetStatus === MonitorStateMachine::OVERDUE)
			{
				$dueNoticeSent = !empty($cycle['due_notice_sent_at']);
				$allRemindersSent = (int)$cycle['reminders_sent'] >= (int)$cycle['max_reminders'];
				$eligibleAt = $this->AddDays(
					$this->ParseUtc((string)$cycle['response_deadline_at'], $now),
					(int)$cycle['reminder_interval_days'] * (int)$cycle['max_reminders']
				);

				if (!$dueNoticeSent || !$allRemindersSent || $now < $eligibleAt)
				{
					$connection->commit();
					return false;
				}
			}

			$this->_stateMachine->AssertTransition((string)$cycle['status'], $targetStatus);
			$nowValue = $this->FormatUtc($now);
			$update = $connection->prepare('
				UPDATE check_cycles
				SET status = :status, ' . $timestampColumn . ' = :transitioned_at, updated_at = :updated_at
				WHERE id = :id
			');
			$update->execute([
				'status' => $targetStatus,
				'transitioned_at' => $nowValue,
				'updated_at' => $nowValue,
				'id' => $cycleId,
			]);

			if ($targetStatus === MonitorStateMachine::ESCALATED)
			{
				$clearDue = $connection->prepare('UPDATE monitors SET next_check_due_at = NULL, updated_at = :updated_at WHERE id = :id');
				$clearDue->execute(['updated_at' => $nowValue, 'id' => (int)$cycle['monitor_id']]);
			}

			$this->WriteAudit(
				$connection,
				(int)$cycle['user_id'],
				$eventType,
				(int)$cycle['monitor_id'],
				$nowValue,
				['cycle_id' => $cycleId]
			);
			$connection->commit();

			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Locks all active monitors belonging to a user.
	 * @param PDO $connection Active connection and transaction.
	 * @param int $userId Owner user ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function LockActiveMonitorsForUser(PDO $connection, int $userId): array
	{
		$statement = $connection->prepare('
			SELECT
				id,
				user_id,
				check_interval_days,
				response_window_days,
					reminder_interval_days,
					max_reminders,
					escalation_policy,
					safety_response_window_days,
					safety_reminder_interval_days,
					safety_max_reminders,
					safety_required_confirmations,
					safety_confirmation_days,
					is_paused,
				paused_at,
				is_archived,
				archived_at,
				last_confirmed_at,
				next_check_due_at,
				location_check_in_enabled,
				created_at
			FROM monitors
			WHERE user_id = :user_id
				AND is_paused = 0
				AND is_archived = 0
				AND NOT EXISTS
				(
					SELECT 1
					FROM check_cycles active_cc
					WHERE active_cc.monitor_id = monitors.id
					  AND active_cc.status = \'escalated\'
				)
			ORDER BY id ASC
			FOR UPDATE
		');
		$statement->execute(['user_id' => $userId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Locks one owned monitor.
	 * @param PDO $connection Active connection and transaction.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return array<string, mixed>|null
	 */
	private function LockMonitorForUser(PDO $connection, int $monitorId, int $userId): ?array
	{
		$statement = $connection->prepare('
			SELECT
				id,
				user_id,
				check_interval_days,
				response_window_days,
					reminder_interval_days,
					max_reminders,
					escalation_policy,
					safety_response_window_days,
					safety_reminder_interval_days,
					safety_max_reminders,
					safety_required_confirmations,
					safety_confirmation_days,
					is_paused,
				paused_at,
				is_archived,
				archived_at,
				last_confirmed_at,
				next_check_due_at,
				location_check_in_enabled,
				created_at
			FROM monitors
			WHERE id = :id
				AND user_id = :user_id
			FOR UPDATE
		');
		$statement->execute(['id' => $monitorId, 'user_id' => $userId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Finds and locks the one current cycle for a monitor.
	 * @param PDO $connection Active connection and transaction.
	 * @param int $monitorId Monitor ID.
	 * @return array<string, mixed>|null
	 */
	private function FindOpenCycleForUpdate(PDO $connection, int $monitorId): ?array
	{
		$statement = $connection->prepare('
			SELECT
				id,
				monitor_id,
				status,
				started_at,
				due_at,
				response_deadline_at,
					reminder_interval_days,
					max_reminders,
					escalation_policy_snapshot,
					safety_response_window_days,
					safety_reminder_interval_days,
					safety_max_reminders,
					safety_required_confirmations,
					safety_confirmation_days,
					reminders_sent
			FROM check_cycles
			WHERE monitor_id = :monitor_id
					AND status IN (\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\')
			ORDER BY id DESC
			LIMIT 1
			FOR UPDATE
		');
		$statement->execute(['monitor_id' => $monitorId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Writes a lifecycle audit entry without personal message content.
	 * @param PDO $connection Active connection and transaction.
	 * @param int $userId Owner user ID.
	 * @param string $eventType Event identifier.
	 * @param int $monitorId Monitor ID.
	 * @param string $createdAt UTC timestamp.
	 * @param array<string, mixed> $context Non-sensitive event context.
	 * @return int New audit-log ID.
	 */
	private function WriteAudit(
		PDO $connection,
		int $userId,
		string $eventType,
		int $monitorId,
		string $createdAt,
		array $context = []
	): int
	{
		$statement = $connection->prepare('
			INSERT INTO audit_log
			(user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES
			(:user_id, :event_type, \'monitor\', :entity_id, :message, :context_json, :created_at)
		');
		$statement->execute([
			'user_id' => $userId,
			'event_type' => $eventType,
			'entity_id' => $monitorId,
			'message' => $eventType,
			'context_json' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
			'created_at' => $createdAt,
		]);

		return (int)$connection->lastInsertId();
	}

	/**
	 * @brief Stores a validated location beside its check-in audit entry.
	 * @param PDO $connection Active connection and transaction.
	 * @param int $auditId Check-in audit-log ID.
	 * @param int $checkCycleId Closed cycle ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @param array{latitude: float, longitude: float, accuracy_meters: float, address_label: string|null} $location Validated location.
	 * @param string $createdAt UTC check-in timestamp.
	 */
	private function InsertCheckInLocation(
		PDO $connection,
		int $auditId,
		int $checkCycleId,
		int $monitorId,
		int $userId,
		array $location,
		string $createdAt
	): void
	{
		$statement = $connection->prepare('
			INSERT INTO check_in_locations
			(
				audit_log_id,
				check_cycle_id,
				monitor_id,
				user_id,
				latitude,
				longitude,
				accuracy_meters,
				address_label,
				created_at
			)
			VALUES
			(
				:audit_log_id,
				:check_cycle_id,
				:monitor_id,
				:user_id,
				:latitude,
				:longitude,
				:accuracy_meters,
				:address_label,
				:created_at
			)
		');
		$statement->execute([
			'audit_log_id' => $auditId,
			'check_cycle_id' => $checkCycleId,
			'monitor_id' => $monitorId,
			'user_id' => $userId,
			'latitude' => $location['latitude'],
			'longitude' => $location['longitude'],
			'accuracy_meters' => $location['accuracy_meters'],
			'address_label' => $location['address_label'],
			'created_at' => $createdAt,
		]);
	}

	/**
	 * @brief Cancels durable owner-notification jobs that have not yet been claimed.
	 * @param PDO $connection Active connection and transaction.
	 * @param int $cycleId Closing cycle ID.
	 * @param string $cancelledAt UTC timestamp.
	 */
	private function CancelQueuedReminders(PDO $connection, int $cycleId, string $cancelledAt): void
	{
		$statement = $connection->prepare('
			UPDATE mail_queue
			SET status = \'cancelled\',
				body_text = CASE
					WHEN mail_type IN (\'safety_invitation\', \'safety_reminder\') THEN \'[Safety link redacted after cancellation]\'
					ELSE body_text
				END,
				cancelled_at = :cancelled_at, updated_at = :updated_at
			WHERE check_cycle_id = :cycle_id
				AND status IN (\'queued\', \'retrying\', \'failed\')
		');
		$statement->execute([
			'cancelled_at' => $cancelledAt,
			'updated_at' => $cancelledAt,
			'cycle_id' => $cycleId,
		]);
		$cancelSafety = $connection->prepare('
			UPDATE safety_contact_requests
			SET status = \'cancelled\', updated_at = :updated_at
			WHERE check_cycle_id = :cycle_id AND status = \'pending\'
		');
		$cancelSafety->execute(['updated_at' => $cancelledAt, 'cycle_id' => $cycleId]);
		$cancelDeliveries = $connection->prepare('
			UPDATE recipient_release_deliveries
			SET status = \'cancelled\', cancelled_at = :cancelled_at, updated_at = :updated_at
			WHERE check_cycle_id = :cycle_id AND status IN (\'queued\', \'failed\')
		');
		$cancelDeliveries->execute([
			'cancelled_at' => $cancelledAt,
			'updated_at' => $cancelledAt,
			'cycle_id' => $cycleId,
		]);
		$cancelRelease = $connection->prepare('
			UPDATE recipient_releases
			SET status = \'cancelled\', cancelled_at = :cancelled_at, updated_at = :updated_at
			WHERE check_cycle_id = :cycle_id AND status IN (\'blocked\', \'pending\', \'failed\')
		');
		$cancelRelease->execute([
			'cancelled_at' => $cancelledAt,
			'updated_at' => $cancelledAt,
			'cycle_id' => $cycleId,
		]);
	}

	/** @brief Resolves external-confirmation duration, defaulting to the normal check interval. */
	private function SafetyConfirmationDays(array $monitor): int
	{
		$configured = isset($monitor['safety_confirmation_days']) ? (int)$monitor['safety_confirmation_days'] : 0;
		return $configured > 0 ? $configured : max(1, (int)$monitor['check_interval_days']);
	}

	/** @brief Returns the current UTC time. @return DateTimeImmutable */
	private function UtcNow(): DateTimeImmutable
	{
		return new DateTimeImmutable('now', new DateTimeZone('UTC'));
	}

	/** @brief Parses a UTC database timestamp. @param string $value Timestamp. @param DateTimeImmutable $fallback Fallback. @return DateTimeImmutable */
	private function ParseUtc(string $value, DateTimeImmutable $fallback): DateTimeImmutable
	{
		if (trim($value) === '')
		{
			return $fallback;
		}

		try
		{
			return new DateTimeImmutable($value, new DateTimeZone('UTC'));
		}
		catch (Throwable)
		{
			return $fallback;
		}
	}

	/** @brief Adds whole UTC days. @param DateTimeImmutable $value Base timestamp. @param int $days Days. @return DateTimeImmutable */
	private function AddDays(DateTimeImmutable $value, int $days): DateTimeImmutable
	{
		return $value->add(new DateInterval('P' . max(0, $days) . 'D'));
	}

	/** @brief Formats a UTC database timestamp. @param DateTimeImmutable $value Timestamp. @return string */
	private function FormatUtc(DateTimeImmutable $value): string
	{
		return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}
}
