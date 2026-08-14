<?php

/**
 * @file DocumentService.php
 * @brief Safe document upload, deletion, monitor cleanup, and download preparation.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use finfo;
use Pulse\Core\DocumentException;
use Pulse\Core\Logger;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\MonitorRepository;

/**
 * @brief Keeps document filesystem rules out of HTTP controllers.
 */
class DocumentService
{
	private DocumentRepository $_documentRepository;
	private MonitorRepository $_monitorRepository;
	private Logger $_logger;
	private string $_storageDirectory;
	private int $_maximumBytes;

	/** @var array<int, string> */
	private array $_allowedMimeTypes;

	/**
	 * @brief Constructs the document service.
	 * @param DocumentRepository $documentRepository Document repository.
	 * @param MonitorRepository $monitorRepository Monitor repository.
	 * @param Logger $logger Logger.
	 * @param string $storageDirectory Absolute private storage directory.
	 * @param array<string, mixed> $uploadConfig Upload policy.
	 */
	public function __construct(
		DocumentRepository $documentRepository,
		MonitorRepository $monitorRepository,
		Logger $logger,
		string $storageDirectory,
		array $uploadConfig
	)
	{
		$this->_documentRepository = $documentRepository;
		$this->_monitorRepository = $monitorRepository;
		$this->_logger = $logger;
		$this->_storageDirectory = rtrim($storageDirectory, '/');
		$this->_maximumBytes = (int)($uploadConfig['maximum_bytes'] ?? 26214400);
		$this->_allowedMimeTypes = array_values((array)($uploadConfig['allowed_mime_types'] ?? []));
	}

	/**
	 * @brief Validates and stores one uploaded file document.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @param string $title Requested title.
	 * @param string $description Optional document description.
	 * @param array<string, mixed> $file PHP upload entry.
	 * @param array<int> $recipientIds Requested monitor-contact IDs.
	 * @return int New document ID.
	 */
	public function UploadForUser(
		int $userId,
		int $monitorId,
		string $title,
		string $description,
		array $file,
		array $recipientIds
	): int
	{
		if ($this->_monitorRepository->FindByIdForUser($monitorId, $userId) === null)
		{
			throw new DocumentException('monitors.documents.flash.monitor_not_found');
		}

		$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

		if ($error === UPLOAD_ERR_NO_FILE)
		{
			throw new DocumentException('monitors.documents.flash.file_required');
		}

		if ($error !== UPLOAD_ERR_OK)
		{
			throw new DocumentException('monitors.documents.flash.upload_failed');
		}

		$tmpName = (string)($file['tmp_name'] ?? '');
		$originalFilename = $this->NormalizeOriginalFilename((string)($file['name'] ?? ''));

		if ($tmpName === '' || $originalFilename === '' || !is_uploaded_file($tmpName))
		{
			throw new DocumentException('monitors.documents.flash.upload_failed');
		}

		$actualSize = filesize($tmpName);

		if (!is_int($actualSize) || $actualSize <= 0)
		{
			throw new DocumentException('monitors.documents.flash.empty_file');
		}

		if ($actualSize > $this->_maximumBytes)
		{
			throw new DocumentException('monitors.documents.flash.file_too_large');
		}

		$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);

		if (!is_string($mimeType) || !in_array($mimeType, $this->_allowedMimeTypes, true))
		{
			throw new DocumentException('monitors.documents.flash.file_type_not_allowed');
		}

		$allowedRecipientIds = $this->FilterRecipientIds($userId, $monitorId, $recipientIds);
		$storedFilename = bin2hex(random_bytes(32)) . '.bin';
		$this->EnsureStorageDirectory();
		$targetPath = $this->_storageDirectory . '/' . $storedFilename;

		if (!move_uploaded_file($tmpName, $targetPath))
		{
			throw new DocumentException('monitors.documents.flash.upload_failed');
		}

		if (!@chmod($targetPath, 0600))
		{
			@unlink($targetPath);
			throw new DocumentException('monitors.documents.flash.upload_failed');
		}

		try
		{
			$documentId = $this->_documentRepository->CreateFileDocumentForMonitor(
				$monitorId,
				$title !== '' ? $title : $originalFilename,
				trim($description) !== '' ? $description : null,
				$storedFilename,
				$originalFilename,
				$mimeType,
				$actualSize
			);
			$this->_documentRepository->ReplaceRecipientsForDocument($documentId, $allowedRecipientIds);
		}
		catch (\Throwable $throwable)
		{
			@unlink($targetPath);
			throw $throwable;
		}

		$this->_logger->Info('Document uploaded', [
			'user_id' => $userId,
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
		]);

