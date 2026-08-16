<?php

/**
 * @file TotpService.php
 * @brief Optional TOTP enrollment, password-login challenges, and recovery lifecycle.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\Session;
use Pulse\Core\TotpAlgorithm;
use Pulse\Repositories\TotpCredentialRepository;
use RuntimeException;

/**
 * @brief Coordinates short-lived session state with durable encrypted TOTP credentials.
 */
final class TotpService
{
	private const ENROLLMENT_SESSION_KEY = 'pulse_totp_enrollment';
	private const LOGIN_SESSION_KEY = 'pulse_totp_login';
	private const RECOVERY_CODES_SESSION_KEY = 'pulse_totp_recovery_codes';
	private const ENROLLMENT_MAX_AGE_SECONDS = 600;
	private const LOGIN_MAX_AGE_SECONDS = 300;
	private const RECOVERY_DISPLAY_MAX_AGE_SECONDS = 1800;
	private const RECOVERY_CODE_COUNT = 10;

	private TotpCredentialRepository $_repository;
	private TotpAlgorithm $_algorithm;
	private TotpSecretProtector $_protector;
	private Session $_session;

	/** @brief Constructs the TOTP service. */
	public function __construct(
		TotpCredentialRepository $repository,
		TotpAlgorithm $algorithm,
		TotpSecretProtector $protector,
		Session $session
	)
	{
		$this->_repository = $repository;
		$this->_algorithm = $algorithm;
		$this->_protector = $protector;
		$this->_session = $session;
	}

	/** @brief Returns whether password login for an account requires a second factor. */
	public function IsEnabled(int $userId): bool
	{
		return $this->_repository->IsEnabled($userId);
	}

	/** @return array<string, mixed>|null @brief Returns enabled-method status for Profile. */
	public function Status(int $userId): ?array
	{
		return $this->_repository->FindForUser($userId);
	}

	/** @param array<string, mixed> $user @brief Starts password-confirmed authenticator enrollment. */
	public function BeginEnrollment(array $user): void
	{
		$userId = (int)($user['id'] ?? 0);

		if ($userId <= 0 || $this->IsEnabled($userId))
		{
			throw new RuntimeException('TOTP is already enabled or the account is unavailable.');
		}

		$this->_protector->EnsureReady();
		$this->_session->Set(self::ENROLLMENT_SESSION_KEY, [
			'user_id' => $userId,
			'secret' => $this->_algorithm->GenerateSecret(),
			'account_name' => (string)($user['email'] ?? 'Pulse account'),
			'created_at' => time(),
		]);
	}

	/** @return array{secret:string,formatted_secret:string,provisioning_uri:string}|null @brief Returns a live pending enrollment without consuming it. */
	public function PendingEnrollment(int $userId): ?array
	{
		$entry = $this->_session->Get(self::ENROLLMENT_SESSION_KEY);

		if (!$this->ValidTimedEntry($entry, $userId, self::ENROLLMENT_MAX_AGE_SECONDS))
		{
			$this->_session->Remove(self::ENROLLMENT_SESSION_KEY);
			return null;
		}

		$secret = (string)$entry['secret'];
		$accountName = trim((string)($entry['account_name'] ?? 'Pulse account'));

		return [
			'secret' => $secret,
			'formatted_secret' => trim(chunk_split($secret, 4, ' ')),
			'provisioning_uri' => $this->ProvisioningUri($accountName, $secret),
		];
	}

	/** @return array<int, string>|null @brief Confirms enrollment and returns the only plaintext copy of new recovery codes. */
	public function ConfirmEnrollment(int $userId, string $code): ?array
	{
		$entry = $this->_session->Get(self::ENROLLMENT_SESSION_KEY);

		if (!$this->ValidTimedEntry($entry, $userId, self::ENROLLMENT_MAX_AGE_SECONDS))
		{
			$this->_session->Remove(self::ENROLLMENT_SESSION_KEY);
			return null;
		}

		$secret = (string)$entry['secret'];
		$counter = $this->_algorithm->Verify($secret, $code);

		if (!is_int($counter))
		{
			return null;
		}

		$recoveryCodes = $this->GenerateRecoveryCodes();
		$this->_repository->Enable(
			$userId,
			$this->_protector->Encrypt($secret),
			$this->HashRecoveryCodes($recoveryCodes)
		);
		$this->_session->Remove(self::ENROLLMENT_SESSION_KEY);
		$this->StoreRecoveryCodes($userId, $recoveryCodes);
		return $recoveryCodes;
	}

