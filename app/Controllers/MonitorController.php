<?php

/**
 * @file MonitorController.php
 * @brief Monitor configuration and manual check-in controller.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\ContactRepository;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\MessageRepository;
use Pulse\Repositories\MonitorRepository;
use Pulse\Services\AuthService;
use Pulse\Services\DocumentService;

/**
 * @brief Handles monitor configuration without owning document HTTP actions.
 */
class MonitorController extends BaseController
{
	private MonitorRepository $_monitorRepository;
	private ContactRepository $_contactRepository;
	private DocumentRepository $_documentRepository;
	private MessageRepository $_messageRepository;
	private DocumentService $_documentService;
	private bool $_allowForceDue;

	/**
	 * @brief Constructs the monitor controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param Logger $logger Logger.
	 * @param Request $request Current request.
	 * @param MonitorRepository $monitorRepository Monitor repository.
	 * @param ContactRepository $contactRepository Contact repository.
	 * @param DocumentRepository $documentRepository Document repository.
	 * @param MessageRepository $messageRepository Message repository.
	 * @param DocumentService $documentService Document service.
	 * @param bool $allowForceDue Whether the development-only action is enabled.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		MonitorRepository $monitorRepository,
		ContactRepository $contactRepository,
		DocumentRepository $documentRepository,
		MessageRepository $messageRepository,
		DocumentService $documentService,
		bool $allowForceDue
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_monitorRepository = $monitorRepository;
		$this->_contactRepository = $contactRepository;
		$this->_documentRepository = $documentRepository;
		$this->_messageRepository = $messageRepository;
		$this->_documentService = $documentService;
		$this->_allowForceDue = $allowForceDue;
	}

	/** @brief Displays the runtime-oriented monitor list. @return string */
	public function Index(): string
	{
		$user = $this->RequireUser();

		return $this->_view->Render('monitors.index', [
			'user' => $user,
			'monitors' => $this->_monitorRepository->FindAllByUserId((int)$user['id']),
			'allowForceDue' => $this->_allowForceDue,
		]);
	}

	/** @brief Displays the monitor creation form. @return string */
	public function New(): string
	{
		$user = $this->RequireUser();

		return $this->_view->Render('monitors.new', [
			'user' => $user,
			'contacts' => $this->_contactRepository->FindAllByUserId((int)$user['id']),
		]);
	}

	/** @brief Creates a monitor and its initial contact assignments. */
	public function Create(): void
	{
		$user = $this->RequireUser();
		$values = $this->MonitorInput();

		if (!$this->ValidateMonitorInput($values, '/monitors/new', 0))
		{
			return;
		}

		$contactIds = $this->AllowedContactIds((int)$user['id'], $this->_request->PostIntArray('contact_ids'));
		$monitorId = $this->_monitorRepository->CreateForUser(
			(int)$user['id'],
			$values['name'],
			$values['description'] !== '' ? $values['description'] : null,
			$values['check_interval_days'],
			$values['response_window_days'],
			$values['reminder_interval_days'],
			$values['max_reminders'],
			$values['is_paused']
		);
		$this->_monitorRepository->ReplaceContactsForMonitor($monitorId, (int)$user['id'], $contactIds);
		$this->_logger->Info('Monitor created', ['user_id' => (int)$user['id'], 'monitor_id' => $monitorId]);
		$this->Flash('success', __('monitors.add.flash.created', ['name' => $values['name']]));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=recipients');
	}

	/** @brief Displays the monitor editor and related document metadata. @return string */
	public function Edit(): string
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->QueryInt('id');
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->Flash('error', __('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$documents = $this->_documentRepository->FindAllByMonitorIdForUser($monitorId, (int)$user['id']);
		$messageOverrides = $this->_messageRepository->FindByMonitorIdForUser($monitorId, (int)$user['id']);

		foreach ($documents as &$document)
		{
			$document['assigned_monitor_contact_ids'] = $this->_documentRepository->FindAssignedMonitorContactIds((int)$document['id']);
		}
		unset($document);

		return $this->_view->Render('monitors.edit', [
			'user' => $user,
			'monitor' => $monitor,
			'contacts' => $this->_contactRepository->FindAllByUserId((int)$user['id']),
			'assignedContactIds' => $this->_monitorRepository->FindContactIdsByMonitorId($monitorId),
			'monitorContacts' => $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, (int)$user['id']),
			'documents' => $documents,
			'messageOverrides' => $messageOverrides,
			'activeTab' => $this->ActiveEditorTab(),
		]);
	}

