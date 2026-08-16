<?php

/**
 * @file TotpCredentialRepository.php
 * @brief Persistence for optional TOTP credentials, replay counters, and recovery codes.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;
use RuntimeException;
use Throwable;

/**
 * @brief Stores one enabled authenticator method per account on the generic security-method layer.
 */
final class TotpCredentialRepository
{
	public const METHOD_TOTP = 'totp';

	private Database $_database;

	/** @brief Constructs the repository. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/** @return array<string, mixed>|null @brief Returns the enabled TOTP credential and recovery status for an account. */
	public function FindForUser(int $userId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT
				usm.id AS security_method_id,
				usm.created_at,
				usm.last_used_at,
				utc.secret_ciphertext,
				utc.last_used_counter,
				utc.enabled_at,
				(
					SELECT COUNT(*)
					FROM user_totp_recovery_codes utrc
					WHERE utrc.security_method_id = usm.id AND utrc.used_at IS NULL
				) AS recovery_codes_remaining
			FROM user_security_methods usm
			INNER JOIN user_totp_credentials utc ON utc.security_method_id = usm.id
			WHERE usm.user_id = :user_id AND usm.method = :method
			ORDER BY usm.id DESC
			LIMIT 1
		');
		$statement->execute(['user_id' => $userId, 'method' => self::METHOD_TOTP]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/** @brief Returns whether an account currently requires TOTP after password verification. */
	public function IsEnabled(int $userId): bool
	{
		return $this->FindForUser($userId) !== null;
	}

	/**
	 * @brief Replaces any existing TOTP method with one confirmed credential and fresh recovery codes.
	 * @param array<int, string> $recoveryCodeHashes Keyed hashes of newly issued recovery codes.
	 */
	public function Enable(int $userId, string $secretCiphertext, array $recoveryCodeHashes): int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$this->LockUser($connection, $userId);
			$delete = $connection->prepare('DELETE FROM user_security_methods WHERE user_id = :user_id AND method = :method');
			$delete->execute(['user_id' => $userId, 'method' => self::METHOD_TOTP]);

			$method = $connection->prepare('
				INSERT INTO user_security_methods (user_id, method, label)
				VALUES (:user_id, :method, :label)
			');
			$method->execute([
				'user_id' => $userId,
				'method' => self::METHOD_TOTP,
				'label' => 'Authenticator app',
			]);
			$methodId = (int)$connection->lastInsertId();

			$credential = $connection->prepare('
				INSERT INTO user_totp_credentials
					(security_method_id, secret_ciphertext, enabled_at)
				VALUES
					(:security_method_id, :secret_ciphertext, UTC_TIMESTAMP())
			');
			$credential->execute([
				'security_method_id' => $methodId,
				'secret_ciphertext' => $secretCiphertext,
			]);
			$this->InsertRecoveryCodes($connection, $methodId, $recoveryCodeHashes);
			$this->InsertAudit($connection, $userId, 'security.totp_enabled', 'TOTP two-factor authentication enabled.');
			$connection->commit();
			return $methodId;
		}
		catch (Throwable $throwable)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}

			throw $throwable;
		}
	}

	/** @brief Consumes a newer TOTP counter exactly once and updates method usage metadata. */
	public function ConsumeCounter(int $userId, int $counter): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$normalizedCounter = max(0, $counter);
			$consume = $connection->prepare('
				UPDATE user_totp_credentials utc
				INNER JOIN user_security_methods usm ON usm.id = utc.security_method_id
				SET utc.last_used_counter = :counter
				WHERE usm.user_id = :user_id
					AND usm.method = :method
					AND (utc.last_used_counter IS NULL OR utc.last_used_counter < :counter_guard)
			');
			$consume->execute([
				'counter' => $normalizedCounter,
				'user_id' => $userId,
				'method' => self::METHOD_TOTP,
				'counter_guard' => $normalizedCounter,
			]);

			if ($consume->rowCount() !== 1)
			{
				$connection->rollBack();
				return false;
			}

			$used = $connection->prepare('
				UPDATE user_security_methods
				SET last_used_at = UTC_TIMESTAMP()
				WHERE user_id = :user_id AND method = :method
			');
			$used->execute([
				'user_id' => $userId,
				'method' => self::METHOD_TOTP,
			]);
			$connection->commit();
			return true;
		}
		catch (Throwable $throwable)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}

