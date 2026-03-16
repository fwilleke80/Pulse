<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Repositories\MonitorRepository;
use Pulse\Repositories\ContactRepository;
use Pulse\Repositories\DocumentRepository;

/**
 * @brief Controller for monitor management.
 */
class MonitorController extends BaseController
{
	private MonitorRepository $_monitorRepository;
	private ContactRepository $_contactRepository;
	private DocumentRepository $_documentRepository;

	/**
	 * @brief Constructs the monitor controller.
	 * @param \Pulse\Core\View $view View renderer.
	 * @param \Pulse\Core\Session $session Session service.
	 * @param \Pulse\Services\AuthService $auth Authentication service.
	 * @param MonitorRepository $monitorRepository Monitor repository.
	 */
	public function __construct(
		\Pulse\Core\View $view,
		\Pulse\Core\Session $session,
		\Pulse\Services\AuthService $auth,
		\Pulse\Core\Logger $logger,
		MonitorRepository $monitorRepository,
		ContactRepository $contactRepository,
		DocumentRepository $documentRepository
	)
	{
		parent::__construct($view, $session, $auth, $logger);
		$this->_monitorRepository = $monitorRepository;
		$this->_contactRepository = $contactRepository;
		$this->_documentRepository = $documentRepository;
	}

	/**
	 * @brief Displays the monitor list.
	 * @return string
	 */
	public function Index(): string
	{
		$user = $this->RequireUser();
		$monitors = $this->_monitorRepository->FindAllByUserId((int)$user['id']);

		return $this->_view->Render('monitors.index', [
			'user' => $user,
			'monitors' => $monitors,
		]);
	}

	/**
	 * @brief Displays the form for creating a new monitor.
	 * @return string
	 */
	public function New(): string
	{
		$user = $this->RequireUser();
		$contacts = $this->_contactRepository->FindAllByUserId((int)$user['id']);

		return $this->_view->Render('monitors.new', [
			'user' => $user,
			'contacts' => $contacts,
		]);
	}

	/**
	 * @brief Creates a new monitor for the current user.
	 */
	public function Create(): void
	{
		$user = $this->RequireUser();

		$name = trim((string)($_POST['name'] ?? ''));
		$description = trim((string)($_POST['description'] ?? ''));
		$checkIntervalDays = (int)($_POST['check_interval_days'] ?? 0);
		$responseWindowDays = (int)($_POST['response_window_days'] ?? 0);
		$reminderIntervalDays = (int)($_POST['reminder_interval_days'] ?? 0);
		$maxReminders = (int)($_POST['max_reminders'] ?? 0);
		$isPaused = isset($_POST['is_paused']);
		$contactIds = isset($_POST['contact_ids']) && is_array($_POST['contact_ids'])
			? array_map('intval', $_POST['contact_ids'])
			: [];

		if ($name === '')
		{
			$this->_logger->Warning('Monitor creation failed: name is required', ['user_id' => $user['id']]);
			$this->Flash('error', e__('monitors.add.flash.required'));
			$this->Redirect('/monitors/new');
		}

		if (
			$checkIntervalDays <= 0 ||
			$responseWindowDays <= 0 ||
			$reminderIntervalDays <= 0 ||
			$maxReminders < 0
		)
		{
			$this->_logger->Warning('Monitor creation failed: invalid numeric values', ['user_id' => $user['id']]);
			$this->Flash('error', e__('monitors.add.flash.invalidnumbers'));
			$this->Redirect('/monitors/new');
		}

		$allowedContacts = $this->_contactRepository->FindAllByUserId((int)$user['id']);
		$allowedContactIds = array_map(
			static fn (array $contact): int => (int)$contact['id'],
			$allowedContacts
		);

		$contactIds = array_values(array_unique(array_filter(
			$contactIds,
			static fn (int $contactId): bool => in_array($contactId, $allowedContactIds, true)
		)));

		$monitorId = $this->_monitorRepository->CreateForUser(
			(int)$user['id'],
			$name,
			$description !== '' ? $description : null,
			$checkIntervalDays,
			$responseWindowDays,
			$reminderIntervalDays,
			$maxReminders,
			$isPaused
		);

		$this->_logger->Info('Monitor created successfully', ['user_id' => $user['id'], 'monitor_name' => $name]);
		$this->_monitorRepository->ReplaceContactsForMonitor($monitorId, $contactIds);
		$this->Flash('success', e__('monitors.add.flash.created', ['name' => $name]));
		$this->Redirect('/monitors');
	}