	/** @brief Updates monitor configuration while preserving retained monitor-contact IDs. */
	public function Update(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->Flash('error', __('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$values = $this->MonitorInput();

		if (!$this->ValidateMonitorInput($values, '/monitors/edit?id=' . $monitorId . '&tab=schedule', $monitorId))
		{
			return;
		}

		$this->_monitorRepository->UpdateForUser(
			$monitorId,
			(int)$user['id'],
			$values['name'],
			$values['description'] !== '' ? $values['description'] : null,
			$values['check_interval_days'],
			$values['response_window_days'],
			$values['reminder_interval_days'],
			$values['max_reminders'],
			$values['is_paused']
		);
		$this->_monitorRepository->ReplaceContactsForMonitor(
			$monitorId,
			(int)$user['id'],
			$this->AllowedContactIds((int)$user['id'], $this->_request->PostIntArray('contact_ids'))
		);
		$this->_logger->Info('Monitor updated', ['user_id' => (int)$user['id'], 'monitor_id' => $monitorId]);
		$this->Flash('success', __('monitors.edit.flash.updated', ['name' => $values['name']]));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=review');
	}

	/** @brief Updates the default and recipient-specific delivery messages. */
	public function UpdateMessages(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorId = $this->_request->PostInt('monitor_id');

		if ($this->_monitorRepository->FindByIdForUser($monitorId, $userId) === null)
		{
			$this->Flash('error', __('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$defaultSubject = $this->_request->PostString('default_message_subject', 255);
		$defaultBody = $this->_request->PostString('default_message_body', 1000000, false);

		if (($defaultSubject === '') !== (trim($defaultBody) === ''))
		{
			$this->Flash('error', __('monitors.messages.flash.default_incomplete'));
			$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
		}

		$overrides = [];
		$monitorContacts = $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, $userId);

		foreach ($monitorContacts as $monitorContact)
		{
			$monitorContactId = (int)$monitorContact['id'];

			if (!$this->_request->PostBool('message_override_' . $monitorContactId))
			{
				continue;
			}

			$subject = $this->_request->PostString('message_subject_' . $monitorContactId, 255);
			$body = $this->_request->PostString('message_body_' . $monitorContactId, 1000000, false);

			if ($subject === '' || trim($body) === '')
			{
				$this->Flash('error', __('monitors.messages.flash.override_incomplete', ['name' => (string)$monitorContact['name']]));
				$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
			}

			$overrides[$monitorContactId] = [
				'subject' => $subject,
				'body_text' => $body,
			];
		}

		$this->_messageRepository->ReplaceForMonitor(
			$monitorId,
			$userId,
			$defaultSubject !== '' ? $defaultSubject : null,
			trim($defaultBody) !== '' ? $defaultBody : null,
			$overrides
		);
		$this->_logger->Info('Monitor messages updated', ['user_id' => $userId, 'monitor_id' => $monitorId]);
		$this->Flash('success', __('monitors.messages.flash.updated'));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages');
	}

	/** @brief Deletes an owned monitor and all of its physical document files. */
	public function Delete(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor !== null && $this->_documentService->DeleteMonitorForUser((int)$user['id'], $monitorId))
		{
			$this->_logger->Info('Monitor deleted', ['user_id' => (int)$user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('success', __('monitors.index.flash.deleted', ['name' => (string)$monitor['name']]));
		}

		$this->Redirect('/monitors');
	}

	/** @brief Confirms a due, active monitor and schedules the next check. */
	public function CheckIn(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');

		if ($this->_monitorRepository->ConfirmDueForUser($monitorId, (int)$user['id']))
		{
			$this->_logger->Info('Monitor manually confirmed', ['user_id' => (int)$user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('success', __('monitors.check_in.success'));
		}
		else
		{
			$this->Flash('error', __('monitors.check_in.not_due'));
		}

		$this->Redirect($this->CheckInRedirect());
	}

	/** @brief Forces a monitor due when the explicit development setting is enabled. */
	public function ForceDue(): void
	{
		$user = $this->RequireUser();

		if (!$this->_allowForceDue)
		{
			http_response_code(404);
			exit;
		}

		$monitorId = $this->_request->PostInt('id');
		$this->_monitorRepository->ForceDueForUser($monitorId, (int)$user['id']);
		$this->Flash('success', __('monitors.force_due.success'));
		$this->Redirect('/monitors');
	}

	/**
	 * @brief Reads and bounds monitor configuration input.
	 * @return array{name: string, description: string, check_interval_days: int, response_window_days: int, reminder_interval_days: int, max_reminders: int, is_paused: bool}
	 */
	private function MonitorInput(): array
	{
		return [
			'name' => $this->_request->PostString('name', 255),
			'description' => $this->_request->PostString('description', 10000),
			'check_interval_days' => $this->_request->PostInt('check_interval_days'),
			'response_window_days' => $this->_request->PostInt('response_window_days'),
			'reminder_interval_days' => $this->_request->PostInt('reminder_interval_days'),
			'max_reminders' => $this->_request->PostInt('max_reminders'),
			'is_paused' => $this->_request->PostBool('is_paused'),
		];
	}

	/**
	 * @brief Validates monitor bounds and redirects with an error when invalid.
	 * @param array<string, mixed> $values Parsed input.
	 * @param string $redirect Redirect target.
	 * @param int $monitorId Existing monitor ID or zero.
	 * @return bool
	 */
	private function ValidateMonitorInput(array $values, string $redirect, int $monitorId): bool
	{
		if ((string)$values['name'] === '')
		{
			$this->_logger->Warning('Monitor validation failed: missing name', ['monitor_id' => $monitorId]);
			$this->Flash('error', __($monitorId > 0 ? 'monitors.edit.flash.required' : 'monitors.add.flash.required'));
			$this->Redirect($redirect);
		}

		if (
			(int)$values['check_interval_days'] < 1 || (int)$values['check_interval_days'] > 3650
			|| (int)$values['response_window_days'] < 1 || (int)$values['response_window_days'] > 365
			|| (int)$values['reminder_interval_days'] < 1 || (int)$values['reminder_interval_days'] > 365
			|| (int)$values['max_reminders'] < 0 || (int)$values['max_reminders'] > 100
		)
		{
			$this->_logger->Warning('Monitor validation failed: invalid numeric bounds', ['monitor_id' => $monitorId]);
			$this->Flash('error', __($monitorId > 0 ? 'monitors.edit.flash.invalidnumbers' : 'monitors.add.flash.invalidnumbers'));
			$this->Redirect($redirect);
		}

		return true;
	}

	/** @brief Keeps only contacts owned by the active user. @param int $userId User ID. @param array<int> $requestedIds Requested IDs. @return array<int> */
	private function AllowedContactIds(int $userId, array $requestedIds): array
	{
		$contacts = $this->_contactRepository->FindAllByUserId($userId);
		$allowed = array_map(static fn (array $contact): int => (int)$contact['id'], $contacts);

		return array_values(array_filter(
			array_unique($requestedIds),
			static fn (int $contactId): bool => in_array($contactId, $allowed, true)
		));
	}

	/** @brief Restricts the post-check-in redirect to known monitor pages. @return string */
	private function CheckInRedirect(): string
	{
		$target = $this->_request->PostString('redirect', 100);

		return in_array($target, ['/', '/monitors'], true) ? $target : '/monitors';
	}

	/** @brief Returns a supported monitor editor tab from the query. @return string */
	private function ActiveEditorTab(): string
	{
		$tab = $this->_request->QueryString('tab', 20);

		return in_array($tab, ['schedule', 'recipients', 'messages', 'review'], true) ? $tab : 'schedule';
	}
}
