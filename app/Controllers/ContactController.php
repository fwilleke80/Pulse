<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\EmailAddressValidator;
use Pulse\Core\Logger;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\ContactRepository;
use Pulse\Services\AuthService;

/**
 * @brief Controller for contact management.
 */
class ContactController extends BaseController
{
	private ContactRepository $_contactRepository;
	private NotificationLanguage $_notificationLanguage;

	/**
	 * @brief Constructs the contact controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param Logger $logger Application logger.
	 * @param Request $request Current request.
	 * @param ContactRepository $contactRepository Contact repository.
	 * @param NotificationLanguage $notificationLanguage Recipient-language resolver.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		ContactRepository $contactRepository,
		NotificationLanguage $notificationLanguage
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_contactRepository = $contactRepository;
		$this->_notificationLanguage = $notificationLanguage;
	}

	/**
	 * @brief Displays the contact list.
	 * @return string
	 */
	public function Index(): string
	{
		$user = $this->RequireUser();

		$contacts = $this->_contactRepository->FindAllByUserId((int)$user['id']);

		return $this->_view->Render('contacts.index', [
			'contacts' => $contacts,
			'user' => $user,
		]);
	}

	/**
	 * @brief Displays the form for creating a new contact.
	 * @return string
	 */
	public function New(): string
	{
		$user = $this->RequireUser();

		return $this->_view->Render('contacts.new', [
			'user' => $user,
			'notificationLocales' => $this->_notificationLanguage->SupportedLocales(),
			'notificationLocale' => $this->_notificationLanguage->Resolve(null),
		]);
	}

	/**
	 * @brief Creates a new contact for the current user.
	 */
	public function Create(): void
	{
		$user = $this->RequireUser();

		$name = $this->_request->PostString('name', 255);
		$email = $this->_request->PostString('email', 255);
		$notificationLocale = $this->_request->PostString('notification_locale', 10);
		$cellPhone = $this->_request->PostString('cell_phone', 50);
		$notes = $this->_request->PostString('notes', 10000);
		$emailChecked = $this->_request->PostBool('email_checked');

		if ($name === '' || $email === '')
		{
			$this->Flash('error', __('contacts.add.flash.required'));
			$this->Redirect('/contacts/new');
		}

		if (!EmailAddressValidator::IsValid($email))
		{
			$this->Flash('error', __('contacts.add.flash.invalidemail'));
			$this->Redirect('/contacts/new');
		}

		if (!$this->_notificationLanguage->IsSupported($notificationLocale))
		{
			$this->Flash('error', __('contacts.flash.invalid_language'));
			$this->Redirect('/contacts/new');
		}

		if (!$emailChecked)
		{
			$this->Flash('error', __('contacts.add.flash.email_not_checked'));
			$this->Redirect('/contacts/new');
		}

		$this->_contactRepository->CreateForUser(
			(int)$user['id'],
			$name,
			$email,
			$notificationLocale,
			$emailChecked,
			$cellPhone !== '' ? $cellPhone : null,
			$notes !== '' ? $notes : null
		);

		$this->_logger->Info('Contact created', ['user_id' => (int)$user['id']]);
		$suggestion = EmailAddressValidator::Suggestion($email);
		$this->Flash(
			$suggestion === null ? 'success' : 'warning',
			$suggestion === null
				? __('contacts.add.flash.created', ['name' => $name])
				: __('contacts.flash.suspicious_email', ['suggestion' => $suggestion])
		);
		$this->Redirect('/contacts');
	}

	/**
	 * @brief Deletes a contact owned by the current user.
	 */
	public function Delete(): void
	{
		$user = $this->RequireUser();
		$contactId = $this->_request->PostInt('id');

		if ($contactId > 0)
		{
			$contact = $this->_contactRepository->FindByIdForUser($contactId, (int)$user['id']) ?? null;
			$this->_contactRepository->DeleteForUser($contactId, (int)$user['id']);
			$this->_logger->Info('Deleted contact with ID ' . $contactId . ' for user ID ' . $user['id']);
			$this->Flash('success', __('contacts.index.flash.deleted', ['name' => $contact['name'] ?? '']));
		}

		$this->Redirect('/contacts');
	}

