<?php

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
				check_interval_days,
				response_window_days,
				reminder_interval_days,
				max_reminders,
				is_paused,
				is_test_mode,
				last_confirmed_at,
				next_check_due_at,
				created_at,
				updated_at
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
				check_interval_days,
				response_window_days,
				reminder_interval_days,
				max_reminders,
				is_paused,
				is_test_mode,
				last_confirmed_at,
				next_check_due_at,
				created_at,
				updated_at
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
	 * @brief Creates a monitor for a user.
	 * @param int $userId User ID.
	 * @param string $name Monitor name.
	 * @param string|null $description Optional description.
	 * @param int $checkIntervalDays Days between check-ins.
	 * @param int $responseWindowDays Days allowed for response.
	 * @param int $reminderIntervalDays Days between reminders.
	 * @param int $maxReminders Maximum number of reminders.
	 * @param bool $isPaused Whether the monitor is paused.
	 * @param bool $isTestMode Whether the monitor is in test mode.
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
		bool $isTestMode
	): void
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
				is_test_mode,
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
				:is_test_mode,
				NOW(),
				NOW()
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
			'is_test_mode' => $isTestMode ? 1 : 0,
		]);
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
	 * @param bool $isTestMode Whether the monitor is in test mode.
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
		bool $isPaused,
		bool $isTestMode
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
				is_test_mode = :is_test_mode,
				updated_at = NOW()
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
			'is_test_mode' => $isTestMode ? 1 : 0,
		]);
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
}