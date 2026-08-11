<?php

/**
 * @file MessageRepository.php
 * @brief Repository for monitor default and recipient-specific messages.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;
use RuntimeException;
use Throwable;

/**
 * @brief Persists a monitor's delivery messages as one atomic configuration.
 */
class MessageRepository
{
	private Database $_database;

	/** @brief Constructs the repository. @param Database $database Database service. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Returns recipient-specific messages keyed by monitor-contact ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @return array<int, array<string, string>>
	 */
	public function FindByMonitorIdForUser(int $monitorId, int $userId): array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT cm.monitor_contact_id, cm.subject, cm.body_text
			FROM contact_messages cm
			INNER JOIN monitor_contacts mc ON mc.id = cm.monitor_contact_id
			INNER JOIN monitors m ON m.id = mc.monitor_id
			WHERE mc.monitor_id = :monitor_id
			  AND m.user_id = :user_id
			ORDER BY mc.sort_order ASC, mc.id ASC
		');
		$statement->execute([
			'monitor_id' => $monitorId,
			'user_id' => $userId,
		]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		$result = [];

		foreach (is_array($rows) ? $rows : [] as $row)
		{
			$result[(int)$row['monitor_contact_id']] = [
				'subject' => (string)$row['subject'],
				'body_text' => (string)$row['body_text'],
			];
		}

		return $result;
	}

	/**
	 * @brief Replaces the complete message configuration for an owned monitor.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId Owner user ID.
	 * @param string|null $defaultSubject Default subject.
	 * @param string|null $defaultBody Default body.
	 * @param array<int, array{subject: string, body_text: string}> $overrides Overrides keyed by monitor-contact ID.
	 */
	public function ReplaceForMonitor(
		int $monitorId,
		int $userId,
		?string $defaultSubject,
		?string $defaultBody,
		array $overrides
	): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$monitorStatement = $connection->prepare('
				SELECT id
				FROM monitors
				WHERE id = :monitor_id AND user_id = :user_id
				FOR UPDATE
			');
			$monitorStatement->execute([
				'monitor_id' => $monitorId,
				'user_id' => $userId,
			]);

			if ($monitorStatement->fetchColumn() === false)
			{
				throw new RuntimeException('Owned monitor not found during message update.');
			}

			$updateMonitor = $connection->prepare('
				UPDATE monitors
				SET default_message_subject = :default_subject,
					default_message_body = :default_body,
					updated_at = UTC_TIMESTAMP()
				WHERE id = :monitor_id AND user_id = :user_id
			');
			$updateMonitor->execute([
				'default_subject' => $defaultSubject,
				'default_body' => $defaultBody,
				'monitor_id' => $monitorId,
				'user_id' => $userId,
			]);

			$assignmentStatement = $connection->prepare('
				SELECT id
				FROM monitor_contacts
				WHERE monitor_id = :monitor_id
			');
			$assignmentStatement->execute(['monitor_id' => $monitorId]);
			$allowedIds = array_map('intval', $assignmentStatement->fetchAll(PDO::FETCH_COLUMN));
			$overrides = array_filter(
				$overrides,
				static fn (int $monitorContactId): bool => in_array($monitorContactId, $allowedIds, true),
				ARRAY_FILTER_USE_KEY
			);

			if ($overrides === [])
			{
				$delete = $connection->prepare('
					DELETE cm
					FROM contact_messages cm
					INNER JOIN monitor_contacts mc ON mc.id = cm.monitor_contact_id
					WHERE mc.monitor_id = :monitor_id
				');
				$delete->execute(['monitor_id' => $monitorId]);
			}
			else
			{
				$ids = array_keys($overrides);
				$placeholders = implode(',', array_fill(0, count($ids), '?'));
				$delete = $connection->prepare('
					DELETE cm
					FROM contact_messages cm
					INNER JOIN monitor_contacts mc ON mc.id = cm.monitor_contact_id
					WHERE mc.monitor_id = ?
					  AND cm.monitor_contact_id NOT IN (' . $placeholders . ')
				');
				$delete->execute([$monitorId, ...$ids]);
			}

			$upsert = $connection->prepare('
				INSERT INTO contact_messages (monitor_contact_id, subject, body_text)
				VALUES (:monitor_contact_id, :subject, :body_text)
				ON DUPLICATE KEY UPDATE
					subject = VALUES(subject),
					body_text = VALUES(body_text)
			');

			foreach ($overrides as $monitorContactId => $message)
			{
				$upsert->execute([
					'monitor_contact_id' => $monitorContactId,
					'subject' => $message['subject'],
					'body_text' => $message['body_text'],
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
}
