<?php

/**
 * @file RecipientRepository.php
 * @brief Dedicated per-monitor recipient configuration and immutable delivery history.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;
use RuntimeException;
use Throwable;

/**
 * @brief Keeps reusable contacts separate from their monitor-specific delivery configuration.
 */
final class RecipientRepository
{
	private Database $_database;

	/** @brief Constructs the repository. @param Database $database Database service. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Finds one monitor-recipient assignment owned by a user.
	 * @return array<string, mixed>|null
	 */
	public function FindByIdForUser(int $monitorContactId, int $userId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT
				mc.id,
				mc.monitor_id,
				mc.contact_id,
				mc.sort_order,
				m.name AS monitor_name,
				u.display_name AS owner_name,
				mmt.subject AS default_message_subject,
				mmt.body_text AS default_message_body,
				mpt.message_text AS default_portal_message,
				mpt.intro_text AS default_portal_intro,
				cpm.id AS portal_override_id,
				cpm.body_text AS portal_override_body,
				c.name,
				c.email,
				c.notification_locale,
				c.email_checked_at,
				cm.id AS override_message_id,
				cm.subject AS override_subject,
				cm.body_text AS override_body,
				EXISTS
				(
					SELECT 1
					FROM recipient_release_deliveries rrd
					INNER JOIN recipient_releases rr ON rr.id = rrd.release_id
					WHERE rrd.monitor_id = mc.monitor_id
						AND rrd.contact_id = mc.contact_id
						AND rr.status IN (\'pending\', \'partial\', \'failed\')
				) AS release_in_progress
			FROM monitor_contacts mc
			INNER JOIN monitors m ON m.id = mc.monitor_id
			INNER JOIN users u ON u.id = m.user_id
			INNER JOIN contacts c ON c.id = mc.contact_id
			LEFT JOIN contact_messages cm ON cm.monitor_contact_id = mc.id
			LEFT JOIN monitor_mail_templates mmt
				ON mmt.monitor_id = m.id
				AND mmt.template_key = \'recipient_default\'
				AND mmt.locale = c.notification_locale
			WHERE mc.id = :id AND m.user_id = :user_id
			LIMIT 1
		');
		$statement->execute(['id' => $monitorContactId, 'user_id' => $userId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Adds an existing owned contact as a monitor recipient.
	 * @return int Monitor-contact assignment ID.
	 */
	public function AddForMonitor(int $monitorId, int $contactId, int $userId): int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$ownership = $connection->prepare('
				SELECT COUNT(*)
				FROM monitors m
				INNER JOIN contacts c ON c.user_id = m.user_id
				WHERE m.id = :monitor_id AND c.id = :contact_id AND m.user_id = :user_id
			');
			$ownership->execute([
				'monitor_id' => $monitorId,
				'contact_id' => $contactId,
				'user_id' => $userId,
			]);

			if ((int)$ownership->fetchColumn() !== 1)
			{
				throw new RuntimeException('Owned monitor or contact not found.');
			}

			$insert = $connection->prepare('
				INSERT INTO monitor_contacts (monitor_id, contact_id, sort_order)
				SELECT :monitor_id, :contact_id, COALESCE(MAX(sort_order), 0) + 1
				FROM monitor_contacts
				WHERE monitor_id = :sort_monitor_id
				ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
			');
			$insert->execute([
				'monitor_id' => $monitorId,
				'contact_id' => $contactId,
				'sort_monitor_id' => $monitorId,
			]);

			$lookup = $connection->prepare('
				SELECT id FROM monitor_contacts
				WHERE monitor_id = :monitor_id AND contact_id = :contact_id
				LIMIT 1
			');
			$lookup->execute(['monitor_id' => $monitorId, 'contact_id' => $contactId]);
			$monitorContactId = (int)$lookup->fetchColumn();

			if ($monitorContactId <= 0)
			{
				throw new RuntimeException('Recipient assignment could not be resolved.');
			}

			$connection->commit();
			return $monitorContactId;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @brief Removes a monitor recipient while preserving immutable release snapshots. */
	public function RemoveForUser(int $monitorContactId, int $userId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('
			DELETE mc
			FROM monitor_contacts mc
			INNER JOIN monitors m ON m.id = mc.monitor_id
			WHERE mc.id = :id AND m.user_id = :user_id
		');
		$statement->execute(['id' => $monitorContactId, 'user_id' => $userId]);

		return $statement->rowCount() === 1;
	}

	/**
	 * @brief Atomically updates one recipient's message choice and document assignments.
	 * @param array<int> $documentIds Document IDs belonging to the recipient's monitor.
	 */
	public function UpdateConfigurationForUser(
		int $monitorContactId,
		int $userId,
		bool $useOverride,
		string $subject,
		string $bodyText,
		bool $usePortalOverride,
		string $portalBodyText,
		array $documentIds
	): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$assignment = $connection->prepare('
				SELECT mc.id, mc.monitor_id
				FROM monitor_contacts mc
				INNER JOIN monitors m ON m.id = mc.monitor_id
				WHERE mc.id = :id AND m.user_id = :user_id
				FOR UPDATE
			');
			$assignment->execute(['id' => $monitorContactId, 'user_id' => $userId]);
			$row = $assignment->fetch(PDO::FETCH_ASSOC);

			if (!is_array($row))
			{
				throw new RuntimeException('Owned monitor recipient not found.');
			}

			if ($useOverride)
			{
				$upsert = $connection->prepare('
					INSERT INTO contact_messages (monitor_contact_id, subject, body_text)
					VALUES (:monitor_contact_id, :subject, :body_text)
					ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_text = VALUES(body_text)
				');
				$upsert->execute([
					'monitor_contact_id' => $monitorContactId,
					'subject' => $subject,
					'body_text' => $bodyText,
				]);
			}
			else
			{
				$deleteMessage = $connection->prepare('DELETE FROM contact_messages WHERE monitor_contact_id = :id');
				$deleteMessage->execute(['id' => $monitorContactId]);
			}

			if ($usePortalOverride)
			{
				$upsertPortal = $connection->prepare('
					INSERT INTO contact_portal_messages (monitor_contact_id, body_text)
					VALUES (:monitor_contact_id, :body_text)
					ON DUPLICATE KEY UPDATE body_text = VALUES(body_text)
				');
				$upsertPortal->execute([
					'monitor_contact_id' => $monitorContactId,
					'body_text' => $portalBodyText,
				]);
			}
			else
			{
				$deletePortal = $connection->prepare('DELETE FROM contact_portal_messages WHERE monitor_contact_id = :id');
				$deletePortal->execute(['id' => $monitorContactId]);
			}

			$allowed = $connection->prepare('SELECT id FROM documents WHERE monitor_id = :monitor_id');
			$allowed->execute(['monitor_id' => (int)$row['monitor_id']]);
			$allowedIds = array_map('intval', $allowed->fetchAll(PDO::FETCH_COLUMN));
			$documentIds = array_values(array_filter(
				array_unique(array_map('intval', $documentIds)),
				static fn (int $documentId): bool => in_array($documentId, $allowedIds, true)
			));

			$deleteDocuments = $connection->prepare('
				DELETE FROM document_monitor_contacts WHERE monitor_contact_id = :monitor_contact_id
			');
			$deleteDocuments->execute(['monitor_contact_id' => $monitorContactId]);
			$insertDocument = $connection->prepare('
				INSERT INTO document_monitor_contacts (document_id, monitor_contact_id)
				VALUES (:document_id, :monitor_contact_id)
			');

			foreach ($documentIds as $documentId)
			{
				$insertDocument->execute([
					'document_id' => $documentId,
					'monitor_contact_id' => $monitorContactId,
				]);
			}

			$connection->commit();
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @return array<int> @brief Returns document IDs assigned to one monitor recipient. */
	public function FindAssignedDocumentIdsForUser(int $monitorContactId, int $userId): array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT dmc.document_id
			FROM document_monitor_contacts dmc
			INNER JOIN monitor_contacts mc ON mc.id = dmc.monitor_contact_id
			INNER JOIN monitors m ON m.id = mc.monitor_id
			WHERE dmc.monitor_contact_id = :id AND m.user_id = :user_id
			ORDER BY dmc.document_id ASC
		');
		$statement->execute(['id' => $monitorContactId, 'user_id' => $userId]);
		$rows = $statement->fetchAll(PDO::FETCH_COLUMN);

		return is_array($rows) ? array_map('intval', $rows) : [];
	}

	/** @return array<int, array<string, mixed>> @brief Returns immutable delivery history for one recipient assignment. */
	public function FindDeliveryHistoryForUser(int $monitorContactId, int $userId, int $limit = 50): array
	{
		$recipient = $this->FindByIdForUser($monitorContactId, $userId);

		if (!is_array($recipient))
		{
			return [];
		}

		$limit = max(1, min(100, $limit));
		$statement = $this->_database->GetConnection()->prepare('
			SELECT rrd.id, rrd.status, rrd.recipient_email, rrd.sent_at, rrd.failed_at, rrd.created_at,
				rrd.portal_released_at, rrd.portal_expires_at, rrd.portal_revoked_at,
				CASE
					WHEN rrd.portal_revoked_at IS NOT NULL THEN \'revoked\'
					WHEN rrd.portal_released_at IS NULL THEN \'not_released\'
					WHEN rrd.portal_expires_at IS NOT NULL AND rrd.portal_expires_at <= UTC_TIMESTAMP() THEN \'expired\'
					ELSE \'available\'
				END AS portal_status,
				rr.status AS release_status
			FROM recipient_release_deliveries rrd
			INNER JOIN recipient_releases rr ON rr.id = rrd.release_id
			WHERE rrd.monitor_id = :monitor_id AND rrd.contact_id = :contact_id
			ORDER BY rrd.id DESC
			LIMIT ' . $limit . '
		');
		$statement->execute([
			'monitor_id' => (int)$recipient['monitor_id'],
			'contact_id' => (int)$recipient['contact_id'],
		]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}
}
