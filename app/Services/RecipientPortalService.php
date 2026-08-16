<?php

/**
 * @file RecipientPortalService.php
 * @brief Recipient portal invitation, access-code, and session-authentication orchestration.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\Logger;
use Pulse\Core\RecipientPortalAccess;
use Pulse\Core\Session;
use Pulse\Repositories\MailQueueRepository;
use Pulse\Repositories\RecipientPortalRepository;
use Throwable;

/**
 * @brief Coordinates secure access-code issuance without exposing recipient contact details publicly.
 */
final class RecipientPortalService
{
	public const ACCESS_CODE_LIFETIME_SECONDS = 1800;
	private const CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz';

	private RecipientPortalRepository $_portalRepository;
	private MailQueueRepository $_mailQueueRepository;
	private NotificationComposer $_composer;
	private Logger $_logger;
	private int $_maxMailAttempts;

	/** @brief Constructs the portal service. */
	public function __construct(
		RecipientPortalRepository $portalRepository,
		MailQueueRepository $mailQueueRepository,
		NotificationComposer $composer,
		Logger $logger,
		int $maxMailAttempts
	)
	{
		$this->_portalRepository = $portalRepository;
		$this->_mailQueueRepository = $mailQueueRepository;
		$this->_composer = $composer;
		$this->_logger = $logger;
		$this->_maxMailAttempts = $maxMailAttempts;
	}

	/** @return array<string, mixed>|null @brief Resolves a currently usable portal invitation. */
	public function FindActiveDelivery(string $rawToken): ?array
	{
		return $this->_portalRepository->FindActiveByToken($rawToken);
	}

	/**
	 * @brief Resolves only language metadata for an invitation, including revoked or expired deliveries.
	 * @return array<string, mixed>|null Minimal delivery metadata or null.
	 */
	public function FindLanguageMetadata(string $rawToken): ?array
	{
		return $this->_portalRepository->FindLanguageMetadataByToken($rawToken);
	}

	/**
	 * @brief Generates and queues a new access code when rate limits permit it.
	 * @return int|null Queue ID, or null when no new code was issued.
	 */
	public function RequestAccessCode(array $delivery): ?int
	{
		$rawCode = $this->GenerateCode();
		$normalizedCode = $this->NormalizeCode($rawCode);
		$hash = password_hash($normalizedCode, PASSWORD_DEFAULT);

		if (!is_string($hash) || $hash === '')
		{
			throw new \RuntimeException('Unable to hash recipient access code.');
		}

		$codeId = $this->_portalRepository->CreateAccessCode(
			(int)$delivery['delivery_id'],
			$hash,
			self::ACCESS_CODE_LIFETIME_SECONDS
		);

		if ($codeId === null)
		{
			return null;
		}

		$content = $this->_composer->ComposeRecipientAccessCode([
			'recipient_name' => (string)$delivery['recipient_name'],
			'notification_locale' => (string)$delivery['notification_locale'],
			'owner_name' => (string)$delivery['owner_name'],
			'monitor_name' => (string)$delivery['monitor_name'],
			'access_code' => $rawCode,
			'valid_minutes' => (int)(self::ACCESS_CODE_LIFETIME_SECONDS / 60),
		]);

		try
		{
			return $this->_mailQueueRepository->Enqueue([
				'user_id' => (int)$delivery['user_id'],
				'check_cycle_id' => (int)$delivery['check_cycle_id'],
				'monitor_id' => (int)$delivery['monitor_id'],
				'contact_id' => $delivery['contact_id'],
				'safety_request_id' => null,
				'recipient_delivery_id' => (int)$delivery['delivery_id'],
				'recipient_portal_code_id' => $codeId,
				'mail_type' => 'recipient_access_code',
				'idempotency_key' => 'recipient-access-code:' . (int)$delivery['delivery_id'] . ':' . $codeId,
				'reminder_number' => null,
				'recipient_email' => (string)$delivery['recipient_email'],
				'subject' => $content['subject'],
				'body_text' => $content['body_text'],
				'max_attempts' => $this->_maxMailAttempts,
				'available_at' => gmdate('Y-m-d H:i:s'),
			]);
		}
		catch (Throwable $throwable)
		{
			$this->_portalRepository->InvalidateCode($codeId);
			$this->_logger->Warning('Unable to queue recipient access code', [
				'delivery_id' => (int)$delivery['delivery_id'],
			]);
			throw $throwable;
		}
	}

	/**
	 * @brief Verifies one submitted access code and consumes it on success.
	 * @return array<string, mixed>|null Active delivery on success.
	 */
	public function VerifyAccessCode(string $rawToken, string $submittedCode): ?array
	{
		$normalized = $this->NormalizeCode($submittedCode);
		return $this->_portalRepository->VerifyAccessCode($rawToken, $normalized);
	}