			throw $throwable;
		}
	}

	/** @brief Atomically consumes one unused recovery-code hash for an account. */
	public function ConsumeRecoveryCode(int $userId, string $codeHash): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$lookup = $connection->prepare('
				SELECT utrc.id, usm.id AS security_method_id
				FROM user_totp_recovery_codes utrc
				INNER JOIN user_security_methods usm ON usm.id = utrc.security_method_id
				WHERE usm.user_id = :user_id
					AND usm.method = :method
					AND utrc.code_hash = :code_hash
					AND utrc.used_at IS NULL
				LIMIT 1
				FOR UPDATE
			');
			$lookup->execute([
				'user_id' => $userId,
				'method' => self::METHOD_TOTP,
				'code_hash' => $codeHash,
			]);
			$row = $lookup->fetch(PDO::FETCH_ASSOC);

			if (!is_array($row))
			{
				$connection->rollBack();
				return false;
			}

			$consume = $connection->prepare('UPDATE user_totp_recovery_codes SET used_at = UTC_TIMESTAMP() WHERE id = :id AND used_at IS NULL');
			$consume->execute(['id' => (int)$row['id']]);

			if ($consume->rowCount() !== 1)
			{
				$connection->rollBack();
				return false;
			}

			$used = $connection->prepare('UPDATE user_security_methods SET last_used_at = UTC_TIMESTAMP() WHERE id = :id');
			$used->execute(['id' => (int)$row['security_method_id']]);
			$this->InsertAudit($connection, $userId, 'security.totp_recovery_code_used', 'A TOTP recovery code was used.');
			$connection->commit();
			return true;
		}
		catch (Throwable $throwable)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}

			throw $throwable;
		}
	}

	/** @param array<int, string> $recoveryCodeHashes @brief Replaces all recovery codes after deliberate re-authentication. */
	public function ReplaceRecoveryCodes(int $userId, array $recoveryCodeHashes): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$method = $this->LockMethod($connection, $userId);
			$delete = $connection->prepare('DELETE FROM user_totp_recovery_codes WHERE security_method_id = :security_method_id');
			$delete->execute(['security_method_id' => (int)$method['id']]);
			$this->InsertRecoveryCodes($connection, (int)$method['id'], $recoveryCodeHashes);
			$this->InsertAudit($connection, $userId, 'security.totp_recovery_codes_regenerated', 'TOTP recovery codes regenerated.');
			$connection->commit();
		}
		catch (Throwable $throwable)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}

			throw $throwable;
		}
	}

	/** @brief Disables TOTP and deletes its encrypted secret and recovery-code hashes. */
	public function Disable(int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$this->LockUser($connection, $userId);
			$delete = $connection->prepare('DELETE FROM user_security_methods WHERE user_id = :user_id AND method = :method');
			$delete->execute(['user_id' => $userId, 'method' => self::METHOD_TOTP]);
			$removed = $delete->rowCount() > 0;

			if ($removed)
			{
				$this->InsertAudit($connection, $userId, 'security.totp_disabled', 'TOTP two-factor authentication disabled.');
			}

			$connection->commit();
			return $removed;
		}
		catch (Throwable $throwable)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}

			throw $throwable;
		}
	}

	/** @brief Records a successful TOTP-backed authentication event without secret material. */
	public function RecordAuthentication(int $userId, string $eventType, string $message): void
	{
		$this->InsertAudit($this->_database->GetConnection(), $userId, $eventType, $message);
	}

	/** @brief Locks an existing account so parallel security-method changes serialize. */
	private function LockUser(PDO $connection, int $userId): void
	{
		$statement = $connection->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
		$statement->execute(['id' => $userId]);

		if ($statement->fetchColumn() === false)
		{
			throw new RuntimeException('The account is unavailable.');
		}
	}

	/** @return array<string, mixed> @brief Locks and returns the enabled TOTP method. */
	private function LockMethod(PDO $connection, int $userId): array
	{
		$statement = $connection->prepare('
			SELECT usm.id
			FROM user_security_methods usm
			INNER JOIN user_totp_credentials utc ON utc.security_method_id = usm.id
			WHERE usm.user_id = :user_id AND usm.method = :method
			LIMIT 1
			FOR UPDATE
		');
		$statement->execute(['user_id' => $userId, 'method' => self::METHOD_TOTP]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		if (!is_array($row))
		{
			throw new RuntimeException('TOTP is not enabled for this account.');
		}

		return $row;
	}

	/** @param array<int, string> $hashes @brief Inserts the fixed set of unused recovery-code hashes. */
	private function InsertRecoveryCodes(PDO $connection, int $methodId, array $hashes): void
	{
		$insert = $connection->prepare('
			INSERT INTO user_totp_recovery_codes (security_method_id, code_hash)
			VALUES (:security_method_id, :code_hash)
		');

		foreach (array_values(array_unique($hashes)) as $hash)
		{
			if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1)
			{
				throw new RuntimeException('A recovery-code hash is invalid.');
			}

			$insert->execute(['security_method_id' => $methodId, 'code_hash' => $hash]);
		}
	}

	/** @brief Inserts one user-scoped security audit event. */
	private function InsertAudit(PDO $connection, int $userId, string $eventType, string $message): void
	{
		$statement = $connection->prepare('
			INSERT INTO audit_log (user_id, event_type, entity_type, entity_id, message, context_json)
			VALUES (:user_id, :event_type, :entity_type, :entity_id, :message, NULL)
		');
		$statement->execute([
			'user_id' => $userId,
			'event_type' => $eventType,
			'entity_type' => 'user',
			'entity_id' => $userId,
			'message' => $message,
		]);
	}
}
