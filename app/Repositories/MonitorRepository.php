<?php

/**
 * @file MonitorRepository.php
 * @brief Repository for user monitors.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;

/**
 * @brief Repository for user monitors.
 */
class MonitorRepository
{
	private Database $_database;

	/**
	 * @brief Constructs the repository.
	 * @param Database $database Database service.
	 */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Returns all monitors for a user.
	 * @param int $userId User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindAllByUserId(int $userId): array
	{
		$sql = '
			SELECT
				id,
				name,
				description,
				default_message_subject,
				default_message_body,
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
				safety_invitation_subject,
				safety_invitation_body,
				safety_reminder_subject,
				safety_reminder_body,
				recipient_portal_expiry_days,
				is_paused,
				paused_at,
				last_confirmed_at,
				last_safety_confirmed_at,
				last_safety_contact_id,
				next_check_due_at,
				created_at,
				updated_at,
				(
					SELECT cc.status
					FROM check_cycles cc
					WHERE cc.monitor_id = monitors.id
					  AND cc.status IN (\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\')
					ORDER BY cc.id DESC
					LIMIT 1
				) AS latest_cycle_status,
				(
					SELECT cc.due_notice_sent_at
					FROM check_cycles cc
					WHERE cc.monitor_id = monitors.id
					  AND cc.status IN (\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\')
					ORDER BY cc.id DESC
					LIMIT 1
				) AS due_notice_sent_at,
				(
					SELECT mq.status
					FROM mail_queue mq
					INNER JOIN check_cycles due_cc ON due_cc.id = mq.check_cycle_id
					WHERE due_cc.monitor_id = monitors.id
					  AND due_cc.status IN (\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\')
					  AND mq.mail_type = \'owner_due_notice\'
					ORDER BY mq.id DESC
					LIMIT 1
				) AS due_notice_queue_status,
				(
					SELECT cc.escalation_policy_snapshot
					FROM check_cycles cc
					WHERE cc.monitor_id = monitors.id
					  AND cc.status IN (\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\')
					ORDER BY cc.id DESC
					LIMIT 1
				) AS latest_escalation_policy,
				(
					SELECT COUNT(*)
					FROM monitor_contacts warning_mc
					INNER JOIN contacts warning_c ON warning_c.id = warning_mc.contact_id
					WHERE warning_mc.monitor_id = monitors.id
					  AND warning_c.email_checked_at IS NULL
				) AS unchecked_contact_count,
				(
					SELECT COUNT(*)
					FROM mail_queue failed_mq
					INNER JOIN check_cycles failed_cc ON failed_cc.id = failed_mq.check_cycle_id
					WHERE failed_cc.monitor_id = monitors.id
					  AND failed_cc.status IN (\'awaiting\', \'safety_pending\', \'overdue\', \'escalated\')
					  AND failed_mq.mail_type IN (\'owner_due_notice\', \'owner_reminder\', \'safety_invitation\', \'safety_reminder\', \'recipient_notification\')
					  AND failed_mq.status = \'failed\'
				) AS failed_notification_count,
				(
					SELECT rr.status
					FROM recipient_releases rr
					WHERE rr.monitor_id = monitors.id
					ORDER BY rr.id DESC
					LIMIT 1
				) AS latest_release_status,
				(
					SELECT rr.blocked_reason
					FROM recipient_releases rr
					WHERE rr.monitor_id = monitors.id
					ORDER BY rr.id DESC
					LIMIT 1
				) AS latest_release_blocked_reason
			FROM monitors
			WHERE user_id = :user_id
			ORDER BY name ASC
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'user_id' => $userId,
		]);

		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Finds a monitor by ID for a specific user.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 * @return array<string, mixed>|null Monitor row or null.
	 */
	public function FindByIdForUser(int $monitorId, int $userId): ?array
	{
		$sql = '
			SELECT
				id,
				name,
				description,
				default_message_subject,
				default_message_body,
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
				safety_invitation_subject,
				safety_invitation_body,
				safety_reminder_subject,
				safety_reminder_body,
				recipient_portal_expiry_days,
				is_paused,
				paused_at,
				last_confirmed_at,
				last_safety_confirmed_at,
				last_safety_contact_id,
				next_check_due_at,
				created_at,
				updated_at,
				(
					SELECT cc.status
					FROM check_cycles cc
					WHERE cc.monitor_id = monitors.id
					  AND cc.status IN (\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\')
					ORDER BY cc.id DESC
					LIMIT 1
				) AS latest_cycle_status
			FROM monitors
			WHERE id = :id
			  AND user_id = :user_id
			LIMIT 1
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $monitorId,
			'user_id' => $userId,
		]);

		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Returns the assigned contact IDs for a monitor.
	 * @param int $monitorId Monitor ID.
	 * @return array<int>
	 */
	public function FindContactIdsByMonitorId(int $monitorId): array
	{
		$sql = '
			SELECT contact_id
			FROM monitor_contacts
			WHERE monitor_id = :monitor_id
			ORDER BY sort_order ASC, id ASC
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'monitor_id' => $monitorId,
		]);

		$rows = $statement->fetchAll(PDO::FETCH_COLUMN);

		if (!is_array($rows))
		{
			return [];
		}

		return array_map('intval', $rows);
	}

	/**
	 * @brief Creates a monitor for a user.
	 * @param int $userId User ID.
	 * @param string $name Monitor name.
	 * @param string|null $description Optional description.
	 * @param int $checkIntervalDays Days between check-ins.
	 * @param int $responseWindowDays Days allowed for response.
	 * @param int $reminderIntervalDays Days between reminders.
	 * @param int $maxReminders Maximum number of reminders.
	 */
	public function CreateForUser(
		int $userId,
		string $name,
		?string $description,
		int $checkIntervalDays,
		int $responseWindowDays,
		int $reminderIntervalDays,
		int $maxReminders,
	): int
	{
		$sql = '
			INSERT INTO monitors
			(
				user_id,
				name,
				description,
				check_interval_days,
				response_window_days,
				reminder_interval_days,
				max_reminders,
				is_paused,
				last_confirmed_at,
				next_check_due_at,
				created_at,
				updated_at
			)
			VALUES
			(
				:user_id,
				:name,
				:description,
				:check_interval_days,
				:response_window_days,
				:reminder_interval_days,
				:max_reminders,
				:is_paused,
				UTC_TIMESTAMP(),
				TIMESTAMPADD(DAY, :next_check_interval_days, UTC_TIMESTAMP()),
				UTC_TIMESTAMP(),
				UTC_TIMESTAMP()
			)
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'user_id' => $userId,
			'name' => $name,
			'description' => $description,
			'check_interval_days' => $checkIntervalDays,
			'response_window_days' => $responseWindowDays,
			'reminder_interval_days' => $reminderIntervalDays,
			'max_reminders' => $maxReminders,
			'is_paused' => 0,
			'next_check_interval_days' => $checkIntervalDays,
		]);

		return (int)$this->_database->GetConnection()->lastInsertId();
	}

	/**
	 * @brief Synchronizes assigned contacts while preserving retained monitor_contact IDs.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @param array<int> $contactIds Contact IDs.
	 */
	public function ReplaceContactsForMonitor(int $monitorId, int $userId, array $contactIds): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$ownerStatement = $connection->prepare('
				SELECT id
				FROM monitors
				WHERE id = :monitor_id AND user_id = :user_id
				FOR UPDATE
			');
			$ownerStatement->execute(['monitor_id' => $monitorId, 'user_id' => $userId]);

			if ($ownerStatement->fetchColumn() === false)
			{
				throw new \RuntimeException('Owned monitor not found during contact synchronization.');
			}

			$allowedStatement = $connection->prepare('
				SELECT id
				FROM contacts
				WHERE user_id = :user_id
			');
			$allowedStatement->execute(['user_id' => $userId]);
			$allowedContactIds = array_map('intval', $allowedStatement->fetchAll(PDO::FETCH_COLUMN));
			$contactIds = array_values(array_filter(
				array_unique($contactIds),
				static fn (int $contactId): bool => in_array($contactId, $allowedContactIds, true)
			));

			$selectStatement = $connection->prepare('
				SELECT id, contact_id
				FROM monitor_contacts
				WHERE monitor_id = :monitor_id
				FOR UPDATE
			');
			$selectStatement->execute(['monitor_id' => $monitorId]);
			$rows = $selectStatement->fetchAll(PDO::FETCH_ASSOC);
			$existingByContactId = [];

			foreach (is_array($rows) ? $rows : [] as $row)
			{
				$existingByContactId[(int)$row['contact_id']] = (int)$row['id'];
			}

			$removedContactIds = array_diff(array_keys($existingByContactId), $contactIds);

			if ($removedContactIds !== [])
			{
				$placeholders = implode(',', array_fill(0, count($removedContactIds), '?'));
				$deleteStatement = $connection->prepare('
					DELETE FROM monitor_contacts
					WHERE monitor_id = ?
					  AND contact_id IN (' . $placeholders . ')
				');
				$deleteStatement->execute([$monitorId, ...array_values($removedContactIds)]);
			}

			$insertStatement = $connection->prepare('
				INSERT INTO monitor_contacts (monitor_id, contact_id, sort_order)
				VALUES (:monitor_id, :contact_id, :sort_order)
			');
			$updateStatement = $connection->prepare('
				UPDATE monitor_contacts
				SET sort_order = :sort_order
				WHERE id = :id
			');

			foreach (array_values($contactIds) as $index => $contactId)
			{
				$sortOrder = $index + 1;

				if (isset($existingByContactId[$contactId]))
				{
					$updateStatement->execute([
						'id' => $existingByContactId[$contactId],
						'sort_order' => $sortOrder,
					]);
					continue;
				}

				$insertStatement->execute([
					'monitor_id' => $monitorId,
					'contact_id' => $contactId,
					'sort_order' => $sortOrder,
				]);
			}

			$connection->commit();
		}
		catch (\Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}
	/**
	 * @brief Updates a monitor belonging to a user.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 * @param string $name Monitor name.
	 * @param string|null $description Optional description.
	 * @param int $checkIntervalDays Days between check-ins.
	 * @param int $responseWindowDays Days allowed for response.
	 * @param int $reminderIntervalDays Days between reminders.
	 * @param int $maxReminders Maximum number of reminders.
	 */
	public function UpdateForUser(
		int $monitorId,
		int $userId,
		string $name,
		?string $description,
		int $checkIntervalDays,
		int $responseWindowDays,
		int $reminderIntervalDays,
		int $maxReminders,
		string $escalationPolicy,
		int $safetyResponseWindowDays,
		int $safetyReminderIntervalDays,
		int $safetyMaxReminders,
		int $safetyRequiredConfirmations,
		?int $safetyConfirmationDays,
		?string $safetyInvitationSubject,
		?string $safetyInvitationBody,
		?string $safetyReminderSubject,
		?string $safetyReminderBody
	): void
	{
		$sql = '
			UPDATE monitors
			SET
				name = :name,
				description = :description,
				check_interval_days = :check_interval_days,
				response_window_days = :response_window_days,
				reminder_interval_days = :reminder_interval_days,
				max_reminders = :max_reminders,
				escalation_policy = :escalation_policy,
				safety_response_window_days = :safety_response_window_days,
				safety_reminder_interval_days = :safety_reminder_interval_days,
				safety_max_reminders = :safety_max_reminders,
				safety_required_confirmations = :safety_required_confirmations,
				safety_confirmation_days = :safety_confirmation_days,
				safety_invitation_subject = :safety_invitation_subject,
				safety_invitation_body = :safety_invitation_body,
				safety_reminder_subject = :safety_reminder_subject,
				safety_reminder_body = :safety_reminder_body,
				updated_at = UTC_TIMESTAMP()
			WHERE id = :id
			  AND user_id = :user_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $monitorId,
			'user_id' => $userId,
			'name' => $name,
			'description' => $description,
			'check_interval_days' => $checkIntervalDays,
			'response_window_days' => $responseWindowDays,
			'reminder_interval_days' => $reminderIntervalDays,
			'max_reminders' => $maxReminders,
			'escalation_policy' => $escalationPolicy,
			'safety_response_window_days' => $safetyResponseWindowDays,
			'safety_reminder_interval_days' => $safetyReminderIntervalDays,
			'safety_max_reminders' => $safetyMaxReminders,
			'safety_required_confirmations' => $safetyRequiredConfirmations,
			'safety_confirmation_days' => $safetyConfirmationDays,
			'safety_invitation_subject' => $safetyInvitationSubject,
			'safety_invitation_body' => $safetyInvitationBody,
			'safety_reminder_subject' => $safetyReminderSubject,
			'safety_reminder_body' => $safetyReminderBody,
		]);
	}

	/**
	 * @brief Updates the recipient portal availability policy for an owned monitor.
	 * @param int|null $expiryDays Days after successful recipient notification, or null for no automatic expiry.
	 */
	public function UpdateRecipientPortalAvailabilityForUser(int $monitorId, int $userId, ?int $expiryDays): void
	{
		$statement = $this->_database->GetConnection()->prepare('
			UPDATE monitors
			SET recipient_portal_expiry_days = :expiry_days, updated_at = UTC_TIMESTAMP()
			WHERE id = :id AND user_id = :user_id
		');
		$statement->execute([
			'expiry_days' => $expiryDays,
			'id' => $monitorId,
			'user_id' => $userId,
		]);
	}

	/**
	 * @brief Returns configured safety-contact IDs for an owned monitor.
	 * @return array<int>
	 */
	public function FindSafetyContactIdsByMonitorIdForUser(int $monitorId, int $userId): array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT msc.contact_id
			FROM monitor_safety_contacts msc
			INNER JOIN monitors m ON m.id = msc.monitor_id
			WHERE msc.monitor_id = :monitor_id AND m.user_id = :user_id
			ORDER BY msc.sort_order ASC, msc.id ASC
		');
		$statement->execute(['monitor_id' => $monitorId, 'user_id' => $userId]);
		$rows = $statement->fetchAll(PDO::FETCH_COLUMN);

		return is_array($rows) ? array_map('intval', $rows) : [];
	}

	/**
	 * @brief Replaces one monitor's optional safety-contact assignments.
	 * @param array<int> $contactIds Contact IDs owned by the monitor owner.
	 */
	public function ReplaceSafetyContactsForMonitor(int $monitorId, int $userId, array $contactIds): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$monitor = $connection->prepare('SELECT id FROM monitors WHERE id = :id AND user_id = :user_id FOR UPDATE');
			$monitor->execute(['id' => $monitorId, 'user_id' => $userId]);

			if ($monitor->fetchColumn() === false)
			{
				throw new \RuntimeException('Owned monitor not found during safety-contact synchronization.');
			}

			$allowed = $connection->prepare('SELECT id FROM contacts WHERE user_id = :user_id');
			$allowed->execute(['user_id' => $userId]);
			$allowedIds = array_map('intval', $allowed->fetchAll(PDO::FETCH_COLUMN));
			$contactIds = array_values(array_filter(
				array_unique(array_map('intval', $contactIds)),
				static fn (int $contactId): bool => in_array($contactId, $allowedIds, true)
			));

			$delete = $connection->prepare('DELETE FROM monitor_safety_contacts WHERE monitor_id = :monitor_id');
			$delete->execute(['monitor_id' => $monitorId]);
			$insert = $connection->prepare('
				INSERT INTO monitor_safety_contacts (monitor_id, contact_id, sort_order)
				VALUES (:monitor_id, :contact_id, :sort_order)
			');

			foreach ($contactIds as $index => $contactId)
			{
				$insert->execute([
					'monitor_id' => $monitorId,
					'contact_id' => $contactId,
					'sort_order' => $index + 1,
				]);
			}

			$connection->commit();
		}
		catch (\Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Deletes a monitor belonging to a user.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 */
	public function DeleteForUser(int $monitorId, int $userId): void
	{
		$sql = '
			DELETE FROM monitors
			WHERE id = :id
			  AND user_id = :user_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $monitorId,
			'user_id' => $userId,
		]);
	}

	/**
	 * @brief Returns the number of monitors for a user.
	 * @param int $userId User ID.
	 * @return int
	 */
	public function CountByUserId(int $userId): int
	{
		$sql = '
			SELECT COUNT(*)
			FROM monitors
			WHERE user_id = :user_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'user_id' => $userId,
		]);

		$value = $statement->fetchColumn();

		return is_numeric($value) ? (int)$value : 0;
	}

	/**
	 * @brief Returns all monitor-contact assignments for a monitor owned by a user.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindMonitorContactsByMonitorIdForUser(int $monitorId, int $userId): array
	{
		$sql = '
			SELECT
				mc.id,
				mc.monitor_id,
				mc.contact_id,
				mc.sort_order,
				c.name,
				c.email,
				c.notification_locale,
				c.email_checked_at,
				c.cell_phone,
				c.notes,
				(
					SELECT COUNT(*)
					FROM document_monitor_contacts dmc
					WHERE dmc.monitor_contact_id = mc.id
				) AS document_count,
				(
					SELECT rrd.status
					FROM recipient_release_deliveries rrd
					WHERE rrd.monitor_id = mc.monitor_id AND rrd.contact_id = mc.contact_id
					ORDER BY rrd.id DESC
					LIMIT 1
				) AS latest_delivery_status
			FROM monitor_contacts mc
			INNER JOIN monitors m
				ON m.id = mc.monitor_id
			INNER JOIN contacts c
				ON c.id = mc.contact_id
			WHERE mc.monitor_id = :monitor_id
			  AND m.user_id = :user_id
			ORDER BY mc.sort_order ASC, c.name ASC
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'monitor_id' => $monitorId,
			'user_id' => $userId,
		]);

		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		return is_array($rows) ? $rows : [];
	}
}