	/** @brief Stores recipient authentication in the current browser session. */
	public function GrantSession(Session $session, string $rawToken, int $deliveryId): void
	{
		$session->Regenerate();
		$session->Set(RecipientPortalAccess::SessionKey($rawToken), [
			'delivery_id' => $deliveryId,
			'verified_at' => time(),
		]);
	}

	/** @brief Returns whether this browser has a still-current authentication for the portal token. */
	public function HasValidSession(Session $session, string $rawToken, int $deliveryId): bool
	{
		$value = $session->Get(RecipientPortalAccess::SessionKey($rawToken));

		if (!is_array($value) || (int)($value['delivery_id'] ?? 0) !== $deliveryId)
		{
			return false;
		}

		return (int)($value['verified_at'] ?? 0) > 0;
	}

	/**
	 * @brief Returns immutable document snapshots for one authenticated delivery.
	 * @return array<int, array<string, mixed>> Recipient-facing document snapshots.
	 */
	public function DocumentsForDelivery(int $deliveryId): array
	{
		return $this->_portalRepository->FindDocumentsForDelivery($deliveryId);
	}

	/** @brief Returns the immutable release-level location trail for an authenticated delivery. */
	public function LocationsForDelivery(int $deliveryId): array
	{
		return $this->_portalRepository->FindLocationsForDelivery($deliveryId);
	}

	/** @brief Finds one immutable document snapshot for a delivery. @return array<string, mixed>|null */
	public function DocumentForDelivery(int $deliveryId, int $snapshotDocumentId): ?array
	{
		return $this->_portalRepository->FindDocumentForDelivery($deliveryId, $snapshotDocumentId);
	}

	/** @brief Records one successful recipient document download. */
	public function RecordDocumentDownload(int $deliveryId, int $snapshotDocumentId): void
	{
		$this->_portalRepository->RecordDocumentDownload($deliveryId, $snapshotDocumentId);
	}

	/** @brief Records one successful download-all archive request. */
	public function RecordDownloadAll(int $deliveryId, int $documentCount): void
	{
		$this->_portalRepository->RecordDownloadAll($deliveryId, $documentCount);
	}

	/** @brief Revokes a recipient portal delivery for its authenticated owner. */
	public function RevokeForUser(int $deliveryId, int $userId): bool
	{
		return $this->_portalRepository->RevokeForUser($deliveryId, $userId);
	}

	/**
	 * @brief Starts a deliberate permanent-close confirmation challenge for an authenticated recipient.
	 * @return string Easy-to-type random confirmation code.
	 */
	public function BeginCloseConfirmation(Session $session, string $rawToken, int $deliveryId): string
	{
		$code = $this->GenerateCode();
		$session->Set(RecipientPortalAccess::CloseConfirmationKey($rawToken), [
			'delivery_id' => $deliveryId,
			'code' => $code,
		]);

		return $code;
	}

	/** @brief Returns the current close-confirmation code for this delivery, if one exists. */
	public function CurrentCloseConfirmation(Session $session, string $rawToken, int $deliveryId): ?string
	{
		$value = $session->Get(RecipientPortalAccess::CloseConfirmationKey($rawToken));

		if (!is_array($value) || (int)($value['delivery_id'] ?? 0) !== $deliveryId)
		{
			return null;
		}

		$code = (string)($value['code'] ?? '');
		return $code !== '' ? $code : null;
	}

	/** @brief Checks the deliberate permanent-close confirmation code. */
	public function VerifyCloseConfirmation(Session $session, string $rawToken, int $deliveryId, string $submittedCode): bool
	{
		$expected = $this->CurrentCloseConfirmation($session, $rawToken, $deliveryId);

		if ($expected === null)
		{
			return false;
		}

		return hash_equals($this->NormalizeCode($expected), $this->NormalizeCode($submittedCode));
	}

	/**
	 * @brief Permanently closes one non-expiring delivery at the authenticated recipient's request.
	 */
	public function ClosePermanently(Session $session, string $rawToken, int $deliveryId): bool
	{
		$closed = $this->_portalRepository->CloseByRecipient($rawToken, $deliveryId);

		if ($closed)
		{
			$session->Remove(RecipientPortalAccess::SessionKey($rawToken));
			$session->Remove(RecipientPortalAccess::CloseConfirmationKey($rawToken));
		}

		return $closed;
	}

	/** @brief Normalizes human-entered access codes by ignoring separators and letter case. */
	public function NormalizeCode(string $code): string
	{
		$code = strtolower($code);
		$normalized = preg_replace('/[^a-z0-9]/', '', $code);
		return is_string($normalized) ? substr($normalized, 0, 8) : '';
	}

	/** @brief Generates an easy-to-type eight-letter code formatted as xxxx-xxxx. */
	private function GenerateCode(): string
	{
		$characters = '';
		$maximum = strlen(self::CODE_ALPHABET) - 1;

		for ($index = 0; $index < 8; ++$index)
		{
			$characters .= self::CODE_ALPHABET[random_int(0, $maximum)];
		}

		return substr($characters, 0, 4) . '-' . substr($characters, 4, 4);
	}
}
