<?php

/** @file AccountRepository.php @brief Transactional multiuser account persistence. */
declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;
use RuntimeException;
use Throwable;

class AccountRepository
{
	/** @brief Constructs this account component. */
	public function __construct(private Database $_database)
	{
	}

	/** @brief All lifecycle writes, audit records and queued mail share one transaction. */
	public function Transaction(callable $operation): mixed
	{
		$connection = $this->_database->GetConnection();
		if ($connection->inTransaction())
		{
			throw new RuntimeException('Account operations require their own transaction.');
		}
		$connection->beginTransaction();
		try
		{
			if ($this->Execute('SELECT id FROM account_write_lock WHERE id = 1 FOR UPDATE')->fetchColumn() === false)
			{
				throw new RuntimeException('Account write lock is missing; apply migration 011.');
			}
			$result = $operation();
			$connection->commit();
			return $result;
		}
		catch (Throwable $exception)
		{
			if ($connection->inTransaction())
			{
				$connection->rollBack();
			}
			throw $exception;
		}
	}

	/** @brief Find by id. */
	public function FindById(int $id): ?array
	{
		$lock = $this->_database->GetConnection()->inTransaction() ? ' FOR UPDATE' : '';
		$row = $this->Execute('SELECT * FROM users WHERE id = ?' . $lock, [$id])->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/** @brief List all. */
	public function ListAll(): array
	{
		return $this->Execute('SELECT * FROM users ORDER BY created_at, id')->fetchAll(PDO::FETCH_ASSOC);
	}

	/** @brief Email exists. */
	public function EmailExists(string $email, int $excludeId = 0): bool
	{
		return $this->Execute('SELECT id FROM users WHERE id <> ? AND (email = ? OR email_2 = ? OR email_3 = ? OR email_4 = ?) LIMIT 1', [$excludeId, $email, $email, $email, $email])->fetchColumn() !== false;
	}

	/** @brief Create. */
	public function Create(array $fields): int
	{
		$fields = $this->WritableFields($fields);
		$columns = implode(', ', array_keys($fields));
		$this->Execute('INSERT INTO users (' . $columns . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')', array_values($fields));
		return (int)$this->_database->GetConnection()->lastInsertId();
	}

	/** @brief Update. */
	public function Update(int $id, array $fields): void
	{
		$fields = $this->WritableFields($fields);
		if ($fields === [])
		{
			return;
		}
		$assignments = array_map(static fn (string $field): string => $field . ' = ?', array_keys($fields));
		$this->Execute('UPDATE users SET ' . implode(', ', $assignments) . ' WHERE id = ?', [...array_values($fields), $id]);
	}

	/** @brief Delete. */
	public function Delete(int $id): void
	{
		$this->Execute("UPDATE mail_queue SET status = 'cancelled', body_text = '[Account deleted]', cancelled_at = UTC_TIMESTAMP() WHERE user_id = ? AND status IN ('queued','retrying','processing')", [$id]);
		$this->Execute('DELETE FROM recipient_releases WHERE user_id = ?', [$id]);
		$this->Execute('DELETE FROM monitors WHERE user_id = ?', [$id]);
		$this->Execute('DELETE FROM mail_log WHERE user_id = ?', [$id]);
		$this->Execute('DELETE FROM mail_queue WHERE user_id = ?', [$id]);
		$this->Execute('DELETE FROM users WHERE id = ? AND is_super_user = 0', [$id]);
	}

	/** @brief Revoke passkeys. */
	public function RevokePasskeys(int $id): void
	{
		$this->Execute("DELETE FROM user_security_methods WHERE user_id = ? AND method = 'passkey'", [$id]);
	}

	/** @brief Reset totp. */
	public function ResetTotp(int $id): void
	{
		// Credentials and recovery codes cascade from the method row.
		$this->Execute("DELETE FROM user_security_methods WHERE user_id = ? AND method = 'totp'", [$id]);
	}

	/** @brief Revoke tokens. */
	public function RevokeTokens(int $id, ?string $purpose = null): void
	{
		$sql = 'UPDATE account_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL';
		$this->Execute($sql . ($purpose === null ? '' : ' AND purpose = ?'), $purpose === null ? [$id] : [$id, $purpose]);
		// Cancel superseded links, including mail already claimed by a worker.
		$sql = "UPDATE mail_queue SET status = 'cancelled', body_text = '[Account link revoked]', cancelled_at = UTC_TIMESTAMP() WHERE user_id = ? AND status IN ('queued','retrying','processing') AND ";
		$this->Execute($sql . ($purpose === null ? "mail_type IN ('account_verify','account_invite','account_password_reset')" : 'mail_type = ?'), $purpose === null ? [$id] : [$id, 'account_' . $purpose]);
	}

	/** @brief Create token. */
	public function CreateToken(int $id, string $purpose, string $hash, string $email, int $ttl): void
	{
		$this->Execute('INSERT INTO account_tokens (user_id, purpose, token_hash, email, expires_at) VALUES (?, ?, ?, ?, TIMESTAMPADD(SECOND, ?, UTC_TIMESTAMP()))', [$id, $purpose, $hash, $email, $ttl]);
	}

	/** @brief Find token. */
	public function FindToken(string $hash): ?array
	{
		$row = $this->Execute('SELECT * FROM account_tokens WHERE token_hash = ? AND used_at IS NULL AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() FOR UPDATE', [$hash])->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/** @brief Consume token. */
	public function ConsumeToken(int $id): void
	{
		$statement = $this->Execute('UPDATE account_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ? AND used_at IS NULL AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()', [$id]);
		if ($statement->rowCount() !== 1)
		{
			throw new RuntimeException('Invalid or expired account link.');
		}
	}

	/** @brief Inserts into the existing queue without opening a nested transaction. */
	public function QueueMail(int $id, string $purpose, string $email, string $body, string $key): int
	{
		$this->Execute("INSERT INTO mail_queue (user_id, mail_type, idempotency_key, recipient_email, subject, body_text, status, max_attempts, available_at) VALUES (?, ?, ?, ?, ?, ?, 'queued', 5, UTC_TIMESTAMP())", [$id, 'account_' . $purpose, 'account:' . $key, $email, 'Pulse account: ' . str_replace('_', ' ', $purpose), $body]);
		return (int)$this->_database->GetConnection()->lastInsertId();
	}

	/** @brief Audit. */
	public function Audit(int $actorId, int $targetId, string $event): void
	{
		$this->Execute("INSERT INTO audit_log (user_id, event_type, entity_type, entity_id, message, context_json) VALUES (?, ?, 'account', ?, ?, ?)", [$actorId, $event, $targetId, $event, json_encode(['actor_id' => $actorId, 'target_id' => $targetId], JSON_THROW_ON_ERROR)]);
	}

	/** @brief Reads public registration policy, disabled by default. */
	public function RegistrationSettings(): array
	{
		return $this->Execute('SELECT registration_enabled, registration_requires_approval FROM account_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
	}

	/** @brief Updates policy within the account transaction. */
	public function UpdateRegistrationSettings(bool $enabled, bool $approval): void
	{
		$this->Execute('UPDATE account_settings SET registration_enabled = ?, registration_requires_approval = ? WHERE id = 1', [(int)$enabled, (int)$approval]);
	}

	/** @brief Lists physical file names for cleanup without exposing content to administrators. */
	public function StoredFiles(int $id): array
	{
		return $this->Execute('SELECT d.stored_filename FROM documents d INNER JOIN monitors m ON m.id = d.monitor_id WHERE m.user_id = ? AND d.stored_filename IS NOT NULL UNION SELECT d.stored_filename FROM recipient_delivery_documents d INNER JOIN recipient_release_deliveries r ON r.id = d.recipient_delivery_id INNER JOIN monitors m ON m.id = r.monitor_id WHERE m.user_id = ? AND d.stored_filename IS NOT NULL', [$id, $id])->fetchAll(PDO::FETCH_COLUMN);
	}

	/** @brief Returns administrative account events without private monitor or document data. */
	public function RecentAudit(): array
	{
		return $this->Execute("SELECT user_id, entity_id, event_type, created_at FROM audit_log WHERE event_type LIKE 'account.%' ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
	}

	/** @brief Writable fields. */
	private function WritableFields(array $fields): array
	{
		$allowed = ['email', 'email_checked_at', 'password_hash', 'display_name', 'role', 'is_active', 'email_verified_at', 'approved_at', 'totp_required', 'password_change_required', 'auth_version', 'notification_locale', 'website_locale'];
		foreach (array_keys($fields) as $field)
		{
			if (!in_array($field, $allowed, true))
			{
				throw new RuntimeException('Unsupported account field: ' . $field);
			}
		}
		return $fields;
	}

	/** @brief Execute. */
	private function Execute(string $sql, array $bindings = []): \PDOStatement
	{
		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute($bindings);
		return $statement;
	}
}
