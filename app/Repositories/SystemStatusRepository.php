<?php

/**
 * @file SystemStatusRepository.php
 * @brief Persistence for installation-wide runtime health timestamps and bounded cron diagnostics.
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
	private const MAXIMUM_CRON_FAILURES = 50;

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

	/**
	 * @brief Returns when the persisted web-cron token was last changed through Administration.
	 * @return string|null UTC database timestamp or null when no change has been recorded yet.
	 */
	public function CronTokenChangedAt(): ?string
	{
		$statement = $this->_database->GetConnection()->query('
			SELECT cron_token_changed_at
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

	/** @brief Records that the persisted web-cron token changed through Administration. */
	public function RecordCronTokenChanged(): void
	{
		$statement = $this->_database->GetConnection()->prepare('
			INSERT INTO system_status
			(
				id,
				cron_token_changed_at,
				updated_at
			)
			VALUES
			(
				1,
				UTC_TIMESTAMP(),
				UTC_TIMESTAMP()
			)
			ON DUPLICATE KEY UPDATE
				cron_token_changed_at = UTC_TIMESTAMP(),
				updated_at = UTC_TIMESTAMP()
		');
		$statement->execute();
	}

	/**
	 * @brief Records one request that did not complete successfully and keeps only a bounded recent history.
	 * @param string $failureCode Stable diagnostic failure code.
	 * @param string $requestMethod HTTP request method.
	 * @param string|null $providedToken Supplied token excerpt, or null when no usable scalar token was supplied.
	 * @param bool $tokenTruncated Whether the stored token was truncated for bounded diagnostics.
	 */
	public function RecordFailedCronCall(string $failureCode, string $requestMethod, ?string $providedToken, bool $tokenTruncated): void
	{
		$connection = $this->_database->GetConnection();
		$statement = $connection->prepare('
			INSERT INTO cron_failures
			(
				attempted_at,
				failure_code,
				request_method,
				provided_token,
				token_truncated
			)
			VALUES
			(
				UTC_TIMESTAMP(),
				:failure_code,
				:request_method,
				:provided_token,
				:token_truncated
			)
		');
		$statement->execute([
			'failure_code' => substr($failureCode, 0, 32),
			'request_method' => substr($requestMethod, 0, 16),
			'provided_token' => $providedToken,
			'token_truncated' => $tokenTruncated ? 1 : 0,
		]);

		$connection->exec('
			DELETE FROM cron_failures
			WHERE id < COALESCE
			(
				(
					SELECT cutoff_id
					FROM
					(
						SELECT id AS cutoff_id
						FROM cron_failures
						ORDER BY id DESC
						LIMIT 1 OFFSET ' . (self::MAXIMUM_CRON_FAILURES - 1) . '
					) AS recent_cutoff
				),
				0
			)
		');
	}

	/**
	 * @brief Returns the latest unsuccessful web-cron calls.
	 * @param int $limit Maximum number of rows to return.
	 * @return array<int, array<string, mixed>> Failures in newest-first order.
	 */
	public function RecentFailedCronCalls(int $limit = 20): array
	{
		$limit = max(1, min(self::MAXIMUM_CRON_FAILURES, $limit));
		$statement = $this->_database->GetConnection()->query('
			SELECT
				id,
				attempted_at,
				failure_code,
				request_method,
				provided_token,
				token_truncated
			FROM cron_failures
			ORDER BY id DESC
			LIMIT ' . $limit . '
		');
		$rows = $statement->fetchAll();

		return is_array($rows) ? $rows : [];
	}
}
