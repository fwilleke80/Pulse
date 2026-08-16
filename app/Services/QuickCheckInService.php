<?php

/**
 * @file QuickCheckInService.php
 * @brief Expiring email pointers for authenticated global quick check-in.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use PDO;
use Pulse\Core\Database;
use RuntimeException;

/**
 * @brief Creates and consumes non-authenticating quick-check-in email tokens.
 *
 * The email token only locates an eligible account/check cycle. Completing a check-in
 * still requires a configured authentication method, currently a passkey or password.
 */
final class QuickCheckInService
{
	private const TOKEN_LIFETIME_DAYS = 30;

	private Database $_database;
	private bool $_enabled;
	private string $_baseUrl;

	/** @brief Constructs the quick-check-in service. */
	public function __construct(Database $database, array $config)
	{
		$this->_database = $database;
		$this->_enabled = (bool)($config['security']['passkey_quick_checkin_enabled'] ?? false);
		$this->_baseUrl = rtrim((string)($config['base_url'] ?? ''), '/');
	}

	/** @brief Returns whether quick check-in links are globally enabled. */
	public function IsEnabled(): bool
	{
		return $this->_enabled;
	}

	/** @brief Creates an authenticated-check-in pointer for one owner reminder cycle. */
	public function CreateUrl(int $userId, int $checkCycleId): ?string
	{
		if (!$this->_enabled)
		{
			return null;
		}

		$rawToken = $this->Base64UrlEncode(random_bytes(32));
		$statement = $this->_database->GetConnection()->prepare('INSERT INTO quick_checkin_tokens (user_id, check_cycle_id, token_hash, expires_at) VALUES (:user_id, :check_cycle_id, :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::TOKEN_LIFETIME_DAYS . ' DAY))');
		$statement->execute([
			'user_id' => $userId,
			'check_cycle_id' => $checkCycleId,
			'token_hash' => hash('sha256', $rawToken),
		]);
		return $this->_baseUrl . '/quick-check-in?token=' . rawurlencode($rawToken);
	}

	/** @return array<string, mixed>|null @brief Resolves a raw email token without consuming it. */
	public function ResolveRawToken(string $rawToken): ?array
	{
		if (!$this->_enabled || !$this->IsTokenShapeValid($rawToken))
		{
			return null;
		}

		return $this->ResolveHash(hash('sha256', $rawToken));
	}

	/** @return array<string, mixed>|null @brief Resolves a session-bound token hash without consuming it. */
	public function ResolveHash(string $tokenHash): ?array
	{
		if (!$this->_enabled || preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1)
		{
			return null;
		}

		$statement = $this->_database->GetConnection()->prepare('SELECT q.id, q.user_id, q.check_cycle_id, q.expires_at, q.used_at, cc.status AS cycle_status, u.is_active FROM quick_checkin_tokens q INNER JOIN check_cycles cc ON cc.id = q.check_cycle_id INNER JOIN users u ON u.id = q.user_id WHERE q.token_hash = :token_hash AND q.used_at IS NULL AND q.expires_at > UTC_TIMESTAMP() AND cc.status IN (\'awaiting\',\'safety_pending\',\'overdue\') AND u.is_active = 1 LIMIT 1');
		$statement->execute(['token_hash' => $tokenHash]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/** @brief Atomically consumes a token before performing its authenticated global check-in. */
	public function Claim(string $tokenHash, int $userId): bool
	{
		if (!$this->_enabled || preg_match('/^[a-f0-9]{64}$/', $tokenHash) !== 1)
		{
			return false;
		}

		$statement = $this->_database->GetConnection()->prepare('UPDATE quick_checkin_tokens q INNER JOIN check_cycles cc ON cc.id = q.check_cycle_id SET q.used_at = UTC_TIMESTAMP() WHERE q.token_hash = :token_hash AND q.user_id = :user_id AND q.used_at IS NULL AND q.expires_at > UTC_TIMESTAMP() AND cc.status IN (\'awaiting\',\'safety_pending\',\'overdue\')');
		$statement->execute(['token_hash' => $tokenHash, 'user_id' => $userId]);
		return $statement->rowCount() === 1;
	}

	/** @brief Hashes a validated raw token for storage in the browser session. */
	public function HashRawToken(string $rawToken): string
	{
		if (!$this->IsTokenShapeValid($rawToken))
		{
			throw new RuntimeException('Invalid quick check-in token.');
		}

		return hash('sha256', $rawToken);
	}

	/** @brief Checks the base64url token shape without database access. */
	private function IsTokenShapeValid(string $rawToken): bool
	{
		return strlen($rawToken) >= 40 && strlen($rawToken) <= 128 && preg_match('/^[A-Za-z0-9_-]+$/', $rawToken) === 1;
	}

	/** @brief Encodes random token bytes for URLs. */
	private function Base64UrlEncode(string $value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
