<?php

/**
 * @file RecipientController.php
 * @brief Dedicated monitor-recipient configuration pages.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\DocumentException;
use Pulse\Core\Logger;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\Translator;
use Pulse\Core\View;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\MonitorRepository;
use Pulse\Repositories\RecipientRepository;
use Pulse\Services\AuthService;
use Pulse\Services\DocumentService;
use Pulse\Services\NotificationComposer;
use Pulse\Services\RecipientPortalService;
use Pulse\Services\RecipientMessageValidator;
use Throwable;

/**
 * @brief Edits monitor-scoped message and document settings without changing reusable contact data.
 */
final class RecipientController extends BaseController
{
	private RecipientRepository $_recipientRepository;
	private MonitorRepository $_monitorRepository;
	private DocumentRepository $_documentRepository;
	private DocumentService $_documentService;
	private NotificationComposer $_composer;
	private RecipientPortalService $_portalService;
	private NotificationLanguage $_languages;
	private string $_languagePath;

	/** @brief Constructs the monitor-recipient controller. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		RecipientRepository $recipientRepository,
		MonitorRepository $monitorRepository,
		DocumentRepository $documentRepository,
		DocumentService $documentService,
		NotificationComposer $composer,
		RecipientPortalService $portalService,
		NotificationLanguage $languages,
		string $languagePath
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_recipientRepository = $recipientRepository;
		$this->_monitorRepository = $monitorRepository;
		$this->_documentRepository = $documentRepository;
		$this->_documentService = $documentService;
		$this->_composer = $composer;
		$this->_portalService = $portalService;
		$this->_languages = $languages;
		$this->_languagePath = $languagePath;
	}

	/** @brief Displays one dedicated monitor-recipient page. */
	public function Edit(): string
	{
		$user = $this->RequireUser();
		$recipient = $this->_recipientRepository->FindByIdForUser($this->_request->QueryInt('id'), (int)$user['id']);

		if (!is_array($recipient))
		{
			$this->Flash('error', __('recipients.flash.not_found'));
			$this->Redirect('/monitors');
		}

		$hasOverride = !empty($recipient['override_message_id']);
		$effectiveSubject = $hasOverride
			? (string)($recipient['override_subject'] ?? '')
			: (string)($recipient['default_message_subject'] ?? '');
		$effectiveBody = $hasOverride
			? (string)($recipient['override_body'] ?? '')
			: (string)($recipient['default_message_body'] ?? '');
		$preview = $this->_composer->ComposeRecipientNotification([
			'recipient_name' => (string)$recipient['name'],
			'notification_locale' => (string)$recipient['notification_locale'],
			'owner_name' => (string)$recipient['owner_name'],
			'monitor_name' => (string)$recipient['monitor_name'],
			'message_subject' => $effectiveSubject,
			'message_body' => $effectiveBody,
		]);
		$defaultPreview = $this->_composer->ComposeRecipientNotification([
			'recipient_name' => (string)$recipient['name'],
			'notification_locale' => (string)$recipient['notification_locale'],
			'owner_name' => (string)$recipient['owner_name'],
			'monitor_name' => (string)$recipient['monitor_name'],
			'message_subject' => (string)($recipient['default_message_subject'] ?? ''),
			'message_body' => (string)($recipient['default_message_body'] ?? ''),
		]);
		$defaultTemplateSubject = (string)($recipient['default_message_subject'] ?? '');
		$defaultTemplateBody = (string)($recipient['default_message_body'] ?? '');
		$defaultTemplate = trim($defaultTemplateSubject) !== '' && trim($defaultTemplateBody) !== ''
			? ['subject' => $defaultTemplateSubject, 'body_text' => $defaultTemplateBody]
			: $this->_composer->BuiltInTemplate('recipient_default', (string)$recipient['notification_locale']);

		$messageIssues = RecipientMessageValidator::Validate(
			(string)($recipient['override_subject'] ?? ''),
			(string)($recipient['override_body'] ?? '')
		);
		$defaultMessageIssues = RecipientMessageValidator::Validate(
			(string)($recipient['default_message_subject'] ?? ''),
			(string)($recipient['default_message_body'] ?? '')
		);
		$activeDelivery = $this->_recipientRepository->FindLatestAvailableDeliveryForUser((int)$recipient['id'], (int)$user['id']);
		$activeDeliveryDocuments = is_array($activeDelivery)
			? $this->_recipientRepository->FindReleasedDocumentsForUser((int)$activeDelivery['id'], (int)$recipient['id'], (int)$user['id'])
			: [];
		return $this->_view->Render('recipients.edit', [
			'user' => $user,
			'recipient' => $recipient,
			'documents' => $this->_documentRepository->FindAllByMonitorIdForUser((int)$recipient['monitor_id'], (int)$user['id']),
			'assignedDocumentIds' => $this->_recipientRepository->FindAssignedDocumentIdsForUser((int)$recipient['id'], (int)$user['id']),
			'deliveryHistory' => $this->_recipientRepository->FindDeliveryHistoryForUser((int)$recipient['id'], (int)$user['id']),
			'portalActivity' => $this->_recipientRepository->FindPortalActivityForUser((int)$recipient['id'], (int)$user['id']),
			'preview' => $preview,
			'defaultPreview' => $defaultPreview,
			'defaultTemplate' => $defaultTemplate,
			'messageIssues' => $messageIssues,
			'defaultMessageIssues' => $defaultMessageIssues,
			'activeDelivery' => $activeDelivery,
			'activeDeliveryDocuments' => $activeDeliveryDocuments,
			'activeSection' => $this->ActiveSection(),
		]);
	}

