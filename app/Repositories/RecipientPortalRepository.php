<?php

/**
 * @file RecipientPortalRepository.php
 * @brief Persistent recipient portal tokens, access codes, revocation, and audit state.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Pulse\Core\Database;
use Throwable;

/**
 * @brief Provides fail-closed access to one recipient release delivery without exposing raw credentials.
 */
final class RecipientPortalRepository
{
	private const CODE_MAX_ATTEMPTS = 8;
	private const CODE_REQUESTS_PER_HOUR = 5;

	private Database $_database;

	/** @brief Constructs the repository. @param Database $database Database service. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Resolves an active, already-sent recipient delivery by its raw portal token.
	 * @param string $rawToken Raw 64-character portal invitation token.
	 * @return array<string, mixed>|null Active delivery snapshot or null.
	 */
	public function FindActiveByToken(string $rawToken): ?array
	{
		if (preg_match('/^[a-f0-9]{64}$/i', $rawToken) !== 1)
		{
			return null;
		}

		$sql = <<<'SQL'
			SELECT
				rrd.id AS delivery_id,
				rrd.release_id,
				rrd.check_cycle_id,
				rrd.monitor_id,
				rrd.contact_id,
				rrd.recipient_name,
				rrd.recipient_email,
				rrd.notification_locale,
				rrd.portal_released_at,
				rrd.portal_expires_at,
				rrd.portal_revoked_at,
				rrd.portal_last_access_at,
				rrd.subject,
				rrd.body_text,
				rrd.portal_intro_text,
				rrd.portal_message_text,
				m.name AS monitor_name,
				m.user_id,
				u.display_name AS owner_name
			FROM recipient_release_deliveries rrd
			INNER JOIN monitors m ON m.id = rrd.monitor_id
			INNER JOIN users u ON u.id = m.user_id
			WHERE rrd.portal_token_hash = :token_hash
				AND rrd.status = 'sent'
				AND rrd.portal_released_at IS NOT NULL
				AND rrd.portal_revoked_at IS NULL
				AND (rrd.portal_expires_at IS NULL OR rrd.portal_expires_at > UTC_TIMESTAMP())
			LIMIT 1
		SQL;
		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute(['token_hash' => hash('sha256', $rawToken)]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Resolves language metadata for a portal token even after the delivery is no longer active.
	 *
	 * This deliberately returns only non-sensitive presentation metadata. It is used so revoked or
	 * expired portal pages can still render in the recipient's configured language without making
	 * the delivery usable again.
	 *
	 * @param string $rawToken Raw 64-character portal invitation token.
	 * @return array<string, mixed>|null Minimal delivery metadata or null.
	 */
	public function FindLanguageMetadataByToken(string $rawToken): ?array
	{
		if (preg_match('/^[a-f0-9]{64}$/i', $rawToken) !== 1)
		{
			return null;
		}

		$statement = $this->_database->GetConnection()->prepare(<<<'SQL'
			SELECT id AS delivery_id, notification_locale
			FROM recipient_release_deliveries
			WHERE portal_token_hash = :token_hash
			LIMIT 1
		SQL);
		$statement->execute(['token_hash' => hash('sha256', $rawToken)]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Returns every snapshotted email address for one recipient delivery.
	 * @param int $deliveryId Recipient delivery ID.
	 * @param string $fallback Legacy primary address when no snapshot rows exist.
	 * @return array<int, string>
	 */
	public function FindEmailsForDelivery(int $deliveryId, string $fallback): array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT email
			FROM recipient_release_delivery_emails
			WHERE recipient_delivery_id = :delivery_id
			ORDER BY sort_order ASC
		');
		$statement->execute(['delivery_id' => $deliveryId]);
		$emails = $statement->fetchAll(PDO::FETCH_COLUMN);

		return is_array($emails) && $emails !== [] ? array_map('strval', $emails) : [$fallback];
	}

	/**
	 * @brief Creates one new short-lived access code and invalidates older unused codes.
	 * @param int $deliveryId Recipient release delivery ID.
	 * @param string $codeHash Password-hash representation of the generated code.
	 * @param int $lifetimeSeconds Code lifetime in seconds.
	 * @return int|null New code ID, or null when rate limiting suppresses a new code.
	 */
	public function CreateAccessCode(int $deliveryId, string $codeHash, int $lifetimeSeconds): ?int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$deliverySql = <<<'SQL'
				SELECT rrd.id, rrd.monitor_id, m.user_id
				FROM recipient_release_deliveries rrd
				INNER JOIN monitors m ON m.id = rrd.monitor_id
				WHERE rrd.id = :id
					AND rrd.status = 'sent'
					AND rrd.portal_released_at IS NOT NULL
					AND rrd.portal_revoked_at IS NULL
					AND (rrd.portal_expires_at IS NULL OR rrd.portal_expires_at > UTC_TIMESTAMP())
				FOR UPDATE
			SQL;
			$delivery = $connection->prepare($deliverySql);
			$delivery->execute(['id' => $deliveryId]);
			$row = $delivery->fetch(PDO::FETCH_ASSOC);

			if (!is_array($row))
			{
				$connection->commit();
				return null;
			}

			$recentSql = <<<'SQL'
				SELECT
					SUM(created_at >= TIMESTAMPADD(SECOND, -60, UTC_TIMESTAMP())) AS last_minute,
					SUM(created_at >= TIMESTAMPADD(HOUR, -1, UTC_TIMESTAMP())) AS last_hour
				FROM recipient_portal_codes
				WHERE recipient_delivery_id = :delivery_id
			SQL;
			$recent = $connection->prepare($recentSql);
			$recent->execute(['delivery_id' => $deliveryId]);
			$counts = $recent->fetch(PDO::FETCH_ASSOC);

			if (
				is_array($counts)
				&& ((int)$counts['last_minute'] >= 1 || (int)$counts['last_hour'] >= self::CODE_REQUESTS_PER_HOUR)
			)
			{
				$this->InsertAudit($connection, (int)$row['user_id'], (int)$row['monitor_id'], 'recipient.portal_code_rate_limited', [
					'delivery_id' => $deliveryId,
				]);
				$connection->commit();
				return null;
			}

			$cancelMailSql = <<<'SQL'
				UPDATE mail_queue mq
				INNER JOIN recipient_portal_codes rpc ON rpc.id = mq.recipient_portal_code_id
				SET mq.status = 'cancelled', mq.body_text = '[Access code redacted after cancellation]',
					mq.cancelled_at = UTC_TIMESTAMP(), mq.updated_at = UTC_TIMESTAMP()
				WHERE rpc.recipient_delivery_id = :delivery_id
					AND mq.mail_type = 'recipient_access_code'
					AND mq.status IN ('queued', 'retrying', 'failed')
			SQL;
			$cancelMail = $connection->prepare($cancelMailSql);
			$cancelMail->execute(['delivery_id' => $deliveryId]);

			$invalidate = $connection->prepare(<<<'SQL'
				UPDATE recipient_portal_codes
				SET invalidated_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
				WHERE recipient_delivery_id = :delivery_id
					AND used_at IS NULL AND invalidated_at IS NULL
			SQL);
			$invalidate->execute(['delivery_id' => $deliveryId]);

			$expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
				->modify('+' . max(60, $lifetimeSeconds) . ' seconds')
				->format('Y-m-d H:i:s');
			$insert = $connection->prepare(<<<'SQL'
				INSERT INTO recipient_portal_codes
				(recipient_delivery_id, code_hash, attempt_count, expires_at, created_at, updated_at)
				VALUES (:delivery_id, :code_hash, 0, :expires_at, UTC_TIMESTAMP(), UTC_TIMESTAMP())
			SQL);
			$insert->execute([
				'delivery_id' => $deliveryId,
				'code_hash' => $codeHash,
				'expires_at' => $expiresAt,
			]);
			$codeId = (int)$connection->lastInsertId();
			$this->InsertAudit($connection, (int)$row['user_id'], (int)$row['monitor_id'], 'recipient.portal_code_requested', [
				'delivery_id' => $deliveryId,
				'code_id' => $codeId,
			]);
			$connection->commit();

			return $codeId > 0 ? $codeId : null;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @brief Invalidates a code that could not be queued safely. */
	public function InvalidateCode(int $codeId): void
	{
		$statement = $this->_database->GetConnection()->prepare(<<<'SQL'
			UPDATE recipient_portal_codes
			SET invalidated_at = COALESCE(invalidated_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP()
			WHERE id = :id AND used_at IS NULL
		SQL);
		$statement->execute(['id' => $codeId]);
	}

	/**
	 * @brief Verifies and consumes the newest active code for a portal token.
	 * @param string $rawToken Raw portal token.
	 * @param string $normalizedCode Normalized eight-character access code.
	 * @return array<string, mixed>|null Active delivery on success, otherwise null.
	 */
	public function VerifyAccessCode(string $rawToken, string $normalizedCode): ?array
	{
		if (preg_match('/^[a-f0-9]{64}$/i', $rawToken) !== 1 || preg_match('/^[a-z0-9]{8}$/', $normalizedCode) !== 1)
		{
			return null;
		}

		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$deliveryStatement = $connection->prepare(<<<'SQL'
				SELECT rrd.id AS delivery_id, rrd.monitor_id, m.user_id
				FROM recipient_release_deliveries rrd
				INNER JOIN monitors m ON m.id = rrd.monitor_id
				WHERE rrd.portal_token_hash = :token_hash
					AND rrd.status = 'sent'
					AND rrd.portal_released_at IS NOT NULL
					AND rrd.portal_revoked_at IS NULL
					AND (rrd.portal_expires_at IS NULL OR rrd.portal_expires_at > UTC_TIMESTAMP())
				FOR UPDATE
			SQL);
			$deliveryStatement->execute(['token_hash' => hash('sha256', $rawToken)]);
			$delivery = $deliveryStatement->fetch(PDO::FETCH_ASSOC);

			if (!is_array($delivery))
			{
				$connection->commit();
				return null;
			}

			$codeStatement = $connection->prepare(<<<'SQL'
				SELECT *
				FROM recipient_portal_codes
				WHERE recipient_delivery_id = :delivery_id
					AND used_at IS NULL
					AND invalidated_at IS NULL
				ORDER BY id DESC
				LIMIT 1
				FOR UPDATE
			SQL);
			$codeStatement->execute(['delivery_id' => (int)$delivery['delivery_id']]);
			$code = $codeStatement->fetch(PDO::FETCH_ASSOC);

			if (!is_array($code) || strtotime((string)$code['expires_at'] . ' UTC') <= time())
			{
				if (is_array($code))
				{
					$expire = $connection->prepare('UPDATE recipient_portal_codes SET invalidated_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = :id');
					$expire->execute(['id' => (int)$code['id']]);
				}
				$connection->commit();
				return null;
			}

			$valid = password_verify($normalizedCode, (string)$code['code_hash']);

			if (!$valid)
			{
				$attempts = (int)$code['attempt_count'] + 1;
				$update = $connection->prepare(<<<'SQL'
					UPDATE recipient_portal_codes
					SET attempt_count = :attempt_count,
						invalidated_at = :invalidated_at,
						updated_at = UTC_TIMESTAMP()
					WHERE id = :id
				SQL);
				$update->execute([
					'attempt_count' => $attempts,
					'invalidated_at' => $attempts >= self::CODE_MAX_ATTEMPTS ? gmdate('Y-m-d H:i:s') : null,
					'id' => (int)$code['id'],
				]);
				$connection->commit();
				return null;
			}

			$now = gmdate('Y-m-d H:i:s');
			$consume = $connection->prepare('UPDATE recipient_portal_codes SET used_at = :used_at, updated_at = :updated_at WHERE id = :id');
			$consume->execute(['used_at' => $now, 'updated_at' => $now, 'id' => (int)$code['id']]);
			$touch = $connection->prepare('UPDATE recipient_release_deliveries SET portal_last_access_at = :accessed_at, updated_at = :updated_at WHERE id = :id');
			$touch->execute(['accessed_at' => $now, 'updated_at' => $now, 'id' => (int)$delivery['delivery_id']]);
			$this->InsertAudit($connection, (int)$delivery['user_id'], (int)$delivery['monitor_id'], 'recipient.portal_access_granted', [
				'delivery_id' => (int)$delivery['delivery_id'],
			]);
			$connection->commit();

			return $this->FindActiveByToken($rawToken);
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Returns the immutable document snapshot for one recipient delivery.
	 * @return array<int, array<string, mixed>> Snapshot documents ordered as staged.
	 */
	public function FindDocumentsForDelivery(int $deliveryId): array
	{
		$statement = $this->_database->GetConnection()->prepare(<<<'SQL'
			SELECT
				id, recipient_delivery_id, source_document_id, title, description, storage_type,
				text_content, stored_filename, original_filename, mime_type, file_size_bytes, created_at
			FROM recipient_delivery_documents
			WHERE recipient_delivery_id = :delivery_id
			ORDER BY id ASC
		SQL);
		$statement->execute(['delivery_id' => $deliveryId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Returns the immutable release-level location trail for an authenticated delivery.
	 * @return array<int, array<string, mixed>> Chronological location snapshots.
	 */
	public function FindLocationsForDelivery(int $deliveryId): array
	{
		$statement = $this->_database->GetConnection()->prepare(<<<'SQL'
			SELECT
				rrl.sequence_number,
				rrl.latitude,
				rrl.longitude,
				rrl.accuracy_meters,
				rrl.address_label,
				rrl.checked_in_at
			FROM recipient_release_deliveries rrd
			INNER JOIN recipient_release_locations rrl ON rrl.release_id = rrd.release_id
			WHERE rrd.id = :delivery_id
			ORDER BY rrl.sequence_number ASC
		SQL);
		$statement->execute(['delivery_id' => $deliveryId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Finds one immutable document snapshot belonging to a recipient delivery.
	 * @return array<string, mixed>|null Snapshot row or null.
	 */
	public function FindDocumentForDelivery(int $deliveryId, int $snapshotDocumentId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare(<<<'SQL'
			SELECT
				id, recipient_delivery_id, source_document_id, title, description, storage_type,
				text_content, stored_filename, original_filename, mime_type, file_size_bytes, created_at
			FROM recipient_delivery_documents
			WHERE id = :id AND recipient_delivery_id = :delivery_id
			LIMIT 1
		SQL);
		$statement->execute([
			'id' => $snapshotDocumentId,
			'delivery_id' => $deliveryId,
		]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/** @brief Records one recipient document download without storing document content. */
	public function RecordDocumentDownload(int $deliveryId, int $snapshotDocumentId): void
	{
		$statement = $this->_database->GetConnection()->prepare(<<<'SQL'
			SELECT rrd.monitor_id, m.user_id
			FROM recipient_release_deliveries rrd
			INNER JOIN monitors m ON m.id = rrd.monitor_id
			INNER JOIN recipient_delivery_documents rdd ON rdd.recipient_delivery_id = rrd.id
			WHERE rrd.id = :delivery_id AND rdd.id = :document_id
			LIMIT 1
		SQL);
		$statement->execute([
			'delivery_id' => $deliveryId,
			'document_id' => $snapshotDocumentId,
		]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		if (!is_array($row))
		{
			return;
		}

		$this->InsertAudit(
			$this->_database->GetConnection(),
			(int)$row['user_id'],
			(int)$row['monitor_id'],
			'recipient.portal_document_downloaded',
			['delivery_id' => $deliveryId, 'document_snapshot_id' => $snapshotDocumentId]
		);
	}

	/** @brief Records a recipient "download all" action. */
	public function RecordDownloadAll(int $deliveryId, int $documentCount): void
	{
		$statement = $this->_database->GetConnection()->prepare(<<<'SQL'
			SELECT rrd.monitor_id, m.user_id
			FROM recipient_release_deliveries rrd
			INNER JOIN monitors m ON m.id = rrd.monitor_id
			WHERE rrd.id = :delivery_id
			LIMIT 1
		SQL);
		$statement->execute(['delivery_id' => $deliveryId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		if (!is_array($row))
		{
			return;
		}

		$this->InsertAudit(
			$this->_database->GetConnection(),
			(int)$row['user_id'],
			(int)$row['monitor_id'],
			'recipient.portal_all_documents_downloaded',
			['delivery_id' => $deliveryId, 'document_count' => $documentCount]
		);
	}

	/**
	 * @brief Revokes one sent recipient portal delivery owned by the authenticated user.
	 * @return bool True when access was newly revoked.
	 */
	public function RevokeForUser(int $deliveryId, int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$lookup = $connection->prepare(<<<'SQL'
				SELECT rrd.id, rrd.monitor_id
				FROM recipient_release_deliveries rrd
				INNER JOIN monitors m ON m.id = rrd.monitor_id
				WHERE rrd.id = :id AND m.user_id = :user_id
				FOR UPDATE
			SQL);
			$lookup->execute(['id' => $deliveryId, 'user_id' => $userId]);
			$row = $lookup->fetch(PDO::FETCH_ASSOC);

			if (!is_array($row))
			{
				$connection->commit();
				return false;
			}

			$revoke = $connection->prepare(<<<'SQL'
				UPDATE recipient_release_deliveries
				SET portal_revoked_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
				WHERE id = :id AND portal_revoked_at IS NULL AND portal_token_hash IS NOT NULL
			SQL);
			$revoke->execute(['id' => $deliveryId]);
			$newlyRevoked = $revoke->rowCount() === 1;
			$invalidate = $connection->prepare(<<<'SQL'
				UPDATE recipient_portal_codes
				SET invalidated_at = COALESCE(invalidated_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP()
				WHERE recipient_delivery_id = :delivery_id AND used_at IS NULL
			SQL);
			$invalidate->execute(['delivery_id' => $deliveryId]);
			$cancel = $connection->prepare(<<<'SQL'
				UPDATE mail_queue
				SET status = 'cancelled', body_text = '[Access code redacted after cancellation]',
					cancelled_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
				WHERE recipient_delivery_id = :delivery_id
					AND mail_type = 'recipient_access_code'
					AND status IN ('queued', 'retrying', 'failed')
			SQL);
			$cancel->execute(['delivery_id' => $deliveryId]);

			if ($newlyRevoked)
			{
				$this->InsertAudit($connection, $userId, (int)$row['monitor_id'], 'recipient.portal_revoked', [
					'delivery_id' => $deliveryId,
				]);
			}

			$connection->commit();
			return $newlyRevoked;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Permanently closes one non-expiring delivery at the authenticated recipient's request.
	 * @return bool True when access was newly closed.
	 */
	public function CloseByRecipient(string $rawToken, int $deliveryId): bool
	{
		if (preg_match('/^[a-f0-9]{64}$/i', $rawToken) !== 1)
		{
			return false;
		}

		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$lookup = $connection->prepare(<<<'SQL'
				SELECT rrd.id, rrd.monitor_id, m.user_id
				FROM recipient_release_deliveries rrd
				INNER JOIN monitors m ON m.id = rrd.monitor_id
				WHERE rrd.id = :id
					AND rrd.portal_token_hash = :token_hash
					AND rrd.status = 'sent'
					AND rrd.portal_released_at IS NOT NULL
					AND rrd.portal_revoked_at IS NULL
					AND rrd.portal_expires_at IS NULL
				FOR UPDATE
			SQL);
			$lookup->execute([
				'id' => $deliveryId,
				'token_hash' => hash('sha256', $rawToken),
			]);
			$row = $lookup->fetch(PDO::FETCH_ASSOC);

			if (!is_array($row))
			{
				$connection->commit();
				return false;
			}

			$close = $connection->prepare(<<<'SQL'
				UPDATE recipient_release_deliveries
				SET portal_revoked_at = UTC_TIMESTAMP(),
					portal_closed_by_recipient_at = UTC_TIMESTAMP(),
					updated_at = UTC_TIMESTAMP()
				WHERE id = :id AND portal_revoked_at IS NULL
			SQL);
			$close->execute(['id' => $deliveryId]);
			$newlyClosed = $close->rowCount() === 1;

			$invalidate = $connection->prepare(<<<'SQL'
				UPDATE recipient_portal_codes
				SET invalidated_at = COALESCE(invalidated_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP()
				WHERE recipient_delivery_id = :delivery_id AND used_at IS NULL
			SQL);
			$invalidate->execute(['delivery_id' => $deliveryId]);

			$cancel = $connection->prepare(<<<'SQL'
				UPDATE mail_queue
				SET status = 'cancelled', body_text = '[Access code redacted after cancellation]',
					cancelled_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
				WHERE recipient_delivery_id = :delivery_id
					AND mail_type = 'recipient_access_code'
					AND status IN ('queued', 'retrying', 'failed')
			SQL);
			$cancel->execute(['delivery_id' => $deliveryId]);

			if ($newlyClosed)
			{
				$this->InsertAudit($connection, (int)$row['user_id'], (int)$row['monitor_id'], 'recipient.portal_closed_by_recipient', [
					'delivery_id' => $deliveryId,
				]);
			}

			$connection->commit();
			return $newlyClosed;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @brief Appends a content-free portal audit event. */
	private function InsertAudit(PDO $connection, int $userId, int $monitorId, string $eventType, array $context): void
	{
		$statement = $connection->prepare(<<<'SQL'
			INSERT INTO audit_log
			(user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES (:user_id, :event_type, 'monitor', :monitor_id, :message, :context_json, UTC_TIMESTAMP())
		SQL);
		$statement->execute([
			'user_id' => $userId,
			'event_type' => $eventType,
			'monitor_id' => $monitorId,
			'message' => $eventType,
			'context_json' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
		]);
	}
}
