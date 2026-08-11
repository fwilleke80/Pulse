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
				is_paused,
				last_confirmed_at,
				next_check_due_at,
				created_at,
				updated_at,
				(
					SELECT cc.status
					FROM check_cycles cc
					WHERE cc.monitor_id = monitors.id
					  AND (monitors.last_confirmed_at IS NULL OR cc.started_at > monitors.last_confirmed_at)
					ORDER BY cc.started_at DESC, cc.id DESC
					LIMIT 1
				) AS latest_cycle_status,
				(
					SELECT COUNT(*)
					FROM monitor_contacts warning_mc
					INNER JOIN contacts warning_c ON warning_c.id = warning_mc.contact_id
					WHERE warning_mc.monitor_id = monitors.id
					  AND warning_c.email_checked_at IS NULL
				) AS unchecked_contact_count
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
				is_paused,
				last_confirmed_at,
				next_check_due_at,
				created_at,
				updated_at,
				(
					SELECT cc.status
					FROM check_cycles cc
					WHERE cc.monitor_id = monitors.id
					  AND (monitors.last_confirmed_at IS NULL OR cc.started_at > monitors.last_confirmed_at)
					ORDER BY cc.started_at DESC, cc.id DESC
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
	 * @param bool $isPaused Whether the monitor is paused.
	 */
	public function CreateForUser(
		int $userId,
		string $name,
		?string $description,
		int $checkIntervalDays,
		int $responseWindowDays,
		int $reminderIntervalDays,
		int $maxReminders,
		bool $isPaused,
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
			'is_paused' => $isPaused ? 1 : 0,
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
	 * @param bool $isPaused Whether the monitor is paused.
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
		bool $isPaused
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
				is_paused = :is_paused,
				next_check_due_at = TIMESTAMPADD(DAY, :next_check_interval_days, COALESCE(last_confirmed_at, UTC_TIMESTAMP())),
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
			'is_paused' => $isPaused ? 1 : 0,
			'next_check_interval_days' => $checkIntervalDays,
		]);
	}

	/**
	 * @brief Confirms a monitor when it is due and schedules its next check.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 * @return bool True when the monitor was due and confirmed.
	 */
	public function ConfirmDueForUser(int $monitorId, int $userId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('
			UPDATE monitors
			SET
				last_confirmed_at = UTC_TIMESTAMP(),
				next_check_due_at = TIMESTAMPADD(DAY, check_interval_days, UTC_TIMESTAMP()),
				updated_at = UTC_TIMESTAMP()
			WHERE id = :id
			  AND user_id = :user_id
			  AND is_paused = 0
			  AND (next_check_due_at IS NULL OR next_check_due_at <= UTC_TIMESTAMP())
		');
		$statement->execute([
			'id' => $monitorId,
			'user_id' => $userId,
		]);

		return $statement->rowCount() === 1;
	}

	/**
	 * @brief Forces a monitor due for development testing.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 * @return bool True when an owned monitor was updated.
	 */
	public function ForceDueForUser(int $monitorId, int $userId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('
			UPDATE monitors
			SET next_check_due_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
			WHERE id = :id AND user_id = :user_id
		');
		$statement->execute([
			'id' => $monitorId,
			'user_id' => $userId,
		]);

		return $statement->rowCount() === 1;
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
				c.email_checked_at,
				c.cell_phone,
				c.notes
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
