<?php

/**
 * @file SystemStatusRepository.php
 * @brief Persistence for installation-wide runtime health timestamps.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use Pulse\Core\Database;

/**
 * @brief Stores operational state that is neither user data nor application configuration.
 */
final class SystemStatusRepository
{
	private Database $_database;

	/** @brief Constructs the repository. @param Database $database Database service. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Returns the UTC timestamp of the latest fully successful combined cron run.
	 * @return string|null UTC database timestamp or null when cron has never completed successfully.
	 */
	public function LastSuccessfulCronRun(): ?string
	{
		$statement = $this->_database->GetConnection()->query('
			SELECT last_successful_cron_at
			FROM system_status
			WHERE id = 1
			LIMIT 1
		');
		$value = $statement->fetchColumn();

		return is_string($value) && $value !== '' ? $value : null;
	}

	/** @brief Records completion of one successful combined scheduler/queue-worker cron run. */
	public function RecordSuccessfulCronRun(): void
	{
		$statement = $this->_database->GetConnection()->prepare('
			INSERT INTO system_status
			(
				id,
				last_successful_cron_at,
				updated_at
			)
			VALUES
			(
				1,
				UTC_TIMESTAMP(),
				UTC_TIMESTAMP()
			)
			ON DUPLICATE KEY UPDATE
				last_successful_cron_at = UTC_TIMESTAMP(),
				updated_at = UTC_TIMESTAMP()
		');
		$statement->execute();
	}
}