		return $documentId;
	}

	/**
	 * @brief Creates an editable text document and its recipient assignments.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @param string $title Document title.
	 * @param string $textContent Document content.
	 * @param array<int> $recipientIds Requested monitor-contact IDs.
	 * @return int New document ID.
	 */
	public function CreateTextForUser(
		int $userId,
		int $monitorId,
		string $title,
		string $textContent,
		array $recipientIds
	): int
	{
		if ($this->_monitorRepository->FindByIdForUser($monitorId, $userId) === null)
		{
			throw new DocumentException('monitors.documents.flash.monitor_not_found');
		}

		if ($title === '' || trim($textContent) === '')
		{
			throw new DocumentException('monitors.documents.flash.text_required');
		}

		$documentId = $this->_documentRepository->CreateTextDocumentForMonitor($monitorId, $title, $textContent);
		$this->_documentRepository->ReplaceRecipientsForDocument(
			$documentId,
			$this->FilterRecipientIds($userId, $monitorId, $recipientIds)
		);
		$this->_logger->Info('Text document created', [
			'user_id' => $userId,
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
		]);

		return $documentId;
	}

	/**
	 * @brief Updates an editable text document and its recipients.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $documentId Document ID.
	 * @param string $title Document title.
	 * @param string $textContent Document content.
	 * @param array<int> $recipientIds Requested monitor-contact IDs.
	 */
	public function UpdateTextForUser(
		int $userId,
		int $monitorId,
		int $documentId,
		string $title,
		string $textContent,
		array $recipientIds
	): void
	{
		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, $userId);

		if ($document === null || (string)$document['storage_type'] !== 'text')
		{
			throw new DocumentException('monitors.documents.flash.document_not_found');
		}

		if ($title === '' || trim($textContent) === '')
		{
			throw new DocumentException('monitors.documents.flash.text_required');
		}

		$this->_documentRepository->UpdateTextDocument($documentId, $title, $textContent);
		$this->_documentRepository->ReplaceRecipientsForDocument(
			$documentId,
			$this->FilterRecipientIds($userId, $monitorId, $recipientIds)
		);
		$this->_logger->Info('Text document updated', [
			'user_id' => $userId,
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
		]);
	}

	/**
	 * @brief Updates an uploaded file's display metadata and recipient assignments.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $documentId Document ID.
	 * @param string $title Display title.
	 * @param string $description Optional short description.
	 * @param array<int> $recipientIds Requested monitor-contact IDs.
	 */
	public function UpdateFileForUser(
		int $userId,
		int $monitorId,
		int $documentId,
		string $title,
		string $description,
		array $recipientIds
	): void
	{
		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, $userId);

		if ($document === null || (string)$document['storage_type'] !== 'file')
		{
			throw new DocumentException('monitors.documents.flash.document_not_found');
		}

		if ($title === '')
		{
			throw new DocumentException('monitors.documents.flash.title_required');
		}

		$this->_documentRepository->UpdateFileDocumentMetadata(
			$documentId,
			$title,
			trim($description) !== '' ? $description : null
		);
		$this->_documentRepository->ReplaceRecipientsForDocument(
			$documentId,
			$this->FilterRecipientIds($userId, $monitorId, $recipientIds)
		);
		$this->_logger->Info('File document updated', [
			'user_id' => $userId,
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
		]);
	}

	/**
	 * @brief Replaces recipients after validating ownership and assignment scope.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $documentId Document ID.
	 * @param array<int> $recipientIds Requested monitor-contact IDs.
	 */
	public function UpdateRecipientsForUser(
		int $userId,
		int $monitorId,
		int $documentId,
		array $recipientIds
	): void
	{
		if ($this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, $userId) === null)
		{
			throw new DocumentException('monitors.documents.flash.document_not_found');
		}

		$this->_documentRepository->ReplaceRecipientsForDocument(
			$documentId,
			$this->FilterRecipientIds($userId, $monitorId, $recipientIds)
		);
	}

	/**
	 * @brief Deletes an owned document and its private stored file.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $documentId Document ID.
	 */
	public function DeleteForUser(int $userId, int $monitorId, int $documentId): void
	{
		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, $userId);

		if ($document === null)
		{
			throw new DocumentException('monitors.documents.flash.document_not_found');
		}

		$storedFilename = (string)($document['stored_filename'] ?? '');
		$preserveStoredFile = (string)($document['storage_type'] ?? '') === 'file'
			&& $this->_documentRepository->IsStoredFileReferencedByRecipientDelivery($storedFilename);
		$this->_documentRepository->DeleteById($documentId);

		if (!$preserveStoredFile)
		{
			$this->RemoveStoredFile($storedFilename);
		}
		else
		{
			$this->_logger->Info('Stored document retained for an immutable recipient delivery', [
				'monitor_id' => $monitorId,
				'document_id' => $documentId,
			]);
		}
		$this->_logger->Info('Document deleted', [
			'user_id' => $userId,
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
		]);
	}

	/**
	 * @brief Deletes an owned monitor and removes all of its physical files.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @return bool True when the monitor existed.
	 */
	public function DeleteMonitorForUser(int $userId, int $monitorId): bool
	{
		if ($this->_monitorRepository->FindByIdForUser($monitorId, $userId) === null)
		{
			return false;
		}

		$documents = $this->_documentRepository->FindAllByMonitorIdForUser($monitorId, $userId);
		$retainedStoredFiles = $this->_documentRepository->FindRecipientDeliveryStoredFilenamesForMonitor($monitorId);
		$this->_monitorRepository->DeleteForUser($monitorId, $userId);
		$storedFiles = $retainedStoredFiles;

		foreach ($documents as $document)
		{
			$storedFiles[] = (string)($document['stored_filename'] ?? '');
		}

		foreach (array_values(array_unique($storedFiles)) as $storedFilename)
		{
			$this->RemoveStoredFile($storedFilename);
		}

		return true;
	}

	/**
	 * @brief Returns document metadata and a verified private path for download.
	 * @param int $userId Owner user ID.
	 * @param int $monitorId Monitor ID.
	 * @param int $documentId Document ID.
	 * @return array{document: array<string, mixed>, path: string}
	 */
	public function PrepareDownloadForUser(int $userId, int $monitorId, int $documentId): array
	{
		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, $userId);

		if ($document === null || (string)$document['storage_type'] !== 'file')
		{
			throw new DocumentException('monitors.documents.flash.document_not_found');
		}

		$path = $this->ResolveStoredFile((string)($document['stored_filename'] ?? ''));

		if ($path === null)
		{
			$this->_logger->Error('Stored document file is missing', [
				'user_id' => $userId,
				'monitor_id' => $monitorId,
				'document_id' => $documentId,
			]);
			throw new DocumentException('monitors.documents.flash.document_not_found');
		}

		return ['document' => $document, 'path' => $path];
	}

	/**
	 * @brief Resolves one immutable portal snapshot to a downloadable file path when it represents an uploaded file.
	 * @param array<string, mixed> $snapshot Recipient-delivery document snapshot.
	 * @return string|null Absolute private path, or null for missing/non-file snapshots.
	 */
	public function ResolvePortalSnapshotFile(array $snapshot): ?string
	{
		if ((string)($snapshot['storage_type'] ?? '') !== 'file')
		{
			return null;
		}

		return $this->ResolveStoredFile((string)($snapshot['stored_filename'] ?? ''));
	}

	/** @brief Keeps only monitor contacts belonging to the owned monitor. @param int $userId User ID. @param int $monitorId Monitor ID. @param array<int> $recipientIds Requested IDs. @return array<int> */
	private function FilterRecipientIds(int $userId, int $monitorId, array $recipientIds): array
	{
		$monitorContacts = $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, $userId);
		$allowed = array_map(static fn (array $row): int => (int)$row['id'], $monitorContacts);

		return array_values(array_filter(
			array_unique($recipientIds),
			static fn (int $id): bool => in_array($id, $allowed, true)
		));
	}

	/** @brief Creates the private upload directory with restrictive permissions. */
	private function EnsureStorageDirectory(): void
	{
		if (!is_dir($this->_storageDirectory) && !@mkdir($this->_storageDirectory, 0700, true) && !is_dir($this->_storageDirectory))
		{
			throw new DocumentException('monitors.documents.flash.upload_failed');
		}
	}

	/** @brief Normalizes a client-supplied filename to harmless metadata. @param string $filename Original filename. @return string */
	private function NormalizeOriginalFilename(string $filename): string
	{
		$filename = str_replace('\\', '/', $filename);
		$filename = basename($filename);
		$filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';

		return substr(trim($filename), 0, 255);
	}

	/** @brief Resolves a stored basename without allowing path traversal. @param string $storedFilename Stored basename. @return string|null */
	private function ResolveStoredFile(string $storedFilename): ?string
	{
		if ($storedFilename === '' || basename($storedFilename) !== $storedFilename)
		{
			return null;
		}

		$path = $this->_storageDirectory . '/' . $storedFilename;

		return is_file($path) ? $path : null;
	}

	/** @brief Removes a stored file and logs cleanup failures without exposing its name. @param string $storedFilename Stored basename. */
	private function RemoveStoredFile(string $storedFilename): void
	{
		$path = $this->ResolveStoredFile($storedFilename);

		if ($path !== null && !unlink($path))
		{
			$this->_logger->Error('Unable to remove a stored document file');
		}
	}
}
