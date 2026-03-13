<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Repositories\MonitorRepository;
use Pulse\Repositories\ContactRepository;

/**
 * @brief Controller for monitor management.
 */
class MonitorController extends BaseController
{
	private MonitorRepository $_monitorRepository;
	private ContactRepository $_contactRepository;

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
		MonitorRepository $monitorRepository,
		ContactRepository $contactRepository
	)
	{
		parent::__construct($view, $session, $auth);
		$this->_monitorRepository = $monitorRepository;
		$this->_contactRepository = $contactRepository;
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
		$isTestMode = isset($_POST['is_test_mode']);
		$contactIds = isset($_POST['contact_ids']) && is_array($_POST['contact_ids'])
			? array_map('intval', $_POST['contact_ids'])
			: [];

		if ($name === '')
		{
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
			$isPaused,
			$isTestMode
		);

		$this->_monitorRepository->ReplaceContactsForMonitor($monitorId, $contactIds);

		$this->Flash('success', e__('monitors.add.flash.created'));
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
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$monitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($monitor === null)
		{
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$contacts = $this->_contactRepository->FindAllByUserId((int)$user['id']);
		$assignedContactIds = $this->_monitorRepository->FindContactIdsByMonitorId($monitorId);

		return $this->_view->Render('monitors.edit', [
			'user' => $user,
			'monitor' => $monitor,
			'contacts' => $contacts,
			'assignedContactIds' => $assignedContactIds,
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
		$isTestMode = isset($_POST['is_test_mode']);
		$contactIds = isset($_POST['contact_ids']) && is_array($_POST['contact_ids'])
			? array_map('intval', $_POST['contact_ids'])
			: [];

		if ($monitorId <= 0)
		{
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		$existingMonitor = $this->_monitorRepository->FindByIdForUser($monitorId, (int)$user['id']);

		if ($existingMonitor === null)
		{
			$this->Flash('error', e__('monitors.edit.flash.notfound'));
			$this->Redirect('/monitors');
		}

		if ($name === '')
		{
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
			$isPaused,
			$isTestMode
		);

		$this->_monitorRepository->ReplaceContactsForMonitor($monitorId, $contactIds);

		$this->Flash('success', e__('monitors.edit.flash.updated'));
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
			$this->_monitorRepository->DeleteForUser($monitorId, (int)$user['id']);
			$this->Flash('success', e__('monitors.index.flash.deleted'));
		}

		$this->Redirect('/monitors');
	}
}