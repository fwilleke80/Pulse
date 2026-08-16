<?php

/**
 * @file MonitorController.php
 * @brief Monitor configuration and global manual check-in controller.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\CheckInLocation;
use Pulse\Core\EmailAddressCollection;
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
use Pulse\Services\EscalationService;
use Pulse\Services\MailQueueWorker;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\NotificationComposer;
use Pulse\Services\NotificationScheduler;
use Pulse\Services\RecipientMessageValidator;

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
	private MonitorExecutionService $_monitorExecutionService;
	private NotificationScheduler $_notificationScheduler;
	private MailQueueWorker $_mailQueueWorker;
	private EscalationService $_escalationService;
	private NotificationComposer $_notificationComposer;
	/** @var array<int, string> */
	private array $_availableLocales;
	private bool $_debugEnabled;
	private bool $_mailEnabled;

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
	 * @param MonitorExecutionService $monitorExecutionService Check-in lifecycle service.
	 * @param NotificationScheduler $notificationScheduler Owner-notification scheduler.
	 * @param MailQueueWorker $mailQueueWorker Transactional mail worker.
	 * @param EscalationService $escalationService Safety and recipient escalation service.
	 * @param NotificationComposer $notificationComposer Mail template composer.
	 * @param array<int, string> $availableLocales Configured UI/mail locales.
	 * @param bool $debugEnabled Whether development actions are enabled.
	 * @param bool $mailEnabled Whether mail delivery is enabled.
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
		MonitorExecutionService $monitorExecutionService,
		NotificationScheduler $notificationScheduler,
		MailQueueWorker $mailQueueWorker,
		EscalationService $escalationService,
		NotificationComposer $notificationComposer,
		array $availableLocales,
		bool $debugEnabled,
		bool $mailEnabled
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_monitorRepository = $monitorRepository;
		$this->_contactRepository = $contactRepository;
		$this->_documentRepository = $documentRepository;
		$this->_messageRepository = $messageRepository;
		$this->_documentService = $documentService;
		$this->_monitorExecutionService = $monitorExecutionService;
		$this->_notificationScheduler = $notificationScheduler;
		$this->_mailQueueWorker = $mailQueueWorker;
		$this->_escalationService = $escalationService;
		$this->_notificationComposer = $notificationComposer;
		$this->_availableLocales = array_values(array_filter($availableLocales, 'is_string'));
		$this->_debugEnabled = $debugEnabled;
		$this->_mailEnabled = $mailEnabled;
	}

	/** @brief Displays the runtime-oriented monitor list. @return string */
	public function Index(): string
	{
		$user = $this->RequireUser();
		$this->_monitorExecutionService->SynchronizeDueCyclesForUser((int)$user['id']);

		$userId = (int)$user['id'];
		$showArchived = $this->_request->QueryString('view', 20) === 'archived';
		$monitors = $this->_monitorRepository->FindAllByUserId($userId, $showArchived);

		foreach ($monitors as &$monitor)
		{
			$monitorId = (int)$monitor['id'];
			$monitorContacts = $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, $userId);
			$messageOverrides = $this->_messageRepository->FindByMonitorIdForUser($monitorId, $userId);
			$mailTemplates = $this->_messageRepository->FindLocalizedTemplatesForMonitor($monitorId, $userId);
			$issues = $this->RecipientConfigurationIssues($monitorContacts, $messageOverrides, $mailTemplates);
			$monitor['recipient_configuration_issue_count'] = count($issues) + $this->LocalizedRecipientDefaultIssueCount($mailTemplates);
		}
		unset($monitor);

		return $this->_view->Render('monitors.index', [
			'user' => $user,
			'monitors' => $monitors,
			'debugEnabled' => $this->_debugEnabled,
			'mailEnabled' => $this->_mailEnabled,
			'showArchived' => $showArchived,
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
			$values['max_reminders']
		);
		$this->_monitorRepository->UpdateLocationSettingsForUser(
			$monitorId,
			(int)$user['id'],
			$values['location_check_in_enabled'],
			$values['portal_location_sharing_enabled'],
			$values['portal_location_history_limit']
		);
		$this->_monitorExecutionService->InitializeMonitorForUser($monitorId, (int)$user['id']);
		$this->_monitorRepository->ReplaceContactsForMonitor($monitorId, (int)$user['id'], $contactIds);
		$this->_logger->Info('Monitor created', ['user_id' => (int)$user['id'], 'monitor_id' => $monitorId]);
		$this->Flash('success', __('monitors.add.flash.created', ['name' => $values['name']]));
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=documents');
	}

	/** @brief Displays the monitor editor and related document metadata. @return string */
	public function Edit(): string
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->QueryInt('id');
		$this->_monitorExecutionService->SynchronizeMonitorForUser($monitorId, (int)$user['id']);
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->Flash('error', __('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$documents = $this->_documentRepository->FindAllByMonitorIdForUser($monitorId, (int)$user['id']);
		$messageOverrides = $this->_messageRepository->FindByMonitorIdForUser($monitorId, (int)$user['id']);
		$mailTemplates = $this->_messageRepository->FindLocalizedTemplatesForMonitor($monitorId, (int)$user['id']);
		$portalTemplates = $this->_messageRepository->FindLocalizedPortalTemplatesForMonitor($monitorId, (int)$user['id']);
		$mailDefaults = [];
		$portalDefaults = [];
		$ownerNotificationLocale = $this->_notificationComposer->ResolveLocale(
			isset($user['notification_locale']) ? (string)$user['notification_locale'] : null
		);

		foreach (['owner_due_notice', 'owner_reminder'] as $templateKey)
		{
			$mailDefaults[$templateKey]['owner'] = $this->_notificationComposer->BuiltInTemplate(
				$templateKey,
				$ownerNotificationLocale,
				['max_reminders' => (int)($monitor['max_reminders'] ?? 0)]
			);
		}

		foreach (['recipient_default', 'safety_invitation', 'safety_reminder'] as $templateKey)
		{
			foreach ($this->_availableLocales as $templateLocale)
			{
				$mailDefaults[$templateKey][$templateLocale] = $this->_notificationComposer->BuiltInTemplate($templateKey, $templateLocale);
			}
		}

		foreach ($this->_availableLocales as $templateLocale)
		{
			$portalDefaults[$templateLocale] = $this->_notificationComposer->BuiltInPortalContent($templateLocale);
		}

		foreach ($documents as &$document)
		{
			$document['assigned_monitor_contact_ids'] = $this->_documentRepository->FindAssignedMonitorContactIds((int)$document['id']);
		}
		unset($document);

		$monitorContacts = $this->_monitorRepository->FindMonitorContactsByMonitorIdForUser($monitorId, (int)$user['id']);
		$recipientConfigurationIssues = $this->RecipientConfigurationIssues($monitorContacts, $messageOverrides, $mailTemplates);

		return $this->_view->Render('monitors.edit', [
			'user' => $user,
			'monitor' => $monitor,
			'contacts' => $this->_contactRepository->FindAllByUserId((int)$user['id']),
			'assignedContactIds' => $this->_monitorRepository->FindContactIdsByMonitorId($monitorId),
			'safetyContactIds' => $this->_monitorRepository->FindSafetyContactIdsByMonitorIdForUser($monitorId, (int)$user['id']),
			'monitorContacts' => $monitorContacts,
			'recipientConfigurationIssues' => $recipientConfigurationIssues,
			'defaultRecipientTemplateIssueCount' => $this->LocalizedRecipientDefaultIssueCount($mailTemplates),
			'documents' => $documents,
			'messageOverrides' => $messageOverrides,
			'mailTemplates' => $mailTemplates,
			'mailDefaults' => $mailDefaults,
			'portalTemplates' => $portalTemplates,
			'portalDefaults' => $portalDefaults,
			'ownerNotificationLocale' => $ownerNotificationLocale,
			'availableLocales' => $this->_availableLocales,
			'activeTab' => $this->ActiveEditorTab(),
			'activeMessageSection' => $this->ActiveMessageSection(),
		]);
	}

	/** @brief Updates monitor configuration while preserving retained monitor-contact IDs. */
	public function Update(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');
		$returnTab = $this->PostedEditorTab();
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->Flash('error', __('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		if (!empty($monitor['is_archived']))
		{
			$this->Flash('warning', __('monitors.archived.readonly.flash'));
			$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=' . $returnTab);
		}

		$values = $this->MonitorInput();
		$safetyContactIds = $this->AllowedContactIds((int)$user['id'], $this->_request->PostIntArray('safety_contact_ids'));

		if (
			!$this->ValidateMonitorInput($values, '/monitors/edit?id=' . $monitorId . '&tab=' . $returnTab, $monitorId)
			|| !$this->ValidateSafetyConfiguration($values, $safetyContactIds, (int)$user['id'], '/monitors/edit?id=' . $monitorId . '&tab=escalation')
		)
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
			$values['escalation_policy'],
			$values['safety_response_window_days'],
			$values['safety_reminder_interval_days'],
			$values['safety_max_reminders'],
			$values['safety_required_confirmations'],
			$values['safety_confirmation_days'] > 0 ? $values['safety_confirmation_days'] : null,
			null,
			null,
			null,
			null
		);
		$this->_monitorRepository->UpdateLocationSettingsForUser(
			$monitorId,
			(int)$user['id'],
			$values['location_check_in_enabled'],
			$values['portal_location_sharing_enabled'],
			$values['portal_location_history_limit']
		);
		$this->_monitorExecutionService->SynchronizeMonitorForUser($monitorId, (int)$user['id']);
		$this->_monitorRepository->ReplaceSafetyContactsForMonitor($monitorId, (int)$user['id'], $safetyContactIds);
		$this->_logger->Info('Monitor updated', ['user_id' => (int)$user['id'], 'monitor_id' => $monitorId]);

		if ($this->_request->PostBool('message_save_warning'))
		{
			$this->Flash('warning', __('monitors.messages.flash.saved_with_warnings'));
		}
		else
		{
			$this->Flash('success', __('monitors.edit.flash.updated', ['name' => $values['name']]));
		}

		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=' . $returnTab);
	}

	/** @brief Updates the monitor's language-specific default recipient messages. */
	public function UpdateMessages(): ?string
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$monitorId = $this->_request->PostInt('monitor_id');
		$returnSection = $this->PostedMessageSection();
		$asyncSave = $this->_request->PostBool('async_save');

		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, $userId);

		if ($monitor === null)
		{
			$this->Flash('error', __('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		if (!empty($monitor['is_archived']))
		{
			$this->Flash('warning', __('monitors.archived.readonly.flash'));
			$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages&section=' . $returnSection);
		}

		$hasDraftIssues = false;

		if ($returnSection === 'owner')
		{
			$dueTemplate = $this->TemplateInput('owner_due_notice');
			$reminderTemplate = $this->TemplateInput('owner_reminder');
			$redirect = '/monitors/edit?id=' . $monitorId . '&tab=messages&section=owner';

			$validationError = $this->TemplatePairError($dueTemplate, 'monitors.messages.owner_due_incomplete')
				?? $this->TemplatePairError($reminderTemplate, 'monitors.messages.owner_reminder_incomplete');

			if ($asyncSave && $validationError !== null)
			{
				return $this->MessageSaveJson(false, false, $validationError);
			}

			$this->ValidateTemplatePair($dueTemplate, 'monitors.messages.owner_due_incomplete', $redirect);
			$this->ValidateTemplatePair($reminderTemplate, 'monitors.messages.owner_reminder_incomplete', $redirect);
			$this->_messageRepository->ReplaceOwnerTemplateForMonitor($monitorId, $userId, 'owner_due_notice', $dueTemplate);
			$this->_messageRepository->ReplaceOwnerTemplateForMonitor($monitorId, $userId, 'owner_reminder', $reminderTemplate);
		}
		elseif ($returnSection === 'recipient')
		{
			$templates = $this->LocalizedTemplateInput('recipient_default');
			$this->_messageRepository->ReplaceLocalizedTemplatesForMonitor(
				$monitorId,
				$userId,
				'recipient_default',
				$templates
			);

			foreach ($templates as $template)
			{
				if (RecipientMessageValidator::Validate((string)$template['subject'], (string)$template['body_text']) !== [])
				{
					$hasDraftIssues = true;
					break;
				}
			}
		}
		elseif ($returnSection === 'safety')
		{
			$safetyTemplates = $this->LocalizedSafetyTemplateInput();
			$validationError = $this->LocalizedSafetyTemplateError($safetyTemplates);

			if ($asyncSave && $validationError !== null)
			{
				return $this->MessageSaveJson(false, false, $validationError);
			}

			if (!$this->ValidateLocalizedSafetyTemplates($safetyTemplates, '/monitors/edit?id=' . $monitorId . '&tab=messages&section=safety'))
			{
				return null;
			}

			$this->_messageRepository->ReplaceLocalizedTemplatesForMonitor($monitorId, $userId, 'safety_invitation', $safetyTemplates['safety_invitation']);
			$this->_messageRepository->ReplaceLocalizedTemplatesForMonitor($monitorId, $userId, 'safety_reminder', $safetyTemplates['safety_reminder']);
		}
		else
		{
			$portalTemplates = $this->LocalizedPortalContentInput();
			$expiryMode = $this->_request->PostString('recipient_portal_expiry_mode', 20);
			$expiryDays = match ($expiryMode)
			{
				'30' => 30,
				'90' => 90,
				'365' => 365,
				'custom' => $this->_request->PostInt('recipient_portal_expiry_custom_days'),
				default => null,
			};

			if ($expiryDays !== null && ($expiryDays < 1 || $expiryDays > 3650))
			{
				if ($asyncSave)
				{
					return $this->MessageSaveJson(false, false, __('monitors.messages.portal_expiry.invalid'));
				}

				$this->Flash('error', __('monitors.messages.portal_expiry.invalid'));
				$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages&section=portal');
			}

			$this->_monitorRepository->UpdateRecipientPortalAvailabilityForUser($monitorId, $userId, $expiryDays);
			$this->_messageRepository->ReplaceLocalizedPortalTemplatesForMonitor($monitorId, $userId, $portalTemplates);
		}

		$this->_logger->Info('Monitor messages/content updated', [
			'user_id' => $userId,
			'monitor_id' => $monitorId,
			'section' => $returnSection,
		]);

		if ($asyncSave)
		{
			return $this->MessageSaveJson(
				true,
				$hasDraftIssues,
				__($hasDraftIssues ? 'monitors.messages.flash.saved_with_warnings' : 'monitors.messages.flash.updated')
			);
		}

		$this->Flash(
			$hasDraftIssues ? 'warning' : 'success',
			__($hasDraftIssues ? 'monitors.messages.flash.saved_with_warnings' : 'monitors.messages.flash.updated')
		);
		$this->Redirect('/monitors/edit?id=' . $monitorId . '&tab=messages&section=' . $returnSection);
		return null;
	}

	/** @brief Deletes an owned monitor and all of its physical document files. */
	public function Delete(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');
		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if (is_array($monitor) && !empty($monitor['is_archived']))
		{
			$this->Flash('warning', __('monitors.archived.readonly.flash'));
			$this->Redirect('/monitors?view=archived');
		}

		if (is_array($monitor) && $this->_monitorRepository->HasReleaseHistoryForUser($monitorId, (int)$user['id']))
		{
			$this->Flash('warning', __('monitors.index.flash.delete_has_history'));
			$this->Redirect('/monitors');
		}

		if ($monitor !== null && $this->_documentService->DeleteMonitorForUser((int)$user['id'], $monitorId))
		{
			$this->_logger->Info('Monitor deleted', ['user_id' => (int)$user['id'], 'monitor_id' => $monitorId]);
			$this->Flash('success', __('monitors.index.flash.deleted', ['name' => (string)$monitor['name']]));
		}

		$this->Redirect('/monitors');
	}

	/** @brief Confirms every active monitor and restarts each monitor's own interval. */
	public function CheckIn(): void
	{
		$user = $this->RequireUser();
		$result = $this->_monitorExecutionService->CheckInAllActiveForUser(
			(int)$user['id'],
			'manual',
			CheckInLocation::FromRequest($this->_request)
		);

		if ($result['updated'] === 0)
		{
			$this->Flash('warning', __('monitors.check_in.none_active'));
		}
		else
		{
			$this->Flash('success', __(
				$result['updated'] === 1 ? 'monitors.check_in.success.one' : 'monitors.check_in.success.many',
				['count' => $result['updated']]
			));
		}

		$this->Redirect($this->RuntimeRedirect());
	}

	/** @brief Pauses an owned monitor and cancels its current schedule. */
	public function Pause(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');

		if ($this->_monitorExecutionService->PauseMonitorForUser($monitorId, (int)$user['id']))
		{
			$this->Flash('success', __('monitors.pause.success'));
		}
		else
		{
			$this->Flash('warning', __('monitors.pause.unchanged'));
		}

		$this->Redirect($this->RuntimeRedirect());
	}

	/** @brief Resumes an owned monitor with a fresh interval. */
	public function Resume(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');

		if ($this->_monitorExecutionService->ResumeMonitorForUser($monitorId, (int)$user['id']))
		{
			$this->Flash('success', __('monitors.resume.success'));
		}
		else
		{
			$this->Flash('warning', __('monitors.resume.unchanged'));
		}

		$this->Redirect($this->RuntimeRedirect());
	}


	/** @brief Resets an escalated or archived monitor and starts a fresh monitoring cycle. */
	public function ResetAndReactivate(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');

		if ($this->_monitorExecutionService->ResetAndReactivateMonitorForUser($monitorId, (int)$user['id']))
		{
			$this->Flash('success', __('monitors.reset.success'));
		}
		else
		{
			$this->Flash('warning', __('monitors.reset.unavailable'));
		}

		$this->Redirect('/monitors');
	}

	/** @brief Archives an escalated monitor without changing existing recipient portal access. */
	public function Archive(): void
	{
		$user = $this->RequireUser();
		$monitorId = $this->_request->PostInt('id');

		if ($this->_monitorExecutionService->ArchiveEscalatedMonitorForUser($monitorId, (int)$user['id']))
		{
			$this->Flash('success', __('monitors.archive.success'));
		}
		else
		{
			$this->Flash('warning', __('monitors.archive.unavailable'));
		}

		$this->Redirect('/monitors?view=archived');
	}

	/** @brief Forces a monitor due when application debug mode is enabled. */
	public function ForceDue(): void
	{
		$user = $this->RequireUser();

		if (!$this->_debugEnabled)
		{
			http_response_code(404);
			exit;
		}

		$monitorId = $this->_request->PostInt('id');

		if ($this->_monitorExecutionService->ForceDueForUser($monitorId, (int)$user['id']))
		{
			$this->Flash('success', __('monitors.force_due.success'));
		}
		else
		{
			$this->Flash('warning', __('monitors.force_due.unavailable'));
		}

		$this->Redirect($this->RuntimeRedirect());
	}

	/** @brief Queues and immediately attempts the current monitor's due notification in debug mode. */
	public function SendDueNotice(): void
	{
		$user = $this->RequireUser();

		if (!$this->_debugEnabled)
		{
			http_response_code(404);
			exit;
		}

		if (!$this->_mailEnabled)
		{
			$this->Flash('warning', __('monitors.send_due_notice.mail_disabled'));
			$this->Redirect($this->RuntimeRedirect());
		}

		$monitorId = $this->_request->PostInt('id');
		$jobId = $this->_notificationScheduler->QueueDueNoticeForMonitorForUser($monitorId, (int)$user['id']);

		if ($jobId === null)
		{
			$this->Flash('warning', __('monitors.send_due_notice.unavailable'));
			$this->Redirect($this->RuntimeRedirect());
		}

		$outcome = $this->_mailQueueWorker->ProcessById($jobId);
		$key = match ($outcome)
		{
			'sent' => 'monitors.send_due_notice.sent',
			'retrying' => 'monitors.send_due_notice.retrying',
			'failed' => 'monitors.send_due_notice.failed',
			'cancelled' => 'monitors.send_due_notice.cancelled',
			default => 'monitors.send_due_notice.queued',
		};
		$type = $outcome === 'sent' ? 'success' : ($outcome === 'failed' ? 'error' : 'warning');
		$this->Flash($type, __($key));
		$this->Redirect($this->RuntimeRedirect());
	}

	/** @brief Starts and immediately sends the configured safety-contact gate in debug mode. */
	public function SendSafetyContactNotifications(): void
	{
		$user = $this->RequireUser();

		if (!$this->_debugEnabled)
		{
			http_response_code(404);
			exit;
		}

		if (!$this->_mailEnabled)
		{
			$this->Flash('warning', __('monitors.send_safety_contacts.mail_disabled'));
			$this->Redirect($this->RuntimeRedirect());
		}

		$cycleId = $this->_escalationService->FindDebugSafetyGateCycleForUser(
			$this->_request->PostInt('id'),
			(int)$user['id']
		);

		if ($cycleId === null)
		{
			$this->Flash('warning', __('monitors.send_safety_contacts.unavailable'));
			$this->Redirect($this->RuntimeRedirect());
		}

		if ($this->_escalationService->StartSafetyGate($cycleId) < 1)
		{
			$this->Flash('error', __('monitors.send_safety_contacts.blocked'));
			$this->Redirect($this->RuntimeRedirect());
		}

		$sent = 0;
		$failed = 0;

		foreach ($this->_escalationService->FindPendingQueueIdsForSafetyInvitations($cycleId) as $queueId)
		{
			$this->_mailQueueWorker->PrepareImmediateDebugRetry($queueId);
			$outcome = $this->_mailQueueWorker->ProcessById($queueId);

			if ($outcome === 'sent')
			{
				$sent++;
			}
			elseif ($outcome === 'failed')
			{
				$failed++;
			}
		}

		$key = $sent > 0
			? 'monitors.send_safety_contacts.sent'
			: ($failed > 0 ? 'monitors.send_safety_contacts.failed' : 'monitors.send_safety_contacts.queued');
		$this->Flash($sent > 0 ? 'success' : ($failed > 0 ? 'error' : 'warning'), __($key, ['count' => $sent]));
		$this->Redirect($this->RuntimeRedirect());
	}

	/** @brief Moves the current safety-contact deadline into the past for a normal cron timeout test. */
	public function ExpireSafetyContactWindow(): void
	{
		$user = $this->RequireUser();

		if (!$this->_debugEnabled)
		{
			http_response_code(404);
			exit;
		}

		$monitorId = $this->_request->PostInt('id');

		if ($this->_escalationService->ExpireDebugSafetyWindowForUser($monitorId, (int)$user['id']))
		{
			$this->Flash('success', __('monitors.expire_safety.success'));
		}
		else
		{
			$this->Flash('warning', __('monitors.expire_safety.unavailable'));
		}

		$this->Redirect($this->RuntimeRedirect());
	}

	/** @brief Forces and sends an immutable recipient release through the real queue in debug mode. */
	public function SendRecipientNotifications(): void
	{
		$user = $this->RequireUser();

		if (!$this->_debugEnabled)
		{
			http_response_code(404);
			exit;
		}

		if (!$this->_mailEnabled)
		{
			$this->Flash('warning', __('monitors.send_recipients.mail_disabled'));
			$this->Redirect($this->RuntimeRedirect());
		}

		$cycleId = $this->_escalationService->PrepareDebugRecipientReleaseForUser(
			$this->_request->PostInt('id'),
			(int)$user['id']
		);

		if ($cycleId === null)
		{
			$this->Flash('warning', __('monitors.send_recipients.unavailable'));
			$this->Redirect($this->RuntimeRedirect());
		}

		$release = $this->_escalationService->StageRecipientRelease($cycleId);

		if ($release['status'] === 'blocked')
		{
			$this->Flash('error', $this->RecipientReleaseBlockedMessage((array)($release['issues'] ?? [])));
			$this->Redirect($this->RuntimeRedirect());
		}

		$sent = 0;
		$failed = 0;

		foreach ($this->_escalationService->FindPendingQueueIdsForRelease($release['release_id']) as $queueId)
		{
			$this->_mailQueueWorker->PrepareImmediateDebugRetry($queueId);
			$outcome = $this->_mailQueueWorker->ProcessById($queueId);

			if ($outcome === 'sent')
			{
				$sent++;
			}
			elseif ($outcome === 'failed')
			{
				$failed++;
			}
		}

		$key = $sent > 0
			? 'monitors.send_recipients.sent'
			: ($failed > 0 ? 'monitors.send_recipients.failed' : 'monitors.send_recipients.queued');
		$this->Flash($sent > 0 ? 'success' : ($failed > 0 ? 'error' : 'warning'), __($key, ['count' => $sent]));
		$this->Redirect($this->RuntimeRedirect());
	}


	/**
	 * @brief Counts invalid non-empty monitor-wide recipient default drafts across installed languages.
	 * @param array<string, array<string, array{subject: string, body_text: string}>> $mailTemplates Monitor-wide templates.
	 */
	private function LocalizedRecipientDefaultIssueCount(array $mailTemplates): int
	{
		$count = 0;

		foreach ((array)($mailTemplates['recipient_default'] ?? []) as $template)
		{
			if (RecipientMessageValidator::Validate(
				(string)($template['subject'] ?? ''),
				(string)($template['body_text'] ?? '')
			) !== [])
			{
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @brief Returns validation issues for the effective message of each configured recipient.
	 * @param array<int, array<string, mixed>> $monitorContacts Monitor recipient assignments.
	 * @param array<int, array<string, string>> $messageOverrides Personal recipient templates keyed by assignment ID.
	 * @param array<string, array<string, array{subject: string, body_text: string}>> $mailTemplates Monitor-wide localized templates.
	 * @return array<int, array{source: string, locale: string, issues: array<int, string>}>
	 */
	private function RecipientConfigurationIssues(array $monitorContacts, array $messageOverrides, array $mailTemplates): array
	{
		$result = [];

		foreach ($monitorContacts as $monitorContact)
		{
			$assignmentId = (int)$monitorContact['id'];
			$locale = (string)($monitorContact['notification_locale'] ?? 'en');
			$override = $messageOverrides[$assignmentId] ?? null;
			$source = is_array($override) ? 'personal' : 'default';
			$template = is_array($override)
				? $override
				: (array)($mailTemplates['recipient_default'][$locale] ?? ['subject' => '', 'body_text' => '']);
			$issues = RecipientMessageValidator::Validate(
				(string)($template['subject'] ?? ''),
				(string)($template['body_text'] ?? '')
			);

			if (!EmailAddressCollection::HasChecked($monitorContact))
			{
				array_unshift($issues, 'unchecked_recipient');
			}

			if ($issues !== [])
			{
				$result[$assignmentId] = [
					'source' => $source,
					'locale' => $locale,
					'issues' => array_values(array_unique($issues)),
				];
			}
		}

		return $result;
	}

	/**
	 * @brief Builds an actionable debug-release error including affected recipient names.
	 * @param array<int, array{reason?: string, recipient_name?: string}> $issues Release validation issues.
	 */
	private function RecipientReleaseBlockedMessage(array $issues): string
	{
		if ($issues === [])
		{
			return __('monitors.send_recipients.blocked');
		}

		$grouped = [];

		foreach ($issues as $issue)
		{
			$reason = (string)($issue['reason'] ?? '');
			$name = trim((string)($issue['recipient_name'] ?? ''));

			if ($reason === '')
			{
				continue;
			}

			$grouped[$reason] ??= [];

			if ($name !== '')
			{
				$grouped[$reason][] = $name;
			}
		}

		$parts = [];

		foreach ($grouped as $reason => $names)
		{
			$names = array_values(array_unique($names));
			$key = 'monitors.send_recipients.blocked_reason.' . $reason;
			$parts[] = __($key, ['recipients' => implode(', ', $names)]);
		}

		return __('monitors.send_recipients.blocked_detailed', ['details' => implode(' ', $parts)]);
	}

	/**
	 * @brief Reads and bounds monitor configuration input.
	 * @return array{name: string, description: string, check_interval_days: int, response_window_days: int, reminder_interval_days: int, max_reminders: int, escalation_policy: string, safety_response_window_days: int, safety_reminder_interval_days: int, safety_max_reminders: int, safety_required_confirmations: int, safety_confirmation_days: int, location_check_in_enabled: bool, portal_location_sharing_enabled: bool, portal_location_history_limit: int}
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
			'escalation_policy' => in_array($this->_request->PostString('escalation_policy', 30), ['direct', 'safety_contact'], true)
				? $this->_request->PostString('escalation_policy', 30)
				: 'direct',
			'safety_response_window_days' => $this->_request->PostInt('safety_response_window_days', 3),
			'safety_reminder_interval_days' => $this->_request->PostInt('safety_reminder_interval_days', 1),
			'safety_max_reminders' => $this->_request->PostInt('safety_max_reminders', 1),
			'safety_required_confirmations' => $this->_request->PostInt('safety_required_confirmations', 1),
			'safety_confirmation_days' => $this->_request->PostInt('safety_confirmation_days', 0),
			'location_check_in_enabled' => $this->_request->PostBool('location_check_in_enabled'),
			'portal_location_sharing_enabled' => $this->_request->PostBool('portal_location_sharing_enabled'),
			'portal_location_history_limit' => $this->_request->PostInt('portal_location_history_limit', 5),
		];
	}

	/**
	 * @brief Reads language-specific monitor-wide recipient portal content from POST data.
	 * @return array<string, array{message_text: string, intro_text: string}> Content keyed by locale.
	 */
	private function LocalizedPortalContentInput(): array
	{
		$result = [];

		foreach ($this->_availableLocales as $locale)
		{
			$fieldLocale = preg_replace('/[^a-z0-9_]/i', '_', $locale);
			$result[$locale] = [
				'message_text' => '',
				'intro_text' => $this->_request->PostString('portal_intro_' . $fieldLocale, 1000000, false),
			];
		}

		return $result;
	}

	/**
	 * @brief Reads one owner-facing monitor mail template from POST data.
	 * @param string $templateKey Template field prefix.
	 * @return array{subject: string, body_text: string} Owner template.
	 */
	private function TemplateInput(string $templateKey): array
	{
		return [
			'subject' => $this->_request->PostString($templateKey . '_subject', 255),
			'body_text' => $this->_request->PostString($templateKey . '_body', 1000000, false),
		];
	}

	/**
	 * @brief Validates one optional owner subject/body override.
	 * @param array{subject: string, body_text: string} $template Template values.
	 * @param string $incompleteKey Translation key for an incomplete pair.
	 * @param string $redirect Redirect target.
	 */
	private function ValidateTemplatePair(array $template, string $incompleteKey, string $redirect): void
	{
		$error = $this->TemplatePairError($template, $incompleteKey);

		if ($error !== null)
		{
			$this->Flash('error', $error);
			$this->Redirect($redirect);
		}
	}

	/** @brief Returns the localized validation error for one optional subject/body pair. */
	private function TemplatePairError(array $template, string $incompleteKey): ?string
	{
		$subject = trim((string)($template['subject'] ?? ''));
		$body = trim((string)($template['body_text'] ?? ''));
		return (($subject === '') !== ($body === '')) ? __($incompleteKey) : null;
	}

	/**
	 * @brief Reads one language-specific monitor-wide template family from POST data.
	 * @param string $templateKey Template field prefix.
	 * @return array<string, array{subject: string, body_text: string}> Templates keyed by locale.
	 */
	private function LocalizedTemplateInput(string $templateKey): array
	{
		$result = [];

		foreach ($this->_availableLocales as $locale)
		{
			$fieldLocale = preg_replace('/[^a-z0-9_]/i', '_', $locale);
			$result[$locale] = [
				'subject' => $this->_request->PostString($templateKey . '_subject_' . $fieldLocale, 255),
				'body_text' => $this->_request->PostString($templateKey . '_body_' . $fieldLocale, 1000000, false),
			];
		}

		return $result;
	}

	/**
	 * @brief Reads localized safety invitation and reminder templates.
	 * @return array<string, array<string, array{subject: string, body_text: string}>>
	 */
	private function LocalizedSafetyTemplateInput(): array
	{
		return [
			'safety_invitation' => $this->LocalizedTemplateInput('safety_invitation'),
			'safety_reminder' => $this->LocalizedTemplateInput('safety_reminder'),
		];
	}

	/**
	 * @brief Validates localized subject/body pairs and optional required safety URL.
	 * @param array<string, array{subject: string, body_text: string}> $templates Templates keyed by locale.
	 * @param string $incompleteKey Translation key for incomplete pairs.
	 * @param bool $requireUrl Whether non-empty bodies must include {url}.
	 * @param string $redirect Redirect target.
	 * @param string $urlRequiredKey Translation key for a missing required URL placeholder.
	 */
	private function ValidateLocalizedTemplatePairs(
		array $templates,
		string $incompleteKey,
		bool $requireUrl,
		string $redirect,
		string $urlRequiredKey = 'monitors.escalation.messages.flash.url_required'
	): bool
	{
		$error = $this->LocalizedTemplatePairsError($templates, $incompleteKey, $requireUrl, $urlRequiredKey);

		if ($error !== null)
		{
			$this->Flash('error', $error);
			$this->Redirect($redirect);
		}

		return true;
	}

	/** @brief Returns the first validation error for localized optional mail template pairs. */
	private function LocalizedTemplatePairsError(
		array $templates,
		string $incompleteKey,
		bool $requireUrl,
		string $urlRequiredKey = 'monitors.escalation.messages.flash.url_required'
	): ?string
	{
		foreach ($templates as $templateLocale => $template)
		{
			$subject = trim((string)($template['subject'] ?? ''));
			$body = trim((string)($template['body_text'] ?? ''));

			if (($subject === '') !== ($body === ''))
			{
				return __($incompleteKey);
			}

			if ($requireUrl && $body !== '' && !str_contains($body, '{url}'))
			{
				return __($urlRequiredKey, ['language' => notification_language_name((string)$templateLocale)]);
			}

			if ($requireUrl && str_contains($subject, '{url}'))
			{
				return __('mail.templates.flash.url_in_subject');
			}
		}

		return null;
	}

	/** @brief Validates all localized safety-contact mail templates. */
	private function ValidateLocalizedSafetyTemplates(array $templates, string $redirect): bool
	{
		return $this->ValidateLocalizedTemplatePairs(
			(array)($templates['safety_invitation'] ?? []),
			'monitors.escalation.messages.flash.invitation_incomplete',
			true,
			$redirect
		) && $this->ValidateLocalizedTemplatePairs(
			(array)($templates['safety_reminder'] ?? []),
			'monitors.escalation.messages.flash.reminder_incomplete',
			true,
			$redirect
		);
	}

	/** @brief Returns the first validation error across both safety-contact mail template families. */
	private function LocalizedSafetyTemplateError(array $templates): ?string
	{
		return $this->LocalizedTemplatePairsError(
			(array)($templates['safety_invitation'] ?? []),
			'monitors.escalation.messages.flash.invitation_incomplete',
			true
		) ?? $this->LocalizedTemplatePairsError(
			(array)($templates['safety_reminder'] ?? []),
			'monitors.escalation.messages.flash.reminder_incomplete',
			true
		);
	}

	/** @brief Encodes the response used by the Monitor Editor's asynchronous message-save step. */
	private function MessageSaveJson(bool $ok, bool $warning, string $message): string
	{
		header('Content-Type: application/json; charset=utf-8');
		return (string)json_encode([
			'ok' => $ok,
			'warning' => $warning,
			'message' => $message,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/** @brief Returns a whitelisted editor tab from the shared settings form. */
	private function PostedEditorTab(): string
	{
		$tab = $this->_request->PostString('active_tab', 20);
		return in_array($tab, ['details', 'schedule', 'documents', 'recipients', 'escalation', 'messages', 'review'], true) ? $tab : 'details';
	}

	/** @brief Returns a whitelisted Messages & content subsection posted by its form. */
	private function PostedMessageSection(): string
	{
		$section = $this->_request->PostString('message_section', 20);
		return in_array($section, ['owner', 'recipient', 'safety', 'portal'], true) ? $section : 'owner';
	}

	/** @brief Returns a supported Messages & content subsection from the query. */
	private function ActiveMessageSection(): string
	{
		$section = $this->_request->QueryString('section', 20);
		return in_array($section, ['owner', 'recipient', 'safety', 'portal'], true) ? $section : 'owner';
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
			|| (int)$values['safety_response_window_days'] < 1 || (int)$values['safety_response_window_days'] > 365
			|| (int)$values['safety_reminder_interval_days'] < 1 || (int)$values['safety_reminder_interval_days'] > 365
			|| (int)$values['safety_max_reminders'] < 0 || (int)$values['safety_max_reminders'] > 100
			|| (int)$values['safety_required_confirmations'] < 1 || (int)$values['safety_required_confirmations'] > 100
			|| (int)$values['safety_confirmation_days'] < 0 || (int)$values['safety_confirmation_days'] > 3650
			|| (int)$values['portal_location_history_limit'] < 1 || (int)$values['portal_location_history_limit'] > 20
		)
		{
			$this->_logger->Warning('Monitor validation failed: invalid numeric bounds', ['monitor_id' => $monitorId]);
			$this->Flash('error', __($monitorId > 0 ? 'monitors.edit.flash.invalidnumbers' : 'monitors.add.flash.invalidnumbers'));
			$this->Redirect($redirect);
		}

		return true;
	}

	/** @brief Validates optional safety-contact configuration against current owned contacts. */
	private function ValidateSafetyConfiguration(array $values, array $contactIds, int $userId, string $redirect): bool
	{
		if ((string)$values['escalation_policy'] !== 'safety_contact')
		{
			return true;
		}

		if ($contactIds === [] || (int)$values['safety_required_confirmations'] > count($contactIds))
		{
			$this->Flash('error', __('monitors.escalation.flash.invalid_contacts'));
			$this->Redirect($redirect);
		}

		$contacts = $this->_contactRepository->FindAllByUserId($userId);
		$byId = [];

		foreach ($contacts as $contact)
		{
			$byId[(int)$contact['id']] = $contact;
		}

		foreach ($contactIds as $contactId)
		{
			if (!isset($byId[$contactId]) || !EmailAddressCollection::HasChecked($byId[$contactId]))
			{
				$this->Flash('error', __('monitors.escalation.flash.unchecked_contact'));
				$this->Redirect($redirect);
			}
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

	/** @brief Restricts runtime-action redirects to known monitor pages. @return string */
	private function RuntimeRedirect(): string
	{
		$target = $this->_request->PostString('redirect', 200);

		if (in_array($target, ['/', '/monitors', '/monitors?view=archived'], true))
		{
			return $target;
		}

		return preg_match('/^\/monitors\/edit\?id=\d+&tab=review$/', $target) === 1 ? $target : '/monitors';
	}

	/** @brief Returns a supported monitor editor tab from the query. @return string */
	private function ActiveEditorTab(): string
	{
		$tab = $this->_request->QueryString('tab', 20);

		return in_array($tab, ['details', 'schedule', 'documents', 'recipients', 'escalation', 'messages', 'review'], true) ? $tab : 'details';
	}
}
