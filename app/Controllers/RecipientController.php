<?php

/**
 * @file RecipientController.php
 * @brief Dedicated monitor-recipient configuration pages.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\MonitorRepository;
use Pulse\Repositories\RecipientRepository;
use Pulse\Services\AuthService;
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
	private NotificationComposer $_composer;
	private RecipientPortalService $_portalService;

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
		NotificationComposer $composer,
		RecipientPortalService $portalService
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_recipientRepository = $recipientRepository;
		$this->_monitorRepository = $monitorRepository;
		$this->_documentRepository = $documentRepository;
		$this->_composer = $composer;
		$this->_portalService = $portalService;
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

		$messageIssues = RecipientMessageValidator::Validate(
			(string)($recipient['override_subject'] ?? ''),
			(string)($recipient['override_body'] ?? '')
		);
		$defaultMessageIssues = RecipientMessageValidator::Validate(
			(string)($recipient['default_message_subject'] ?? ''),
			(string)($recipient['default_message_body'] ?? '')
		);

		return $this->_view->Render('recipients.edit', [
			'user' => $user,
			'recipient' => $recipient,
			'documents' => $this->_documentRepository->FindAllByMonitorIdForUser((int)$recipient['monitor_id'], (int)$user['id']),
			'assignedDocumentIds' => $this->_recipientRepository->FindAssignedDocumentIdsForUser((int)$recipient['id'], (int)$user['id']),
			'deliveryHistory' => $this->_recipientRepository->FindDeliveryHistoryForUser((int)$recipient['id'], (int)$user['id']),
			'preview' => $preview,
			'defaultPreview' => $defaultPreview,
			'messageIssues' => $messageIssues,
			'defaultMessageIssues' => $defaultMessageIssues,
		]);
	}

	/** @brief Adds an existing contact to a monitor and opens its dedicated page. */
	public function Add(): void
	{
		$user = $this->RequireUser();

		try
		{
			$monitorContactId = $this->_recipientRepository->AddForMonitor(
				$this->_request->PostInt('monitor_id'),
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
		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId);
	}

	/** @brief Saves one recipient's monitor-specific message and documents. */
	public function Update(): void
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

		$useOverride = $this->_request->PostBool('use_message_override');
		$subject = $this->_request->PostString('message_subject', 255);
		$body = $this->_request->PostString('message_body', 1000000, false);

		$this->_recipientRepository->UpdateConfigurationForUser(
			$monitorContactId,
			$userId,
			$useOverride,
			$subject,
			$body,
			$this->_request->PostIntArray('document_ids')
		);
		$this->_logger->Info('Monitor recipient updated', [
			'user_id' => $userId,
			'monitor_id' => (int)$recipient['monitor_id'],
			'monitor_contact_id' => $monitorContactId,
		]);
		$issues = $useOverride ? RecipientMessageValidator::Validate($subject, $body) : [];
		$this->Flash(
			$issues !== [] ? 'warning' : 'success',
			__($issues !== [] ? 'recipients.flash.saved_with_warnings' : 'recipients.flash.updated')
		);
		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId);
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

		$this->Redirect('/monitors/recipients/edit?id=' . $monitorContactId);
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

		$this->_recipientRepository->RemoveForUser($monitorContactId, $userId);
		$this->Flash('success', __('recipients.flash.removed', ['name' => (string)$recipient['name']]));
		$this->Redirect('/monitors/edit?id=' . (int)$recipient['monitor_id'] . '&tab=recipients');
	}
}
