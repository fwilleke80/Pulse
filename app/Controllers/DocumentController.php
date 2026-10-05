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
use Pulse\Core\PrivateFileStreamer;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\MonitorRepository;
use Pulse\Services\AuthService;
use Pulse\Services\DocumentPreviewService;
use Pulse\Services\DocumentService;

/**
 * @brief Keeps document request flow separate from monitor configuration.
 */
class DocumentController extends BaseController
{
	private DocumentService $_documentService;
	private DocumentRepository $_documentRepository;
	private MonitorRepository $_monitorRepository;
	private DocumentPreviewService $_documentPreviewService;
	private PrivateFileStreamer $_privateFileStreamer;

	/**
	 * @brief Constructs the controller.
	 * @param View $view View.
	 * @param Session $session Session.
	 * @param AuthService $auth Authentication.
	 * @param Logger $logger Logger.
	 * @param Request $request Request.
	 * @param DocumentService $documentService Document service.
	 * @param DocumentRepository $documentRepository Document repository.
	 * @param MonitorRepository $monitorRepository Monitor repository.
	 * @param DocumentPreviewService $documentPreviewService Safe preview classifier.
	 * @param PrivateFileStreamer $privateFileStreamer Authenticated private-file streamer.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		DocumentService $documentService,
		DocumentRepository $documentRepository,
		MonitorRepository $monitorRepository,
		DocumentPreviewService $documentPreviewService,
		PrivateFileStreamer $privateFileStreamer
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_documentService = $documentService;
		$this->_documentRepository = $documentRepository;
		$this->_monitorRepository = $monitorRepository;
		$this->_documentPreviewService = $documentPreviewService;
		$this->_privateFileStreamer = $privateFileStreamer;
	}

	/** @brief Displays the dedicated text-document creation editor. @return string */
	public function NewText(): string
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->QueryInt('monitor_id');
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->Flash('error', __('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		if (!empty($monitor['is_archived']))
		{
			$this->Flash('warning', __('monitors.archived.readonly.flash'));
			$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
		}

		return $this->_view->Render('documents.editor', [
			'user' => $user,
			'monitor' => $monitor,
			'document' => null,
		]);
	}

	/** @brief Displays the dedicated editor for one owned monitor document. @return string */
	public function Edit(): string
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->QueryInt('monitor_id');
		$documentId = $this->_request->QueryInt('document_id');
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);
		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, $monitorId, (int)$user['id']);

		if ($monitor === null || $document === null)
		{
			$this->Flash('error', __('monitors.documents.flash.document_not_found'));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=documents' : '/monitors');
		}

		$previewAvailable = false;
		$previewKind = DocumentPreviewService::KIND_DOWNLOAD;

		if ((string)($document['storage_type'] ?? '') === 'file')
		{
			$previewKind = $this->_documentPreviewService->Kind($document);

			if ($this->_documentPreviewService->IsViewable($document))
			{
				try
				{
					$this->_documentService->PrepareDownloadForUser((int)$user['id'], $monitorId, $documentId);
					$previewAvailable = true;
				}
				catch (DocumentException)
				{
					$previewAvailable = false;
				}
			}
		}

		return $this->_view->Render('documents.editor', [
			'user' => $user,
			'monitor' => $monitor,
			'document' => $document,
			'previewAvailable' => $previewAvailable,
			'previewKind' => $previewKind,
		]);
	}

	/** @brief Serves one owner-authenticated inline preview of an uploaded monitor document. */
	public function Preview(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->QueryInt('monitor_id');
		$documentId = $this->_request->QueryInt('document_id');

		try
		{
			$download = $this->_documentService->PrepareDownloadForUser(
				(int)$user['id'],
				$monitorId,
				$documentId
			);
		}
		catch (DocumentException)
		{
			$this->PreviewNotFound();
		}

		$document = $download['document'];
		$path = (string)$download['path'];

		if (!$this->_documentPreviewService->IsViewable($document))
		{
			$this->PreviewNotFound();
		}

		if ($this->_documentPreviewService->IsTextFrame($document))
		{
			$preview = $this->_documentPreviewService->BuildTextFrame($document, $path);

			if (!is_array($preview))
			{
				$this->PreviewNotFound();
			}

			$this->_privateFileStreamer->AllowSameOriginFrame();
			header('Content-Type: text/html; charset=utf-8');
			header('Cache-Control: no-store, private');
			header('Pragma: no-cache');
			echo $this->_view->Render('portal.document-preview', [
				'document' => $document,
				'preview' => $preview,
			]);
			exit;
		}

		$contentType = $this->_documentPreviewService->RawContentType($document);

		if ($contentType === null)
		{
			$this->PreviewNotFound();
		}

		$filename = (string)($document['original_filename'] ?? $document['title'] ?? 'preview');
		$this->_privateFileStreamer->Stream(
			$path,
			$filename,
			$contentType,
			'inline',
			true,
			$this->_documentPreviewService->Kind($document) === DocumentPreviewService::KIND_PDF
		);
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
				[]
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=documents' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.uploaded', ['name' => $originalFilename]));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
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
				[]
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$this->Redirect($monitorId > 0 ? '/monitors/documents/text/new?monitor_id=' . $monitorId : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.text_created', ['name' => $title]));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
	}

	/** @brief Updates an editable text document while preserving recipient assignments. */
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
				null
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$documentId = $this->_request->PostInt('document_id');
			$this->Redirect($monitorId > 0 && $documentId > 0
				? '/monitors/documents/edit?monitor_id=' . $monitorId . '&document_id=' . $documentId
				: '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.text_updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
	}

	/** @brief Updates an uploaded file's display metadata while preserving recipient assignments. */
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
				null
			);
		}
		catch (DocumentException $exception)
		{
			$this->Flash('error', __($exception->TranslationKey()));
			$documentId = $this->_request->PostInt('document_id');
			$this->Redirect($monitorId > 0 && $documentId > 0
				? '/monitors/documents/edit?monitor_id=' . $monitorId . '&document_id=' . $documentId
				: '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.file_updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
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
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=documents' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.recipients_updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
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
			$this->Redirect($monitorId > 0 ? '/monitors/edit?id=' . $monitorId . '&tab=documents' : '/monitors');
		}

		$this->Flash('success', __('monitors.documents.flash.deleted'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
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

	/** @brief Emits a generic owner-only preview 404. */
	private function PreviewNotFound(): never
	{
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: no-store, private');
		echo 'Document not found.';
		exit;
	}
}
