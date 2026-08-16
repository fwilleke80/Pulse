<?php

/**
 * @file TotpSecretProtector.php
 * @brief Authenticated encryption and keyed recovery-code hashing for TOTP credentials.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\EnvironmentFile;
use RuntimeException;

/**
 * @brief Keeps authenticator secrets outside plaintext database storage.
 */
final class TotpSecretProtector
{
	private const ENVIRONMENT_KEY = 'PULSE_TOTP_ENCRYPTION_KEY';
	private const CIPHER = 'aes-256-gcm';
	private const ASSOCIATED_DATA = 'Pulse TOTP credential v1';
	private const FORMAT_PREFIX = 'v1';

	private string $_configuredKey;
	private EnvironmentFile $_environmentFile;
	private ?string $_resolvedKey = null;

	/** @brief Constructs the protector from runtime configuration and the editable environment file. */
	public function __construct(string $configuredKey, EnvironmentFile $environmentFile)
	{
		$this->_configuredKey = trim($configuredKey);
		$this->_environmentFile = $environmentFile;
	}

	/** @brief Creates the installation key on demand and verifies that it is usable. */
	public function EnsureReady(): void
	{
		$this->ResolveKey(true);
	}

	/** @brief Encrypts a Base32 TOTP secret using AES-256-GCM. */
	public function Encrypt(string $secret): string
	{
		$key = $this->ResolveKey(true);
		$nonce = random_bytes(12);
		$tag = '';
		$ciphertext = openssl_encrypt(
			$secret,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			self::ASSOCIATED_DATA,
			16
		);

		if (!is_string($ciphertext) || strlen($tag) !== 16)
		{
			throw new RuntimeException('The TOTP secret could not be encrypted.');
		}

		return implode('.', [
			self::FORMAT_PREFIX,
			$this->Base64UrlEncode($nonce),
			$this->Base64UrlEncode($tag),
			$this->Base64UrlEncode($ciphertext),
		]);
	}

	/** @brief Decrypts and authenticates a stored TOTP secret. */
	public function Decrypt(string $protectedSecret): string
	{
		$parts = explode('.', $protectedSecret);

		if (count($parts) !== 4 || $parts[0] !== self::FORMAT_PREFIX)
		{
			throw new RuntimeException('The stored TOTP secret format is unsupported.');
		}

		$nonce = $this->Base64UrlDecode($parts[1]);
		$tag = $this->Base64UrlDecode($parts[2]);
		$ciphertext = $this->Base64UrlDecode($parts[3]);

		if (strlen($nonce) !== 12 || strlen($tag) !== 16)
		{
			throw new RuntimeException('The stored TOTP secret is malformed.');
		}

		$secret = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$this->ResolveKey(false),
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			self::ASSOCIATED_DATA
		);

		if (!is_string($secret) || preg_match('/^[A-Z2-7]{32}$/', $secret) !== 1)
		{
			throw new RuntimeException('The stored TOTP secret could not be decrypted.');
		}

		return $secret;
	}

	/** @brief Returns an opaque keyed hash for a high-entropy recovery code. */
	public function HashRecoveryCode(string $normalizedCode): string
	{
		return hash_hmac('sha256', 'Pulse recovery code:' . $normalizedCode, $this->ResolveKey(false));
	}

	/** @brief Resolves or deliberately creates the installation-specific 256-bit key. */
	private function ResolveKey(bool $createWhenMissing): string
	{
		if (is_string($this->_resolvedKey))
		{
			return $this->_resolvedKey;
		}

		$key = $this->DecodeKey($this->_configuredKey);

		if (is_string($key))
		{
			$this->_resolvedKey = $key;
			return $key;
		}

		$processValue = getenv(self::ENVIRONMENT_KEY);

		if (is_string($processValue))
		{
			throw new RuntimeException('PULSE_TOTP_ENCRYPTION_KEY is set by the server process but is not a valid Base64-encoded 32-byte key.');
		}

		$fileValues = $this->_environmentFile->ReadValues();
		$fileValue = trim((string)($fileValues[self::ENVIRONMENT_KEY] ?? ''));
		$key = $this->DecodeKey($fileValue);

		if (is_string($key))
		{
			$this->_resolvedKey = $key;
			return $key;
		}

		if (!$createWhenMissing)
		{
			throw new RuntimeException('The TOTP encryption key is missing.');
		}

		if ($fileValue !== '')
		{
			throw new RuntimeException('PULSE_TOTP_ENCRYPTION_KEY in .env is invalid.');
		}

		$encodedKey = base64_encode(random_bytes(32));
		$this->_environmentFile->Update([self::ENVIRONMENT_KEY => $encodedKey]);
		$key = $this->DecodeKey($encodedKey);

		if (!is_string($key))
		{
			throw new RuntimeException('The TOTP encryption key could not be created.');
		}

		$this->_configuredKey = $encodedKey;
		$this->_resolvedKey = $key;
		return $key;
	}

	/** @brief Strictly decodes a Base64-encoded 256-bit key. */
	private function DecodeKey(string $encoded): ?string
	{
		if ($encoded === '')
		{
			return null;
		}

		$decoded = base64_decode($encoded, true);
		return is_string($decoded) && strlen($decoded) === 32 ? $decoded : null;
	}

	/** @brief Base64url-encodes a binary value without padding. */
	private function Base64UrlEncode(string $value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}

	/** @brief Strictly decodes an unpadded Base64url value. */
	private function Base64UrlDecode(string $value): string
	{
		$padding = (4 - strlen($value) % 4) % 4;
		$decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);

		if (!is_string($decoded))
		{
			throw new RuntimeException('The protected TOTP value is not valid Base64url.');
		}

		return $decoded;
	}
}