	/**
	 * @brief Displays the form for editing an existing monitor.
	 * @return string
	 */
	public function Edit(): string
	{
		$user = $this->RequireUser();
		$monitorId = (int)($_GET['id'] ?? 0);

		if ($monitorId <= 0)
		{
			$this->_logger->Warning('Monitor edit failed: invalid monitor ID', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->_logger->Warning('Monitor edit failed: monitor not found', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$this->_logger->Info('Editing monitor', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
		$contacts = $this->_contactRepository->FindAllByUserId((int)$user['id']);
		$assignedContactIds = $this->_monitorRepository->FindContactIdsByMonitorId($monitorId);

		$monitorContacts = $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, (int)$user['id']);
		$documents = $this->_documentRepository->FindAllByMonitorIdForUser($monitorId, (int)$user['id']);

		foreach ($documents as &$document)
		{
			$document['assigned_monitor_contact_ids'] = $this->_documentRepository->FindAssignedMonitorContactIds((int)$document['id']);
		}
		unset($document);

		return $this->_view->Render('monitors.edit', [
			'user' => $user,
			'monitor' => $monitor,
			'contacts' => $contacts,
			'assignedContactIds' => $assignedContactIds,
			'monitorContacts' => $monitorContacts,
			'documents' => $documents,
		]);
	}

	/**
	 * @brief Updates an existing monitor for the current user.
	 */
	public function Update(): void
	{
		$user = $this->RequireUser();

		$monitorId = (int)($_POST['id'] ?? 0);
		$name = trim((string)($_POST['name'] ?? ''));
		$description = trim((string)($_POST['description'] ?? ''));
		$checkIntervalDays = (int)($_POST['check_interval_days'] ?? 0);
		$responseWindowDays = (int)($_POST['response_window_days'] ?? 0);
		$reminderIntervalDays = (int)($_POST['reminder_interval_days'] ?? 0);
		$maxReminders = (int)($_POST['max_reminders'] ?? 0);
		$isPaused = isset($_POST['is_paused']);
		$contactIds = isset($_POST['contact_ids']) && is_array($_POST['contact_ids'])
			? array_map('intval', $_POST['contact_ids'])
			: [];

		if ($monitorId <= 0)
		{
			$this->_logger->Warning('Monitor update failed: invalid monitor ID', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$existingMonitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($existingMonitor === null)
		{
			$this->_logger->Warning('Monitor update failed: monitor not found', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		if ($name === '')
		{
			$this->_logger->Warning('Monitor update failed: name is required', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.edit.flash.required'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		if (
			$checkIntervalDays <= 0 ||
			$responseWindowDays <= 0 ||
			$reminderIntervalDays <= 0 ||
			$maxReminders < 0
		)
		{
			$this->_logger->Warning('Monitor update failed: invalid numeric values', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.edit.flash.invalidnumbers'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		$allowedContacts = $this->_contactRepository->FindAllByUserId((int)$user['id']);
		$allowedContactIds = array_map(
			static fn (array $contact): int => (int)$contact['id'],
			$allowedContacts
		);

		$contactIds = array_values(array_unique(array_filter(
			$contactIds,
			static fn (int $contactId): bool => in_array($contactId, $allowedContactIds, true)
		)));

		$this->_monitorRepository->UpdateForUser(
			$monitorId,
			(int)$user['id'],
			$name,
			$description !== '' ? $description : null,
			$checkIntervalDays,
			$responseWindowDays,
			$reminderIntervalDays,
			$maxReminders,
			$isPaused
		);

		$this->_monitorRepository->ReplaceContactsForMonitor($monitorId, $contactIds);
		$this->_logger->Info('Monitor updated successfully', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
		$this->Flash('success', e__('monitors.edit.flash.updated', ['name' => $name]));
		$this->Redirect('/monitors');
	}

	/**
	 * @brief Deletes a monitor owned by the current user.
	 */
	public function Delete(): void
	{
		$user = $this->RequireUser();
		$monitorId = (int)($_POST['id'] ?? 0);

		if ($monitorId > 0)
		{
			$existingMonitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);
			
			$this->_monitorRepository->DeleteForUser($monitorId, (int)$user['id']);
			$this->_logger->Info('Monitor deleted successfully', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('success', e__('monitors.index.flash.deleted', ['name' => $existingMonitor ? (string)$existingMonitor['name'] : ''])); // Use monitor name if available
		}

		$this->Redirect('/monitors');
	}

	/**
	 * @brief Generates a unique stored filename for an uploaded file.
	 * @param string $originalFilename Original uploaded filename.
	 * @return string
	 */
	private function GenerateStoredFilename(string $originalFilename): string
	{
		$extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
		$randomName = bin2hex(random_bytes(16));

		if ($extension === '')
		{
			return $randomName;
		}

		return $randomName . '.' . strtolower($extension);
	}

	/**
	 * @brief Uploads a file document for a monitor and assigns recipients.
	 */
	public function UploadDocument(): void
	{
		$user = $this->RequireUser();

		$monitorId = (int)($_POST['monitor_id'] ?? 0);
		$title = trim((string)($_POST['title'] ?? ''));
		$assignedMonitorContactIds = isset($_POST['document_monitor_contact_ids']) && is_array($_POST['document_monitor_contact_ids'])
			? array_map('intval', $_POST['document_monitor_contact_ids'])
			: [];

		if ($monitorId <= 0)
		{
			$this->Flash('error', e__('monitors.documents.flash.monitor_not_found'));
			$this->Redirect('/monitors');
		}

		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->_logger->Warning('Document upload failed: monitor not found', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.documents.flash.monitor_not_found'));
			$this->Redirect('/monitors');
		}

		if (!isset($_FILES['document_file']) || !is_array($_FILES['document_file']))
		{
			$this->_logger->Warning('Document upload failed: no uploaded file', ['user_id' => $user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('error', e__('monitors.documents.flash.file_required'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		$file = $_FILES['document_file'];

		if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
		{
			$this->_logger->Warning('Document upload failed: upload error', [
				'user_id' => $user['id'],
				'monitor_id' => $monitorId,
				'upload_error' => $file['error'] ?? null,
			]);
			$this->Flash('error', e__('monitors.documents.flash.upload_failed'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		$originalFilename = (string)($file['name'] ?? '');
		$tmpName = (string)($file['tmp_name'] ?? '');
		$fileSizeBytes = (int)($file['size'] ?? 0);

		if ($originalFilename === '' || $tmpName === '')
		{
			$this->Flash('error', e__('monitors.documents.flash.upload_failed'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		if ($title === '')
		{
			$title = $originalFilename;
		}

		$monitorContacts = $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, (int)$user['id']);
		$allowedMonitorContactIds = array_map(
			static fn (array $monitorContact): int => (int)$monitorContact['id'],
			$monitorContacts
		);

		$assignedMonitorContactIds = array_values(array_unique(array_filter(
			$assignedMonitorContactIds,
			static fn (int $monitorContactId): bool => in_array($monitorContactId, $allowedMonitorContactIds, true)
		)));

		$mimeType = (string)mime_content_type($tmpName);
		$storedFilename = $this->GenerateStoredFilename($originalFilename);

		$targetDirectory = dirname(__DIR__, 2) . '/storage/uploads/monitor-documents';

		if (!is_dir($targetDirectory))
		{
			mkdir($targetDirectory, 0775, true);
		}

		$targetPath = $targetDirectory . '/' . $storedFilename;

		if (!move_uploaded_file($tmpName, $targetPath))
		{
			$this->_logger->Error('Document upload failed: move_uploaded_file failed', [
				'user_id' => $user['id'],
				'monitor_id' => $monitorId,
				'stored_filename' => $storedFilename,
			]);
			$this->Flash('error', e__('monitors.documents.flash.upload_failed'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		$documentId = $this->_documentRepository->CreateFileDocumentForMonitor(
			$monitorId,
			$title,
			$storedFilename,
			$originalFilename,
			$mimeType,
			$fileSizeBytes
		);

		$this->_documentRepository->ReplaceRecipientsForDocument($documentId, $assignedMonitorContactIds);

		$this->_logger->Info('Document uploaded successfully', [
			'user_id' => $user['id'],
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
			'original_filename' => $originalFilename,
			'stored_filename' => $storedFilename,
		]);

		$this->Flash('success', e__('monitors.documents.flash.uploaded', [
			'name' => $title,
		]));
		$this->Redirect('/monitors/edit?id=' . $monitorId);
	}

	/**
	 * @brief Updates recipient assignments for a monitor document.
	 */
	public function UpdateDocumentRecipients(): void
	{
		$user = $this->RequireUser();

		$monitorId = (int)($_POST['monitor_id'] ?? 0);
		$documentId = (int)($_POST['document_id'] ?? 0);
		$assignedMonitorContactIds = isset($_POST['document_monitor_contact_ids']) && is_array($_POST['document_monitor_contact_ids'])
			? array_map('intval', $_POST['document_monitor_contact_ids'])
			: [];

		if ($monitorId <= 0 || $documentId <= 0)
		{
			$this->Flash('error', e__('monitors.documents.flash.document_not_found'));
			$this->Redirect('/monitors');
		}

		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, (int)$user['id']);

		if ($document === null)
		{
			$this->_logger->Warning('Document recipient update failed: document not found', [
				'user_id' => $user['id'],
				'monitor_id' => $monitorId,
				'document_id' => $documentId,
			]);
			$this->Flash('error', e__('monitors.documents.flash.document_not_found'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		$monitorContacts = $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, (int)$user['id']);
		$allowedMonitorContactIds = array_map(
			static fn (array $monitorContact): int => (int)$monitorContact['id'],
			$monitorContacts
		);

		$assignedMonitorContactIds = array_values(array_unique(array_filter(
			$assignedMonitorContactIds,
			static fn (int $monitorContactId): bool => in_array($monitorContactId, $allowedMonitorContactIds, true)
		)));

		$this->_documentRepository->ReplaceRecipientsForDocument($documentId, $assignedMonitorContactIds);

		$this->_logger->Info('Document recipients updated successfully', [
			'user_id' => $user['id'],
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
		]);

		$this->Flash('success', e__('monitors.documents.flash.recipients_updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId);
	}

	/**
	 * @brief Deletes a monitor document and its stored file.
	 */
	public function DeleteDocument(): void
	{
		$user = $this->RequireUser();

		$monitorId = (int)($_POST['monitor_id'] ?? 0);
		$documentId = (int)($_POST['document_id'] ?? 0);

		if ($monitorId <= 0 || $documentId <= 0)
		{
			$this->Flash('error', e__('monitors.documents.flash.document_not_found'));
			$this->Redirect('/monitors');
		}

		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, (int)$user['id']);

		if ($document === null)
		{
			$this->_logger->Warning('Document deletion failed: document not found', [
				'user_id' => $user['id'],
				'monitor_id' => $monitorId,
				'document_id' => $documentId,
			]);
			$this->Flash('error', e__('monitors.documents.flash.document_not_found'));
			$this->Redirect('/monitors/edit?id=' . $monitorId);
		}

		if (
			(string)$document['storage_type'] === 'file' &&
			!empty($document['stored_filename'])
		)
		{
			$filePath = dirname(__DIR__, 2) . '/storage/uploads/monitor-documents/' . (string)$document['stored_filename'];

			if (is_file($filePath))
			{
				@unlink($filePath);
			}
		}

		$this->_documentRepository->DeleteById($documentId);

		$this->_logger->Info('Document deleted successfully', [
			'user_id' => $user['id'],
			'monitor_id' => $monitorId,
			'document_id' => $documentId,
		]);

		$this->Flash('success', e__('monitors.documents.flash.deleted'));
		$this->Redirect('/monitors/edit?id=' . $monitorId);
	}

	/**
	 * @brief Downloads a document file for a monitor owned by the current user.
	 */
	public function DownloadDocument(): void
	{
		$user = $this->RequireUser();

		$monitorId = (int)($_GET['monitor_id'] ?? 0);
		$documentId = (int)($_GET['document_id'] ?? 0);

		if ($monitorId <= 0 || $documentId <= 0)
		{
			http_response_code(404);
			echo 'Document not found.';
			exit;
		}

		$document = $this->_documentRepository->FindByIdForMonitorAndUser(
			$documentId,
			$monitorId,
			(int)$user['id']
		);

		if ($document === null)
		{
			$this->_logger->Warning('Document download failed: document not found', [
				'user_id' => $user['id'],
				'monitor_id' => $monitorId,
				'document_id' => $documentId,
			]);

			http_response_code(404);
			echo 'Document not found.';
			exit;
		}

		if ((string)$document['storage_type'] !== 'file' || empty($document['stored_filename']))
		{
			$this->_logger->Warning('Document download failed: document is not a file', [
				'user_id' => $user['id'],
				'monitor_id' => $monitorId,
				'document_id' => $documentId,
			]);

			http_response_code(404);
			echo 'Document not found.';
			exit;
		}

		$filePath = dirname(__DIR__, 2) . '/storage/uploads/monitor-documents/' . (string)$document['stored_filename'];

		if (!is_file($filePath))
		{
			$this->_logger->Error('Document download failed: stored file missing', [
				'user_id' => $user['id'],
				'monitor_id' => $monitorId,
				'document_id' => $documentId,
				'stored_filename' => $document['stored_filename'],
			]);

			http_response_code(404);
			echo 'Document not found.';
			exit;
		}

		$downloadFilename = (string)($document['original_filename'] ?? $document['title'] ?? 'document');
		$mimeType = (string)($document['mime_type'] ?? 'application/octet-stream');
		$fileSize = filesize($filePath);

		header('Content-Description: File Transfer');
		header('Content-Type: ' . $mimeType);
		header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadFilename) . '"');
		header('Content-Length: ' . (string)$fileSize);
		header('Cache-Control: private, must-revalidate');
		header('Pragma: public');

		readfile($filePath);
		exit;
	}
}