	/**
	 * @brief Displays the form for editing an existing contact.
	 * @return string
	 */
	public function Edit(): string
	{
		$user = $this->RequireUser();
		$contactId = $this->_request->QueryInt('id');
		$returnMonitorId = max(0, $this->_request->QueryInt('return_monitor_id'));

		if ($contactId <= 0)
		{
			$this->Flash('error', __('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		$contact = $this->_contactRepository->FindByIdForUser($contactId, (int)$user['id']);

		if ($contact === null)
		{
			$this->Flash('error', __('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		return $this->_view->Render('contacts.edit', [
			'user' => $user,
			'contact' => $contact,
			'returnMonitorId' => $returnMonitorId,
			'notificationLocales' => $this->_notificationLanguage->SupportedLocales(),
			'notificationLocale' => $this->_notificationLanguage->Resolve(
				isset($contact['notification_locale']) ? (string)$contact['notification_locale'] : null
			),
		]);
	}

	/**
	 * @brief Updates an existing contact for the current user.
	 */
	public function Update(): void
	{
		$user = $this->RequireUser();

		$contactId = $this->_request->PostInt('id');
		$returnMonitorId = max(0, $this->_request->PostInt('return_monitor_id'));
		$name = $this->_request->PostString('name', 255);
		$email = $this->_request->PostString('email', 255);
		$notificationLocale = $this->_request->PostString('notification_locale', 10);
		$cellPhone = $this->_request->PostString('cell_phone', 50);
		$notes = $this->_request->PostString('notes', 10000);
		$emailChecked = $this->_request->PostBool('email_checked');

		if ($contactId <= 0)
		{
			$this->_logger->Warning('User ID ' . $user['id'] . ' attempted to update contact with invalid ID: ' . $contactId);
			$this->Flash('error', __('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		$existingContact = $this->_contactRepository->FindByIdForUser($contactId, (int)$user['id']);

		if ($existingContact === null)
		{
			$this->_logger->Warning('User ID ' . $user['id'] . ' attempted to update contact ID ' . $contactId . ' which does not exist');
			$this->Flash('error', __('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		if ($name === '' || $email === '')
		{
			$this->_logger->Warning('User ID ' . $user['id'] . ' attempted to update contact ID ' . $contactId . ' with missing required fields');
			$this->Flash('error', __('contacts.edit.flash.required'));
			$this->Redirect($this->ContactEditPath($contactId, $returnMonitorId));
		}

		if (!EmailAddressValidator::IsValid($email))
		{
			$this->_logger->Warning('Contact update rejected due to invalid email', ['user_id' => (int)$user['id'], 'contact_id' => $contactId]);
			$this->Flash('error', __('contacts.edit.flash.invalidemail'));
			$this->Redirect($this->ContactEditPath($contactId, $returnMonitorId));
		}

		if (!$this->_notificationLanguage->IsSupported($notificationLocale))
		{
			$this->Flash('error', __('contacts.flash.invalid_language'));
			$this->Redirect($this->ContactEditPath($contactId, $returnMonitorId));
		}

		if (!$emailChecked)
		{
			$this->Flash('error', __('contacts.edit.flash.email_not_checked'));
			$this->Redirect($this->ContactEditPath($contactId, $returnMonitorId));
		}

		$this->_contactRepository->UpdateForUser(
			$contactId,
			(int)$user['id'],
			$name,
			$email,
			$notificationLocale,
			$emailChecked,
			$cellPhone !== '' ? $cellPhone : null,
			$notes !== '' ? $notes : null
		);

		$this->_logger->Info('Contact updated', ['user_id' => (int)$user['id'], 'contact_id' => $contactId]);
		$suggestion = EmailAddressValidator::Suggestion($email);
		$this->Flash(
			$suggestion === null ? 'success' : 'warning',
			$suggestion === null
				? __('contacts.edit.flash.updated', ['name' => $name])
				: __('contacts.flash.suspicious_email', ['suggestion' => $suggestion])
		);
		$this->Redirect(
			$returnMonitorId > 0
				? '/monitors/edit?id=' . $returnMonitorId . '&tab=recipients'
				: '/contacts'
		);
	}

	/**
	 * @brief Builds the contact-edit path while preserving an optional monitor return target.
	 * @param int $contactId Contact identifier.
	 * @param int $returnMonitorId Monitor identifier to return to after saving.
	 * @return string
	 */
	private function ContactEditPath(int $contactId, int $returnMonitorId): string
	{
		$path = '/contacts/edit?id=' . $contactId;

		return $returnMonitorId > 0 ? $path . '&return_monitor_id=' . $returnMonitorId : $path;
	}
}
