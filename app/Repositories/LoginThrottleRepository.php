<?php

/**
 * @file LoginThrottleRepository.php
 * @brief Persistence for privacy-preserving login throttling.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Pulse\Core\Database;

/**
 * @brief Stores only opaque hashes of login-throttle subjects.
 */
class LoginThrottleRepository
{
	private Database $_database;

	/** @brief Constructs the repository. @param Database $database Database service. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Returns whether a throttle key is currently blocked.
	 * @param string $attemptKey Opaque SHA-256 key.
	 * @return bool
	 */
	public function IsBlocked(string $attemptKey): bool
	{
		$sql = '
			SELECT 1
			FROM login_attempts
			WHERE attempt_key = :attempt_key
			  AND blocked_until > UTC_TIMESTAMP()
			LIMIT 1
		';
		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute(['attempt_key' => $attemptKey]);

		return $statement->fetchColumn() !== false;
	}

	/**
	 * @brief Records a failure and blocks the key once the configured threshold is reached.
	 * @param string $attemptKey Opaque SHA-256 key.
	 * @param int $maximumAttempts Maximum attempts per window.
	 * @param int $windowSeconds Attempt window length.
	 * @param int $blockSeconds Block duration.
	 */
	public function RecordFailure(
		string $attemptKey,
		int $maximumAttempts,
		int $windowSeconds,
		int $blockSeconds
	): void
	{
		$this->RecordFailureAttempt($attemptKey, $maximumAttempts, $windowSeconds, $blockSeconds, true);
	}

	/**
	 * @brief Removes accumulated failures after successful authentication.
	 * @param string $attemptKey Opaque SHA-256 key.
	 */
	public function Clear(string $attemptKey): void
	{
		$statement = $this->_database->GetConnection()->prepare('
			DELETE FROM login_attempts
			WHERE attempt_key = :attempt_key
		');
		$statement->execute(['attempt_key' => $attemptKey]);
	}

	/**
	 * @brief Performs one transactionally locked failure update.
	 * @param string $attemptKey Opaque SHA-256 key.
	 * @param int $maximumAttempts Maximum attempts per window.
	 * @param int $windowSeconds Attempt window length.
	 * @param int $blockSeconds Block duration.
	 * @param bool $retryOnDuplicate Whether one insert race may be retried.
	 */
	private function RecordFailureAttempt(
		string $attemptKey,
		int $maximumAttempts,
		int $windowSeconds,
		int $blockSeconds,
		bool $retryOnDuplicate
	): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$statement = $connection->prepare('
				SELECT attempts, window_started_at
				FROM login_attempts
				WHERE attempt_key = :attempt_key
				FOR UPDATE
			');
			$statement->execute(['attempt_key' => $attemptKey]);
			$row = $statement->fetch(PDO::FETCH_ASSOC);

			$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
			$attempts = 1;
			$windowStartedAt = $now;

			if (is_array($row))
			{
				$existingStart = new DateTimeImmutable((string)$row['window_started_at'], new DateTimeZone('UTC'));

				if (($now->getTimestamp() - $existingStart->getTimestamp()) < $windowSeconds)
				{
					$attempts = (int)$row['attempts'] + 1;
					$windowStartedAt = $existingStart;
				}
			}

			$blockedUntil = $attempts >= $maximumAttempts
				? $now->modify('+' . $blockSeconds . ' seconds')->format('Y-m-d H:i:s')
				: null;

			if (is_array($row))
			{
				$writeStatement = $connection->prepare('
					UPDATE login_attempts
					SET
						attempts = :attempts,
						window_started_at = :window_started_at,
						blocked_until = :blocked_until,
						updated_at = UTC_TIMESTAMP()
					WHERE attempt_key = :attempt_key
				');
			}
			else
			{
				$writeStatement = $connection->prepare('
					INSERT INTO login_attempts
					(
						attempt_key,
						attempts,
						window_started_at,
						blocked_until,
						updated_at
					)
					VALUES
					(
						:attempt_key,
						:attempts,
						:window_started_at,
						:blocked_until,
						UTC_TIMESTAMP()
					)
				');
			}

			$writeStatement->execute([
				'attempt_key' => $attemptKey,
				'attempts' => $attempts,
				'window_started_at' => $windowStartedAt->format('Y-m-d H:i:s'),
				'blocked_until' => $blockedUntil,
			]);

			$connection->commit();
		}
		catch (PDOException $exception)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}

			if ($retryOnDuplicate && $exception->getCode() === '23000')
			{
				$this->RecordFailureAttempt($attemptKey, $maximumAttempts, $windowSeconds, $blockSeconds, false);
				return;
			}

			throw $exception;
		}
		catch (\Throwable $throwable)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}

			throw $throwable;
		}
	}
}
