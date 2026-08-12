<?php

/**
 * @file DocumentController.php
 * @brief HTTP actions for monitor documents.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\DocumentException;
use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Services\AuthService;
use Pulse\Services\DocumentService;

/**
 * @brief Keeps document request flow separate from monitor configuration.
 */
class DocumentController extends BaseController
{
	private DocumentService $_documentService;

	/** @brief Constructs the controller. @param View $view View. @param Session $session Session. @param AuthService $auth Authentication. @param Logger $logger Logger. @param Request $request Request. @param DocumentService $documentService Document service. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		DocumentService $documentService
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_documentService = $documentService;
	}

	/** @brief Uploads a monitor document. */
	public function Upload(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('monitor_id');
		$file = $this->_request->UploadedFile('document_file') ?? [];
		$originalFilename = str_replace('\\', '/', (string)($file['name'] ?? ''));
		$originalFilename = basename($originalFilename);

		try
		{
			$this->_documentService->UploadForUser(
				(int)$user['id'],
				$monitorId,
				$this->_request->PostString('title', 255),
				$this->_request->PostString('description', 4000, false),
				$file,
				$this->_request->PostIntArray('document_monitor_contact_ids')
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=messages' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.uploaded', ['name' => $originalFilename]));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
	}

	/** @brief Creates an editable text document. */
	public function CreateText(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('monitor_id');
		$title = $this->_request->PostString('title', 255);

		try
		{
			$this->_documentService->CreateTextForUser(
				(int)$user['id'],
				$monitorId,
				$title,
				$this->_request->PostString('text_content', 1000000, false),
				$this->_request->PostIntArray('document_monitor_contact_ids')
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=messages' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.text_created', ['name' => $title]));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
	}

	/** @brief Updates an editable text document and its recipients. */
	public function UpdateText(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('monitor_id');

		try
		{
			$this->_documentService->UpdateTextForUser(
				(int)$user['id'],
				$monitorId,
				$this->_request->PostInt('document_id'),
				$this->_request->PostString('title', 255),
				$this->_request->PostString('text_content', 1000000, false),
				$this->_request->PostIntArray('document_monitor_contact_ids')
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=messages' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.text_updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
	}

	/** @brief Updates an uploaded file's display metadata and recipients. */
	public function UpdateFile(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('monitor_id');

		try
		{
			$this->_documentService->UpdateFileForUser(
				(int)$user['id'],
				$monitorId,
				$this->_request->PostInt('document_id'),
				$this->_request->PostString('title', 255),
				$this->_request->PostString('description', 4000, false),
				$this->_request->PostIntArray('document_monitor_contact_ids')
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=messages' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.file_updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
	}

	/** @brief Updates the document recipient set. */
	public function UpdateRecipients(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('monitor_id');
		$documentId = $this->_request->PostInt('document_id');

		try
		{
			$this->_documentService->UpdateRecipientsForUser(
				(int)$user['id'],
				$monitorId,
				$documentId,
				$this->_request->PostIntArray('document_monitor_contact_ids')
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=messages' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.recipients_updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
	}

	/** @brief Deletes an owned document. */
	public function Delete(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('monitor_id');
		$documentId = $this->_request->PostInt('document_id');

		try
		{
			$this->_documentService->DeleteForUser((int)$user['id'], $monitorId, $documentId);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=messages' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.deleted'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
	}

	/** @brief Streams an owned document as a non-cacheable attachment. */
	public function Download(): void
	{
		$user = $this->RequireUser();

		try
		{
			$download = $this->_documentService->PrepareDownloadForUser(
				(int)$user['id'],
				$this->_request->QueryInt('monitor_id'),
				$this->_request->QueryInt('document_id')
			);
		}
		catch (DocumentException)
		{
			http_response_code(404);
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Document not found.';
			exit;
		}

		$document = $download['document'];
		$path = $download['path'];
		$filename = (string)($document['original_filename'] ?? $document['title'] ?? 'document');
		$asciiFilename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'document';
		$fileSize = filesize($path);

		header('Content-Type: ' . (string)($document['mime_type'] ?? 'application/octet-stream'));
		header('Content-Disposition: attachment; filename="' . str_replace('"', '', $asciiFilename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
		header('Content-Length: ' . (string)$fileSize);
		header('Cache-Control: no-store, private');
		header('Pragma: no-cache');
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		exit;
	}
}
