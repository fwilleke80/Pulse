<?php

/**
 * @file SecurityCredentialRepository.php
 * @brief Persistence for extensible account security methods and credentials.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;
use RuntimeException;

/**
 * @brief Stores method-agnostic security credentials; passkeys are the first implemented method.
 */
final class SecurityCredentialRepository
{
	public const METHOD_PASSKEY = 'passkey';

	private Database $_database;

	/** @brief Constructs the repository. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/** @brief Returns or creates the stable opaque WebAuthn user handle for an account. */
	public function GetOrCreateWebAuthnUserHandle(int $userId): string
	{
		$connection = $this->_database->GetConnection();
		$statement = $connection->prepare('SELECT webauthn_user_handle FROM user_security_profiles WHERE user_id = :user_id');
		$statement->execute(['user_id' => $userId]);
		$value = $statement->fetchColumn();

		if (is_string($value) && $value !== '')
		{
			return $value;
		}

		$handle = $this->Base64UrlEncode(random_bytes(32));
		$insert = $connection->prepare('INSERT IGNORE INTO user_security_profiles (user_id, webauthn_user_handle) VALUES (:user_id, :handle)');
		$insert->execute(['user_id' => $userId, 'handle' => $handle]);
		$statement->execute(['user_id' => $userId]);
		$value = $statement->fetchColumn();

		if (!is_string($value) || $value === '')
		{
			throw new RuntimeException('Unable to create WebAuthn user handle.');
		}

		return $value;
	}

	/** @return array<int, array<string, mixed>> @brief Lists passkeys for an account. */
	public function FindPasskeysForUser(int $userId): array
	{
		$statement = $this->_database->GetConnection()->prepare('SELECT usm.id, usm.label, upc.credential_id, upc.transports, usm.created_at, usm.last_used_at FROM user_security_methods usm INNER JOIN user_passkey_credentials upc ON upc.security_method_id = usm.id WHERE usm.user_id = :user_id AND usm.method = :method ORDER BY usm.id ASC');
		$statement->execute(['user_id' => $userId, 'method' => self::METHOD_PASSKEY]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	/** @brief Returns whether an account has at least one passkey. */
	public function HasPasskeys(int $userId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('SELECT 1 FROM user_security_methods WHERE user_id = :user_id AND method = :method LIMIT 1');
		$statement->execute(['user_id' => $userId, 'method' => self::METHOD_PASSKEY]);
		return $statement->fetchColumn() !== false;
	}

	/** @return array<string, mixed>|null @brief Finds a passkey by raw credential ID. */
	public function FindPasskeyByCredentialId(string $credentialId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare('SELECT usm.id, usm.user_id, usm.method, usm.label, usm.created_at, usm.last_used_at, upc.credential_id_hash, upc.credential_id, upc.public_key_pem, upc.algorithm, upc.sign_count, upc.transports, usp.webauthn_user_handle, u.is_active, u.website_locale FROM user_security_methods usm INNER JOIN user_passkey_credentials upc ON upc.security_method_id = usm.id INNER JOIN users u ON u.id = usm.user_id INNER JOIN user_security_profiles usp ON usp.user_id = usm.user_id WHERE usm.method = :method AND upc.credential_id_hash = :hash LIMIT 1');
		$statement->execute([
			'method' => self::METHOD_PASSKEY,
			'hash' => hash('sha256', $credentialId),
		]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/** @brief Inserts a newly verified passkey. */
	public function AddPasskey(int $userId, string $label, string $credentialId, string $publicKeyPem, int $algorithm, int $signCount, array $transports): int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$method = $connection->prepare('INSERT INTO user_security_methods (user_id, method, label) VALUES (:user_id, :method, :label)');
			$method->execute([
				'user_id' => $userId,
				'method' => self::METHOD_PASSKEY,
				'label' => $label,
			]);
			$methodId = (int)$connection->lastInsertId();
			$credential = $connection->prepare('INSERT INTO user_passkey_credentials (security_method_id, credential_id_hash, credential_id, public_key_pem, algorithm, sign_count, transports) VALUES (:security_method_id, :credential_id_hash, :credential_id, :public_key_pem, :algorithm, :sign_count, :transports)');
			$credential->execute([
				'security_method_id' => $methodId,
				'credential_id_hash' => hash('sha256', $credentialId),
				'credential_id' => $this->Base64UrlEncode($credentialId),
				'public_key_pem' => $publicKeyPem,
				'algorithm' => $algorithm,
				'sign_count' => max(0, $signCount),
				'transports' => $transports === [] ? null : implode(',', array_values(array_unique($transports))),
			]);
			$connection->commit();
			return $methodId;
		}
		catch (\Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @brief Updates usage metadata and the authenticator signature counter. */
	public function MarkPasskeyUsed(int $credentialId, int $signCount): void
	{
		$statement = $this->_database->GetConnection()->prepare('UPDATE user_passkey_credentials upc INNER JOIN user_security_methods usm ON usm.id = upc.security_method_id SET upc.sign_count = :sign_count, usm.last_used_at = UTC_TIMESTAMP() WHERE usm.id = :id AND usm.method = :method');
		$statement->execute([
			'sign_count' => max(0, $signCount),
			'id' => $credentialId,
			'method' => self::METHOD_PASSKEY,
		]);
	}

	/** @brief Deletes one passkey owned by an account. */
	public function DeletePasskeyForUser(int $credentialId, int $userId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('DELETE FROM user_security_methods WHERE id = :id AND user_id = :user_id AND method = :method');
		$statement->execute(['id' => $credentialId, 'user_id' => $userId, 'method' => self::METHOD_PASSKEY]);
		return $statement->rowCount() > 0;
	}

	/** @brief Base64url-encodes a binary value. */
	private function Base64UrlEncode(string $value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