	/** @brief Cancels an unfinished authenticator enrollment for the current account. */
	public function CancelEnrollment(int $userId): void
	{
		$entry = $this->_session->Get(self::ENROLLMENT_SESSION_KEY);

		if (is_array($entry) && (int)($entry['user_id'] ?? 0) === $userId)
		{
			$this->_session->Remove(self::ENROLLMENT_SESSION_KEY);
		}
	}

	/**
	 * @brief Stores the password-verified account while awaiting its second factor.
	 * @param array<string, mixed>|null $location Optional location captured for a pending quick check-in.
	 */
	public function BeginLogin(int $userId, string $email, ?array $location): void
	{
		$this->_session->Set(self::LOGIN_SESSION_KEY, [
			'user_id' => $userId,
			'email' => strtolower(trim($email)),
			'location' => $location,
			'created_at' => time(),
		]);
	}

	/** @return array{user_id:int,email:string,location:array<string,mixed>|null}|null @brief Returns the live password-verified login challenge. */
	public function PendingLogin(): ?array
	{
		$entry = $this->_session->Get(self::LOGIN_SESSION_KEY);

		if (!is_array($entry) || (time() - (int)($entry['created_at'] ?? 0)) > self::LOGIN_MAX_AGE_SECONDS)
		{
			$this->_session->Remove(self::LOGIN_SESSION_KEY);
			return null;
		}

		$userId = (int)($entry['user_id'] ?? 0);

		if ($userId <= 0)
		{
			$this->_session->Remove(self::LOGIN_SESSION_KEY);
			return null;
		}

		$location = $entry['location'] ?? null;
		return [
			'user_id' => $userId,
			'email' => (string)($entry['email'] ?? ''),
			'location' => is_array($location) ? $location : null,
		];
	}

	/** @brief Clears a pending password/TOTP login, including after passkey authentication. */
	public function CancelPendingLogin(): void
	{
		$this->_session->Remove(self::LOGIN_SESSION_KEY);
	}

	/**
	 * @brief Verifies and consumes an authenticator or recovery code for password login.
	 * @return string|null Authentication method for the completed session, or null on failure.
	 */
	public function VerifyAuthentication(int $userId, string $code): ?string
	{
		$method = $this->VerifyCode($userId, $code);

		if ($method === 'totp')
		{
			$this->_repository->RecordAuthentication($userId, 'security.totp_login_succeeded', 'Password login completed with a TOTP code.');
			return 'password_totp';
		}

		if ($method === 'recovery')
		{
			$this->_repository->RecordAuthentication($userId, 'security.totp_recovery_login_succeeded', 'Password login completed with a TOTP recovery code.');
			return 'password_recovery';
		}

		return null;
	}

	/** @brief Verifies and consumes an authenticator or recovery code for a sensitive Profile action. */
	public function VerifyManagementCode(int $userId, string $code): bool
	{
		return $this->VerifyCode($userId, $code) !== null;
	}

	/** @return array<int, string> @brief Invalidates old recovery codes and stores a new one-time display set. */
	public function RegenerateRecoveryCodes(int $userId): array
	{
		$codes = $this->GenerateRecoveryCodes();
		$this->_repository->ReplaceRecoveryCodes($userId, $this->HashRecoveryCodes($codes));
		$this->StoreRecoveryCodes($userId, $codes);
		return $codes;
	}

	/** @brief Disables the optional method and clears any transient TOTP session state. */
	public function Disable(int $userId): bool
	{
		$disabled = $this->_repository->Disable($userId);
		$this->CancelEnrollment($userId);
		$this->AcknowledgeRecoveryCodes($userId);
		return $disabled;
	}

