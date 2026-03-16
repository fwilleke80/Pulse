<?php

/**
 * @file DocumentRepository.php
 * @brief Repository for monitor documents and their recipient assignments.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;

/**
 * @brief Repository for monitor documents and their recipient assignments.
 */
class DocumentRepository
{
	private Database $_database;

	/**
	 * @brief Constructs the repository.
	 * @param Database $database Database service.
	 */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Returns all documents for a monitor owned by a user.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindAllByMonitorIdForUser(int $monitorId, int $userId): array
	{
		$sql = '
			SELECT
				d.id,
				d.monitor_id,
				d.title,
				d.storage_type,
				d.text_content,
				d.stored_filename,
				d.original_filename,
				d.mime_type,
				d.file_size_bytes,
				d.created_at,
				d.updated_at
			FROM documents d
			INNER JOIN monitors m
				ON m.id = d.monitor_id
			WHERE d.monitor_id = :monitor_id
			  AND m.user_id = :user_id
			ORDER BY d.created_at DESC, d.id DESC
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'monitor_id' => $monitorId,
			'user_id' => $userId,
		]);

		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Finds a document by ID for a monitor owned by a user.
	 * @param int $documentId Document ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $userId User ID.
	 * @return array<string, mixed>|null
	 */
	public function FindByIdForMonitorAndUser(int $documentId, int $monitorId, int $userId): ?array
	{
		$sql = '
			SELECT
				d.id,
				d.monitor_id,
				d.title,
				d.storage_type,
				d.text_content,
				d.stored_filename,
				d.original_filename,
				d.mime_type,
				d.file_size_bytes,
				d.created_at,
				d.updated_at
			FROM documents d
			INNER JOIN monitors m
				ON m.id = d.monitor_id
			WHERE d.id = :document_id
			  AND d.monitor_id = :monitor_id
			  AND m.user_id = :user_id
			LIMIT 1
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'document_id' => $documentId,
			'monitor_id' => $monitorId,
			'user_id' => $userId,
		]);

		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Creates a file document for a monitor.
	 * @param int $monitorId Monitor ID.
	 * @param string $title Document title.
	 * @param string $storedFilename Stored unique filename.
	 * @param string $originalFilename Original uploaded filename.
	 * @param string $mimeType MIME type.
	 * @param int $fileSizeBytes File size in bytes.
	 * @return int
	 */
	public function CreateFileDocumentForMonitor(
		int $monitorId,
		string $title,
		string $storedFilename,
		string $originalFilename,
		string $mimeType,
		int $fileSizeBytes
	): int
	{
		$sql = '
			INSERT INTO documents
			(
				monitor_id,
				title,
				storage_type,
				text_content,
				stored_filename,
				original_filename,
				mime_type,
				file_size_bytes,
				created_at,
				updated_at
			)
			VALUES
			(
				:monitor_id,
				:title,
				:file_storage_type,
				NULL,
				:stored_filename,
				:original_filename,
				:mime_type,
				:file_size_bytes,
				NOW(),
				NOW()
			)
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'monitor_id' => $monitorId,
			'title' => $title,
			'file_storage_type' => 'file',
			'stored_filename' => $storedFilename,
			'original_filename' => $originalFilename,
			'mime_type' => $mimeType,
			'file_size_bytes' => $fileSizeBytes,
		]);

		return (int)$this->_database->GetConnection()->lastInsertId();
	}

	/**
	 * @brief Deletes a document by ID.
	 * @param int $documentId Document ID.
	 */
	public function DeleteById(int $documentId): void
	{
		$sql = '
			DELETE FROM documents
			WHERE id = :document_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'document_id' => $documentId,
		]);
	}

	/**
	 * @brief Returns assigned monitor_contact IDs for a document.
	 * @param int $documentId Document ID.
	 * @return array<int>
	 */
	public function FindAssignedMonitorContactIds(int $documentId): array
	{
		$sql = '
			SELECT monitor_contact_id
			FROM document_monitor_contacts
			WHERE document_id = :document_id
			ORDER BY monitor_contact_id ASC
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'document_id' => $documentId,
		]);

		$rows = $statement->fetchAll(PDO::FETCH_COLUMN);

		if (!is_array($rows))
		{
			return [];
		}

		return array_map('intval', $rows);
	}

	/**
	 * @brief Replaces all recipient assignments for a document.
	 * @param int $documentId Document ID.
	 * @param array<int> $monitorContactIds Monitor-contact IDs.
	 */
	public function ReplaceRecipientsForDocument(int $documentId, array $monitorContactIds): void
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$deleteSql = '
				DELETE FROM document_monitor_contacts
				WHERE document_id = :document_id
			';

			$deleteStatement = $connection->prepare($deleteSql);
			$deleteStatement->execute([
				'document_id' => $documentId,
			]);

			if ($monitorContactIds !== [])
			{
				$insertSql = '
					INSERT INTO document_monitor_contacts
					(
						document_id,
						monitor_contact_id
					)
					VALUES
					(
						:document_id,
						:monitor_contact_id
					)
				';

				$insertStatement = $connection->prepare($insertSql);

				foreach ($monitorContactIds as $monitorContactId)
				{
					$insertStatement->execute([
						'document_id' => $documentId,
						'monitor_contact_id' => $monitorContactId,
					]);
				}
			}

			$connection->commit();
		}
		catch (\Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}
}