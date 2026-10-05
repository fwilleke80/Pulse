<?php

/**
 * @file MonitorStatusService.php
 * @brief Read-only operational status and lifecycle history for monitors.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Pulse\Core\Database;

/**
 * @brief Builds a transparent read model from the persisted monitor lifecycle.
 */
final class MonitorStatusService
{
	private Database $_database;

	/**
	 * @brief Constructs the monitor-status reader.
	 * @param Database $database Database service.
	 */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Returns operational status keyed by monitor ID for one owner.
	 * @param int $userId Owner user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindAllForUser(int $userId): array
	{
		$statement = $this->_database->GetConnection()->prepare($this->StatusSql('m.user_id = :user_id'));
		$statement->execute(['user_id' => $userId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		$result = [];

		foreach (is_array($rows) ? $rows : [] as $row)
		{
			$result[(int)$row['monitor_id']] = $this->BuildStatus($row);
		}

		return $result;
	}

	/**
	 * @brief Returns the operational status of one monitor owned by a user.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return array<string, mixed>|null
	 */
	public function FindForMonitorForUser(int $monitorId, int $userId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare($this->StatusSql('m.id = :monitor_id AND m.user_id = :user_id'));
		$statement->execute([
			'monitor_id' => $monitorId,
			'user_id' => $userId,
		]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $this->BuildStatus($row) : null;
	}

	/**
	 * @brief Returns recent lifecycle history for one owned monitor.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @param int $limit Maximum number of events.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindHistoryForMonitorForUser(int $monitorId, int $userId, int $limit = 100): array
	{
		$limit = max(1, min(250, $limit));
		$statement = $this->_database->GetConnection()->prepare('
			SELECT a.event_type, a.context_json, a.created_at
			FROM audit_log a
			INNER JOIN monitors m ON m.id = a.entity_id AND m.user_id = a.user_id
			WHERE a.user_id = :user_id
				AND a.entity_type = \'monitor\'
				AND a.entity_id = :monitor_id
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
					\'mail.reminder_sent\',
					\'mail.safety_invitation_sent\',
					\'mail.safety_reminder_sent\',
					\'mail.recipient_sent\',
					\'mail.recipient_failed\'
				)
			ORDER BY a.created_at DESC, a.id DESC
			LIMIT ' . $limit . '
		');
		$statement->execute([
			'user_id' => $userId,
			'monitor_id' => $monitorId,
		]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		$history = [];

		foreach (is_array($rows) ? $rows : [] as $row)
		{
			$context = [];
			$encoded = isset($row['context_json']) ? (string)$row['context_json'] : '';

			if ($encoded !== '')
			{
				$decoded = json_decode($encoded, true);
				$context = is_array($decoded) ? $decoded : [];
			}

			$history[] = [
				'event_type' => (string)$row['event_type'],
				'created_at' => (string)$row['created_at'],
				'context' => $context,
			];
		}

		return $history;
	}

	/**
	 * @brief SQL for the current-cycle operational read model.
	 * @param string $where Trusted internal WHERE expression.
	 * @return string SQL statement.
	 */
	private function StatusSql(string $where): string
	{
		return '
			SELECT
				m.id AS monitor_id,
				m.is_paused,
				m.is_archived,
				m.next_check_due_at,
				cc.id AS cycle_id,
				cc.status AS cycle_status,
				cc.started_at,
				cc.due_at,
				cc.response_deadline_at,
				cc.reminder_interval_days,
				cc.max_reminders,
				cc.reminders_sent,
				cc.due_notice_sent_at,
				cc.escalation_policy_snapshot,
				cc.safety_response_window_days,
				cc.safety_reminder_interval_days,
				cc.safety_max_reminders,
				cc.safety_required_confirmations,
				cc.safety_confirmation_count,
				cc.safety_gate_started_at,
				cc.safety_gate_deadline_at,
				(
					SELECT MIN(scr.reminders_sent)
					FROM safety_contact_requests scr
					WHERE scr.check_cycle_id = cc.id
						AND scr.status = \'pending\'
				) AS safety_min_reminders_sent,
				(
					SELECT COUNT(*)
					FROM safety_contact_requests scr
					WHERE scr.check_cycle_id = cc.id
						AND scr.status = \'pending\'
				) AS safety_pending_request_count,
				(
					SELECT rr.status
					FROM recipient_releases rr
					WHERE rr.check_cycle_id = cc.id
					LIMIT 1
				) AS release_status,
				(
					SELECT rr.blocked_reason
					FROM recipient_releases rr
					WHERE rr.check_cycle_id = cc.id
					LIMIT 1
				) AS release_blocked_reason,
				(
					SELECT MIN(mq.available_at)
					FROM mail_queue mq
					WHERE mq.check_cycle_id = cc.id
						AND mq.status IN (\'queued\', \'retrying\', \'processing\')
				) AS next_queued_mail_at,
				(
					SELECT COUNT(*)
					FROM mail_queue mq
					WHERE mq.check_cycle_id = cc.id
						AND mq.mail_type IN (\'owner_due_notice\', \'owner_reminder\', \'safety_invitation\', \'safety_reminder\', \'recipient_notification\')
						AND mq.status = \'failed\'
				) AS failed_notification_count
			FROM monitors m
			LEFT JOIN check_cycles cc ON cc.id =
			(
				SELECT current_cc.id
				FROM check_cycles current_cc
				WHERE current_cc.monitor_id = m.id
					AND current_cc.status IN (\'scheduled\', \'awaiting\', \'safety_pending\', \'overdue\', \'escalated\')
				ORDER BY current_cc.id DESC
				LIMIT 1
			)
			WHERE ' . $where . '
			ORDER BY m.name ASC
		';
	}

	/**
	 * @brief Converts one database row into current health, next action, and future plan.
	 * @param array<string, mixed> $row Current-cycle row.
	 * @return array<string, mixed>
	 */
	private function BuildStatus(array $row): array
	{
		$cycleStatus = isset($row['cycle_status']) ? (string)$row['cycle_status'] : '';
		$nextAction = $this->NextAction($row);
		$issues = [];

		if ((int)($row['failed_notification_count'] ?? 0) > 0)
		{
			$issues[] = 'delivery_failed';
		}

		if ((string)($row['release_status'] ?? '') === 'blocked')
		{
			$issues[] = 'release_blocked';
		}

		return [
			'monitor_id' => (int)$row['monitor_id'],
			'cycle_id' => isset($row['cycle_id']) ? (int)$row['cycle_id'] : null,
			'cycle_status' => $cycleStatus,
			'issues' => $issues,
			'is_healthy' => $issues === [],
			'failed_notification_count' => (int)($row['failed_notification_count'] ?? 0),
			'release_status' => isset($row['release_status']) ? (string)$row['release_status'] : null,
			'release_blocked_reason' => isset($row['release_blocked_reason']) ? (string)$row['release_blocked_reason'] : null,
			'next_action' => $nextAction,
			'plan' => $this->FuturePlan($row),
			'cycle' => [
				'started_at' => $row['started_at'] ?? null,
				'due_at' => $row['due_at'] ?? null,
				'response_deadline_at' => $row['response_deadline_at'] ?? null,
				'due_notice_sent_at' => $row['due_notice_sent_at'] ?? null,
				'reminders_sent' => (int)($row['reminders_sent'] ?? 0),
				'max_reminders' => (int)($row['max_reminders'] ?? 0),
				'reminder_interval_days' => (int)($row['reminder_interval_days'] ?? 0),
				'escalation_policy' => (string)($row['escalation_policy_snapshot'] ?? ''),
				'safety_gate_started_at' => $row['safety_gate_started_at'] ?? null,
				'safety_gate_deadline_at' => $row['safety_gate_deadline_at'] ?? null,
				'safety_confirmation_count' => (int)($row['safety_confirmation_count'] ?? 0),
				'safety_required_confirmations' => (int)($row['safety_required_confirmations'] ?? 0),
			],
		];
	}

	/**
	 * @brief Determines the next automatic lifecycle action.
	 * @param array<string, mixed> $row Current-cycle row.
	 * @return array{type: string, at: string|null, number?: int, total?: int}
	 */
	private function NextAction(array $row): array
	{
		if (!empty($row['is_archived']))
		{
			return ['type' => 'archived', 'at' => null];
		}

		if (!empty($row['is_paused']))
		{
			return ['type' => 'paused', 'at' => null];
		}

		$status = (string)($row['cycle_status'] ?? '');

		if ($status === 'scheduled')
		{
			return ['type' => 'check_due', 'at' => $this->NullableTimestamp($row['due_at'] ?? null)];
		}

		if ($status === 'awaiting')
		{
			if (empty($row['due_notice_sent_at']))
			{
				return ['type' => 'due_notice', 'at' => $this->NullableTimestamp($row['due_at'] ?? null)];
			}

			$sent = (int)($row['reminders_sent'] ?? 0);
			$maximum = (int)($row['max_reminders'] ?? 0);

			if ($sent < $maximum)
			{
				return [
					'type' => 'owner_reminder',
					'at' => $this->AddDaysToTimestamp(
						$this->NullableTimestamp($row['response_deadline_at'] ?? null),
						(int)($row['reminder_interval_days'] ?? 0) * $sent
					),
					'number' => $sent + 1,
					'total' => $maximum,
				];
			}

			return [
				'type' => (string)($row['escalation_policy_snapshot'] ?? '') === 'safety_contact' ? 'safety_start' : 'recipient_release',
				'at' => $this->OwnerEscalationTime($row),
			];
		}

		if ($status === 'safety_pending')
		{
			if (empty($row['safety_gate_started_at']))
			{
				return ['type' => 'safety_invitation', 'at' => $this->NullableTimestamp($row['next_queued_mail_at'] ?? null)];
			}

			$minimumSent = $row['safety_min_reminders_sent'] === null ? null : (int)$row['safety_min_reminders_sent'];
			$maximum = (int)($row['safety_max_reminders'] ?? 0);

			if ((int)($row['safety_pending_request_count'] ?? 0) > 0 && $minimumSent !== null && $minimumSent < $maximum)
			{
				$days = (int)($row['safety_response_window_days'] ?? 0)
					+ ((int)($row['safety_reminder_interval_days'] ?? 0) * $minimumSent);
				return [
					'type' => 'safety_reminder',
					'at' => $this->AddDaysToTimestamp($this->NullableTimestamp($row['safety_gate_started_at'] ?? null), $days),
					'number' => $minimumSent + 1,
					'total' => $maximum,
				];
			}

			return ['type' => 'safety_expiry', 'at' => $this->NullableTimestamp($row['safety_gate_deadline_at'] ?? null)];
		}

		if ($status === 'overdue')
		{
			$releaseStatus = (string)($row['release_status'] ?? '');

			if ($releaseStatus === 'blocked')
			{
				return ['type' => 'release_blocked', 'at' => null];
			}

			if (in_array($releaseStatus, ['pending', 'partial'], true))
			{
				return ['type' => 'recipient_delivery', 'at' => $this->NullableTimestamp($row['next_queued_mail_at'] ?? null)];
			}

			return ['type' => 'recipient_release', 'at' => null];
		}

		if ($status === 'escalated')
		{
			return ['type' => 'escalated', 'at' => null];
		}

		return ['type' => 'none', 'at' => null];
	}

	/**
	 * @brief Builds the deterministic remainder of the current cycle.
	 * @param array<string, mixed> $row Current-cycle row.
	 * @return array<int, array<string, mixed>>
	 */
	private function FuturePlan(array $row): array
	{
		if (!empty($row['is_archived']) || !empty($row['is_paused']))
		{
			return [];
		}

		$status = (string)($row['cycle_status'] ?? '');
		$plan = [];

		if ($status === 'scheduled' || ($status === 'awaiting' && empty($row['due_notice_sent_at'])))
		{
			$plan[] = ['type' => 'due_notice', 'at' => $this->NullableTimestamp($row['due_at'] ?? null)];
		}

		if (in_array($status, ['scheduled', 'awaiting'], true))
		{
			$sent = $status === 'scheduled' ? 0 : (int)($row['reminders_sent'] ?? 0);
			$maximum = (int)($row['max_reminders'] ?? 0);
			$startNumber = $sent + 1;

			for ($number = $startNumber; $number <= $maximum; $number++)
			{
				$plan[] = [
					'type' => 'owner_reminder',
					'at' => $this->AddDaysToTimestamp(
						$this->NullableTimestamp($row['response_deadline_at'] ?? null),
						(int)($row['reminder_interval_days'] ?? 0) * max(0, $number - 1)
					),
					'number' => $number,
					'total' => $maximum,
				];
			}

			$usesSafetyGate = (string)($row['escalation_policy_snapshot'] ?? '') === 'safety_contact';
			$plan[] = [
				'type' => $usesSafetyGate ? 'safety_start' : 'recipient_release',
				'at' => $this->OwnerEscalationTime($row),
			];

			if ($usesSafetyGate)
			{
				$plan = array_merge($plan, $this->SafetyBranchPlan($row));
			}

			return $this->RemovePastCompletedPlanItems($plan, $row);
		}

		if ($status === 'safety_pending')
		{
			if (empty($row['safety_gate_started_at']))
			{
				$plan[] = ['type' => 'safety_invitation', 'at' => $this->NullableTimestamp($row['next_queued_mail_at'] ?? null)];
				return array_merge($plan, $this->SafetyBranchPlan($row));
			}

			return $this->SafetyBranchPlan($row);
		}

		if ($status === 'overdue')
		{
			return [$this->NextAction($row)];
		}

		return [];
	}

	/**
	 * @brief Builds the conditional safety-contact branch of the current cycle.
	 * @param array<string, mixed> $row Current-cycle row.
	 * @return array<int, array<string, mixed>> Conditional safety-stage plan.
	 */
	private function SafetyBranchPlan(array $row): array
	{
		$plan = [[
			'type' => 'safety_postpone',
			'at' => null,
			'condition' => 'safety_confirmed',
		]];
		$gateStart = $this->NullableTimestamp($row['safety_gate_started_at'] ?? null);
		$minimumSent = $row['safety_min_reminders_sent'] === null ? 0 : (int)$row['safety_min_reminders_sent'];
		$maximum = (int)($row['safety_max_reminders'] ?? 0);
		$hasPendingRequests = $gateStart === null || (int)($row['safety_pending_request_count'] ?? 0) > 0;

		for ($number = $minimumSent + 1; $hasPendingRequests && $number <= $maximum; $number++)
		{
			$days = (int)($row['safety_response_window_days'] ?? 0)
				+ ((int)($row['safety_reminder_interval_days'] ?? 0) * max(0, $number - 1));
			$action = [
				'type' => 'safety_reminder',
				'at' => $gateStart === null ? null : $this->AddDaysToTimestamp($gateStart, $days),
				'number' => $number,
				'total' => $maximum,
				'condition' => 'safety_pending',
			];

			if ($gateStart === null)
			{
				$action['relative_safety_days'] = $days;
			}

			$plan[] = $action;
		}

		$expiryDays = (int)($row['safety_response_window_days'] ?? 0)
			+ ((int)($row['safety_reminder_interval_days'] ?? 0) * $maximum);
		$expiryAt = $this->NullableTimestamp($row['safety_gate_deadline_at'] ?? null);

		if ($expiryAt === null && $gateStart !== null)
		{
			$expiryAt = $this->AddDaysToTimestamp($gateStart, $expiryDays);
		}

		$expiry = [
			'type' => 'safety_expiry',
			'at' => $expiryAt,
			'condition' => 'safety_unconfirmed',
		];
		$release = [
			'type' => 'recipient_release',
			'at' => $expiryAt,
			'condition' => 'safety_unconfirmed',
		];

		if ($gateStart === null)
		{
			$expiry['relative_safety_days'] = $expiryDays;
			$release['relative_safety_days'] = $expiryDays;
		}

		$plan[] = $expiry;
		$plan[] = $release;

		return $plan;
	}

	/**
	 * @brief Drops the due notice from a currently awaiting cycle because it has already been sent.
	 * @param array<int, array{type: string, at: string|null, number?: int, total?: int}> $plan Candidate plan.
	 * @param array<string, mixed> $row Current-cycle row.
	 * @return array<int, array{type: string, at: string|null, number?: int, total?: int}>
	 */
	private function RemovePastCompletedPlanItems(array $plan, array $row): array
	{
		if ((string)($row['cycle_status'] ?? '') !== 'awaiting' || empty($row['due_notice_sent_at']))
		{
			return $plan;
		}

		return array_values(array_filter(
			$plan,
			static fn (array $item): bool => (string)$item['type'] !== 'due_notice'
		));
	}

	/** @brief Calculates the owner-side escalation eligibility time. */
	private function OwnerEscalationTime(array $row): ?string
	{
		return $this->AddDaysToTimestamp(
			$this->NullableTimestamp($row['response_deadline_at'] ?? null),
			(int)($row['reminder_interval_days'] ?? 0) * (int)($row['max_reminders'] ?? 0)
		);
	}

	/** @brief Adds whole UTC days to a nullable database timestamp. */
	private function AddDaysToTimestamp(?string $timestamp, int $days): ?string
	{
		if ($timestamp === null)
		{
			return null;
		}

		$date = new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));

		if ($days > 0)
		{
			$date = $date->add(new DateInterval('P' . $days . 'D'));
		}

		return $date->format('Y-m-d H:i:s');
	}

	/** @brief Normalizes an optional database timestamp. */
	private function NullableTimestamp(mixed $value): ?string
	{
		if (!is_string($value) || trim($value) === '')
		{
			return null;
		}

		return $value;
	}
}