	/** @return array<int, string> @brief Returns unacknowledged plaintext recovery codes for a short display window. */
	public function RecoveryCodes(int $userId): array
	{
		$entry = $this->_session->Get(self::RECOVERY_CODES_SESSION_KEY);

		if (!$this->ValidTimedEntry($entry, $userId, self::RECOVERY_DISPLAY_MAX_AGE_SECONDS))
		{
			$this->_session->Remove(self::RECOVERY_CODES_SESSION_KEY);
			return [];
		}

		$codes = $entry['codes'] ?? [];
		return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
	}

	/** @brief Removes the temporary plaintext recovery-code display after owner acknowledgement. */
	public function AcknowledgeRecoveryCodes(int $userId): void
	{
		$entry = $this->_session->Get(self::RECOVERY_CODES_SESSION_KEY);

		if (is_array($entry) && (int)($entry['user_id'] ?? 0) === $userId)
		{
			$this->_session->Remove(self::RECOVERY_CODES_SESSION_KEY);
		}
	}

	/** @return string|null @brief Verifies one live TOTP counter or one unused recovery code. */
	private function VerifyCode(int $userId, string $code): ?string
	{
		$credential = $this->_repository->FindForUser($userId);

		if (!is_array($credential))
		{
			return null;
		}

		$numericCode = preg_replace('/[\s-]+/', '', trim($code)) ?? '';

		if (preg_match('/^\d{' . TotpAlgorithm::DIGITS . '}$/', $numericCode) === 1)
		{
			$secret = $this->_protector->Decrypt((string)$credential['secret_ciphertext']);
			$counter = $this->_algorithm->Verify($secret, $numericCode);

			if (is_int($counter) && $this->_repository->ConsumeCounter($userId, $counter))
			{
				return 'totp';
			}
		}

		$recoveryCode = $this->NormalizeRecoveryCode($code);

		if ($recoveryCode !== '' && $this->_repository->ConsumeRecoveryCode($userId, $this->_protector->HashRecoveryCode($recoveryCode)))
		{
			return 'recovery';
		}

		return null;
	}

	/** @return array<int, string> @brief Generates a unique formatted set of 80-bit recovery codes. */
	private function GenerateRecoveryCodes(): array
	{
		$codes = [];

		while (count($codes) < self::RECOVERY_CODE_COUNT)
		{
			$raw = $this->_algorithm->GenerateRecoveryCode();
			$formatted = implode('-', str_split($raw, 4));
			$codes[$raw] = $formatted;
		}

		return array_values($codes);
	}

	/** @param array<int, string> $codes @return array<int, string> @brief Hashes recovery codes with the installation key. */
	private function HashRecoveryCodes(array $codes): array
	{
		return array_map(
			fn (string $code): string => $this->_protector->HashRecoveryCode($this->NormalizeRecoveryCode($code)),
			$codes
		);
	}

	/** @brief Normalizes a displayed recovery code to its 16-character Base32 value. */
	private function NormalizeRecoveryCode(string $code): string
	{
		$normalized = strtoupper(preg_replace('/[^a-z0-9]+/i', '', trim($code)) ?? '');
		return preg_match('/^[A-Z2-7]{16}$/', $normalized) === 1 ? $normalized : '';
	}

	/** @param array<int, string> $codes @brief Keeps plaintext recovery codes only in the authenticated session. */
	private function StoreRecoveryCodes(int $userId, array $codes): void
	{
		$this->_session->Set(self::RECOVERY_CODES_SESSION_KEY, [
			'user_id' => $userId,
			'codes' => $codes,
			'created_at' => time(),
		]);
	}

	/** @brief Builds a standard otpauth URI without making any third-party request. */
	private function ProvisioningUri(string $accountName, string $secret): string
	{
		$accountName = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $accountName) ?? '');
		$accountName = $accountName !== '' ? substr($accountName, 0, 160) : 'Pulse account';

		return 'otpauth://totp/' . rawurlencode('Pulse:' . $accountName)
			. '?secret=' . rawurlencode($secret)
			. '&issuer=' . rawurlencode('Pulse');
	}

	/** @brief Validates user binding and maximum age for transient security state. */
	private function ValidTimedEntry(mixed $entry, int $userId, int $maximumAge): bool
	{
		return is_array($entry)
			&& (int)($entry['user_id'] ?? 0) === $userId
			&& (int)($entry['created_at'] ?? 0) > 0
			&& (time() - (int)$entry['created_at']) <= $maximumAge;
	}
}