	/** @brief Displays an owner-only preview of the future authenticated recipient portal. */
	public function PortalPreview(): string
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorContactId = $this->_request->QueryInt('id');
		$recipient = $this->_recipientRepository->FindByIdForUser($monitorContactId, $userId);

		if (!is_array($recipient))
		{
			http_response_code(404);
			return $this->_view->Render('home.not-found');
		}

		$this->UsePreviewLanguage((string)$recipient['notification_locale'], $monitorContactId);
		$portalContent = $this->_composer->ComposeRecipientPortalContent([
			'recipient_name' => (string)$recipient['name'],
			'notification_locale' => (string)$recipient['notification_locale'],
			'owner_name' => (string)$recipient['owner_name'],
			'monitor_name' => (string)$recipient['monitor_name'],
			'portal_message_override_enabled' => !empty($recipient['portal_override_id']),
			'portal_message_override' => (string)($recipient['portal_override_body'] ?? ''),
			'portal_intro_text' => (string)($recipient['default_portal_intro'] ?? ''),
		]);
		[$documents, $availableDocumentCount, $totalDownloadBytes] = $this->PreparePreviewDocuments(
			$this->_recipientRepository->FindAssignedDocumentsForUser($monitorContactId, $userId),
			$monitorContactId
		);

		return $this->_view->Render('portal.access', [
			'delivery' => [
				'delivery_id' => 0,
				'owner_name' => (string)$recipient['owner_name'],
				'monitor_name' => (string)$recipient['monitor_name'],
				'recipient_name' => (string)$recipient['name'],
				'portal_expires_at' => null,
				'portal_intro_text' => $portalContent['intro_text'],
				'portal_message_text' => $portalContent['message_text'],
			],
			'documents' => $documents,
			'locations' => $this->_recipientRepository->FindPortalPreviewLocationsForUser($monitorContactId, $userId),
			'token' => '',
			'availableDocumentCount' => $availableDocumentCount,
			'totalDownloadBytes' => $totalDownloadBytes,
			'previewMode' => true,
			'previewRecipientName' => (string)$recipient['name'],
		]);
	}

	/** @brief Streams an owner-authenticated image used only inside a portal preview. */
	public function PortalPreviewAsset(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorContactId = $this->_request->QueryInt('recipient');
		$documentId = $this->_request->QueryInt('document');
		$document = $this->_recipientRepository->FindAssignedDocumentForUser($monitorContactId, $documentId, $userId);

		if (!is_array($document) || !$this->IsPreviewImageType((string)($document['mime_type'] ?? '')))
		{
			$this->PreviewAssetNotFound();
		}

		try
		{
			$download = $this->_documentService->PrepareDownloadForUser(
				$userId,
				(int)$document['monitor_id'],
				$documentId
			);
		}
		catch (DocumentException)
		{
			$this->PreviewAssetNotFound();
		}

		$path = (string)$download['path'];
		$filename = (string)($document['original_filename'] ?? $document['title'] ?? 'preview');
		$asciiFilename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'preview';
		$fileSize = filesize($path);

		if (session_status() === PHP_SESSION_ACTIVE)
		{
			session_write_close();
		}

		header('Content-Type: ' . (string)$document['mime_type']);
		header('Content-Disposition: inline; filename="' . str_replace('"', '', $asciiFilename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));

		if (is_int($fileSize))
		{
			header('Content-Length: ' . $fileSize);
		}

		header('Cache-Control: no-store, private');
		header('Pragma: no-cache');
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		exit;
	}

	/** @brief Adds an existing contact to a monitor and opens its dedicated page. */
	public function Add(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('monitor_id');
		$this->RejectArchivedMonitor($monitorId, (int)$user['id'], '/monitors/edit?id=' . $monitorId . '&tab=recipients');

		try
		{
			$monitorContactId = $this->_recipientRepository->AddForMonitor(
				$monitorId,
				$this->_request->PostInt('contact_id'),
				(int)$user['id']
			);
		}
		catch (Throwable)
		{
			$this->Flash('error', __('recipients.flash.add_failed'));
			$this->Redirect('/monitors');
		}

		$this->Flash('success', __('recipients.flash.added'));
		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=overview');
	}

	/** @brief Saves one recipient's monitor-specific message and documents. */
	public function Update(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorContactId = $this->_request->PostInt('id');
		$returnSection = $this->PostedSection();
		$recipient = $this->_recipientRepository->FindByIdForUser($monitorContactId, $userId);

		if (!is_array($recipient))
		{
			$this->Flash('error', __('recipients.flash.not_found'));
			$this->Redirect('/monitors');
		}

		if (!empty($recipient['monitor_is_archived']))
		{
			$this->Flash('warning', __('monitors.archived.readonly.flash'));
			$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=' . $returnSection);
		}

		$useOverride = $returnSection === 'notification'
			? $this->_request->PostBool('use_message_override')
			: !empty($recipient['override_message_id']);
		$subject = $returnSection === 'notification'
			? $this->_request->PostString('message_subject', 255)
			: (string)($recipient['override_subject'] ?? '');
		$body = $returnSection === 'notification'
			? $this->_request->PostString('message_body', 1000000, false)
			: (string)($recipient['override_body'] ?? '');
		$usePortalOverride = $returnSection === 'portal'
			? $this->_request->PostBool('use_portal_message_override')
			: !empty($recipient['portal_override_id']);
		$portalBody = $returnSection === 'portal'
			? $this->_request->PostString('portal_message_body', 1000000, false)
			: (string)($recipient['portal_override_body'] ?? '');

		if ($returnSection === 'portal' && $usePortalOverride && trim($portalBody) === '')
		{
			$this->Flash('error', __('recipients.portal_message.flash.body_required'));
			$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=portal');
		}
		$documentIds = $returnSection === 'documents'
			? $this->_request->PostIntArray('document_ids')
			: $this->_recipientRepository->FindAssignedDocumentIdsForUser($monitorContactId, $userId);

		$this->_recipientRepository->UpdateConfigurationForUser(
			$monitorContactId,
			$userId,
			$useOverride,
			$subject,
			$body,
			$usePortalOverride,
			$portalBody,
			$documentIds
		);
		$this->_logger->Info('Monitor recipient updated', [
			'user_id' => $userId,
			'monitor_id' => (int)$recipient['monitor_id'],
			'monitor_contact_id' => $monitorContactId,
		]);
		$issues = $returnSection === 'notification' && $useOverride ? RecipientMessageValidator::Validate($subject, $body) : [];
		$this->Flash(
			$issues !== [] ? 'warning' : 'success',
			__($issues !== [] ? 'recipients.flash.saved_with_warnings' : 'recipients.flash.updated')
		);
		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=' . $returnSection);
	}

	/** @brief Updates editable presentation text for one already released active portal. */
	public function UpdateReleasedPortal(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorContactId = $this->_request->PostInt('recipient_id');
		$deliveryId = $this->_request->PostInt('delivery_id');
		$recipient = $this->_recipientRepository->FindByIdForUser($monitorContactId, $userId);

		if (!is_array($recipient))
		{
			$this->Flash('error', __('recipients.flash.not_found'));
			$this->Redirect('/monitors');
		}

		$updated = $this->_recipientRepository->UpdateReleasedPortalContentForUser(
			$deliveryId,
			$monitorContactId,
			$userId,
			$this->_request->PostString('portal_intro_text', 1000000, false),
			$this->_request->PostString('portal_message_text', 1000000, false)
		);
		$this->Flash($updated ? 'success' : 'warning', __($updated ? 'recipients.active_portal.flash.updated' : 'recipients.active_portal.flash.unavailable'));
		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=portal');
	}

	/** @brief Updates title and description of one authorized released document snapshot. */
	public function UpdateReleasedDocument(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorContactId = $this->_request->PostInt('recipient_id');
		$updated = $this->_recipientRepository->UpdateReleasedDocumentMetadataForUser(
			$this->_request->PostInt('delivery_id'),
			$this->_request->PostInt('document_id'),
			$monitorContactId,
			$userId,
			$this->_request->PostString('title', 255),
			$this->_request->PostString('description', 4000, false)
		);
		$this->Flash($updated ? 'success' : 'warning', __($updated ? 'recipients.active_documents.flash.updated' : 'recipients.active_documents.flash.unavailable'));
		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=documents');
	}

	/** @brief Revokes one previously released recipient portal delivery for the authenticated owner. */
	public function RevokePortal(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorContactId = $this->_request->PostInt('recipient_id');
		$deliveryId = $this->_request->PostInt('delivery_id');
		$recipient = $this->_recipientRepository->FindByIdForUser($monitorContactId, $userId);

		if (!is_array($recipient))
		{
			$this->Flash('error', __('recipients.flash.not_found'));
			$this->Redirect('/monitors');
		}

		if ($this->_portalService->RevokeForUser($deliveryId, $userId))
		{
			$this->Flash('success', __('recipients.portal.revoke.success'));
		}
		else
		{
			$this->Flash('warning', __('recipients.portal.revoke.unavailable'));
		}

		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=history');
	}

	/** @brief Removes a contact from one monitor without changing prior snapshots. */
	public function Remove(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorContactId = $this->_request->PostInt('id');
		$recipient = $this->_recipientRepository->FindByIdForUser($monitorContactId, $userId);

		if (!is_array($recipient))
		{
			$this->Flash('error', __('recipients.flash.not_found'));
			$this->Redirect('/monitors');
		}

		if (!empty($recipient['monitor_is_archived']))
		{
			$this->Flash('warning', __('monitors.archived.readonly.flash'));
			$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId . '&section=overview');
		}

		$this->_recipientRepository->RemoveForUser($monitorContactId, $userId);
		$this->Flash('success', __('recipients.flash.removed', ['name' => (string)$recipient['name']]));
		$this->Redirect('/monitors/edit?id=' . (int)$recipient['monitor_id'] . '&tab=recipients');
	}

	/** @brief Rejects configuration changes for an archived monitor. */
	private function RejectArchivedMonitor(int $monitorId, int $userId, string $redirect): void
	{
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, $userId);

		if (is_array($monitor) && !empty($monitor['is_archived']))
		{
			$this->Flash('warning', __('monitors.archived.readonly.flash'));
			$this->Redirect($redirect);
		}
	}

	/**
	 * @brief Adds recipient-portal presentation metadata to current assigned documents.
	 * @param array<int, array<string, mixed>> $documents Current assigned documents.
	 * @return array{0: array<int, array<string, mixed>>, 1: int, 2: int}
	 */
	private function PreparePreviewDocuments(array $documents, int $monitorContactId): array
	{
		$totalDownloadBytes = 0;

		foreach ($documents as &$document)
		{
			$isText = (string)($document['storage_type'] ?? '') === 'text';
			$sizeBytes = $isText
				? strlen((string)($document['text_content'] ?? ''))
				: max(0, (int)($document['file_size_bytes'] ?? 0));
			$mimeType = strtolower(trim((string)($document['mime_type'] ?? '')));

			$document['download_available'] = true;
			$document['view_available'] = $isText || $this->IsPreviewInlineType($mimeType);
			$document['image_preview'] = !$isText && $this->IsPreviewImageType($mimeType);
			$document['size_bytes'] = $sizeBytes;
			$document['type_label'] = $this->DocumentTypeLabel($document);
			$document['preview_image_url'] = '/monitors/recipients/portal-preview/asset?recipient='
				. $monitorContactId . '&document=' . (int)$document['id'];
			$totalDownloadBytes += $sizeBytes;
		}
		unset($document);

		return [$documents, count($documents), $totalDownloadBytes];
	}

	/** @brief Selects the recipient's language while retaining the owner's authenticated authorization. */
	private function UsePreviewLanguage(string $storedLocale, int $monitorContactId): void
	{
		$locale = $this->_languages->Resolve($storedLocale);
		setTranslator(new Translator($this->_languagePath, $locale));
		$this->_view->SetGlobals([
			'locale' => $locale,
			'currentTarget' => '/monitors/recipients/portal-preview?id=' . $monitorContactId,
			'isAuthenticated' => false,
			'currentUser' => null,
			'portalPreview' => true,
		], true);
	}

	/** @brief Returns whether a current file can be represented by the portal's passive View action. */
	private function IsPreviewInlineType(string $mimeType): bool
	{
		return $mimeType === 'application/pdf' || $this->IsPreviewImageType($mimeType);
	}

	/** @brief Returns whether a current uploaded file is a safe raster image preview. */
	private function IsPreviewImageType(string $mimeType): bool
	{
		return in_array(strtolower(trim($mimeType)), [
			'image/gif',
			'image/jpeg',
			'image/png',
			'image/webp',
			'image/avif',
		], true);
	}

	/** @brief Returns the same compact file-type label used by an authenticated recipient portal. */
	private function DocumentTypeLabel(array $document): string
	{
		if ((string)($document['storage_type'] ?? '') === 'text')
		{
			return 'MD';
		}

		$extension = strtoupper(pathinfo((string)($document['original_filename'] ?? ''), PATHINFO_EXTENSION));

		if ($extension !== '')
		{
			return substr($extension, 0, 8);
		}

		$mimeType = strtolower((string)($document['mime_type'] ?? ''));
		return match ($mimeType)
		{
			'application/pdf' => 'PDF',
			'image/jpeg' => 'JPG',
			'image/png' => 'PNG',
			default => __('portal.documents.type.file'),
		};
	}

	/** @brief Emits a generic owner-only preview-asset 404. */
	private function PreviewAssetNotFound(): never
	{
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: no-store, private');
		echo 'Preview unavailable.';
		exit;
	}

	/** @brief Returns the active recipient sub-editor section. */
	private function ActiveSection(): string
	{
		$section = $this->_request->QueryString('section', 20);
		return in_array($section, ['overview', 'notification', 'portal', 'documents', 'history'], true) ? $section : 'overview';
	}

	/** @brief Returns the recipient sub-editor section posted by the shared configuration form. */
	private function PostedSection(): string
	{
		$section = $this->_request->PostString('recipient_section', 20);
		return in_array($section, ['overview', 'notification', 'portal', 'documents', 'history'], true) ? $section : 'overview';
	}

}
