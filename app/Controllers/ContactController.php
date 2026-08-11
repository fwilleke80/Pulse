<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use \Pulse\Core\View;
use \Pulse\Core\Session;
use \Pulse\Core\Logger;
use \Pulse\Core\Request;
use \Pulse\Services\AuthService;
use Pulse\Repositories\ContactRepository;

/**
 * @brief Controller for contact management.
 */
class ContactController extends BaseController
{
	private ContactRepository $_contactRepository;

	/**
	 * @brief Constructs the contact controller.
	 * @param \Pulse\Core\View $view View renderer.
	 * @param \Pulse\Core\Session $session Session service.
	 * @param \Pulse\Services\AuthService $auth Authentication service.
	 * @param \Pulse\Core\Logger $logger Application logger.
	 * @param Request $request Current request.
	 * @param ContactRepository $contactRepository Contact repository.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		ContactRepository $contactRepository
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_contactRepository = $contactRepository;
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
		$cellPhone = $this->_request->PostString('cell_phone', 50);
		$notes = $this->_request->PostString('notes', 10000);

		if ($name === '' || $email === '')
		{
			$this->Flash('error', e__('contacts.add.flash.required'));
			$this->Redirect('/contacts/new');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		{
			$this->Flash('error', e__('contacts.add.flash.invalidemail'));
			$this->Redirect('/contacts/new');
		}

		$this->_contactRepository->CreateForUser(
			(int)$user['id'],
			$name,
			$email,
			$cellPhone !== '' ? $cellPhone : null,
			$notes !== '' ? $notes : null
		);

		$this->_logger->Info('Contact created', ['user_id' => (int)$user['id']]);
		$this->Flash('success', e__('contacts.add.flash.created', ['name' => $name]));
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
			$this->Flash('success', e__('contacts.index.flash.deleted', ['name' => $contact['name'] ?? '']));
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

		if ($contactId <= 0)
		{
			$this->Flash('error', e__('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		$contact = $this->_contactRepository->FindByIdForUser($contactId, (int)$user['id']);

		if ($contact === null)
		{
			$this->Flash('error', e__('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		return $this->_view->Render('contacts.edit', [
			'user' => $user,
			'contact' => $contact,
		]);
	}

	/**
	 * @brief Updates an existing contact for the current user.
	 */
	public function Update(): void
	{
		$user = $this->RequireUser();

		$contactId = $this->_request->PostInt('id');
		$name = $this->_request->PostString('name', 255);
		$email = $this->_request->PostString('email', 255);
		$cellPhone = $this->_request->PostString('cell_phone', 50);
		$notes = $this->_request->PostString('notes', 10000);

		if ($contactId <= 0)
		{
			$this->_logger->Warning('User ID ' . $user['id'] . ' attempted to update contact with invalid ID: ' . $contactId);
			$this->Flash('error', e__('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		$existingContact = $this->_contactRepository->FindByIdForUser($contactId, (int)$user['id']);

		if ($existingContact === null)
		{
			$this->_logger->Warning('User ID ' . $user['id'] . ' attempted to update contact ID ' . $contactId . ' which does not exist');
			$this->Flash('error', e__('contacts.edit.flash.notfound', ['id' => $contactId]));
			$this->Redirect('/contacts');
		}

		if ($name === '' || $email === '')
		{
			$this->_logger->Warning('User ID ' . $user['id'] . ' attempted to update contact ID ' . $contactId . ' with missing required fields');
			$this->Flash('error', e__('contacts.edit.flash.required'));
			$this->Redirect('/contacts/edit?id=' . $contactId);
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		{
			$this->_logger->Warning('Contact update rejected due to invalid email', ['user_id' => (int)$user['id'], 'contact_id' => $contactId]);
			$this->Flash('error', e__('contacts.edit.flash.invalidemail'));
			$this->Redirect('/contacts/edit?id=' . $contactId);
		}

		$this->_contactRepository->UpdateForUser(
			$contactId,
			(int)$user['id'],
			$name,
			$email,
			$cellPhone !== '' ? $cellPhone : null,
			$notes !== '' ? $notes : null
		);

		$this->_logger->Info('Contact updated', ['user_id' => (int)$user['id'], 'contact_id' => $contactId]);
		$this->Flash('success', e__('contacts.edit.flash.updated', ['name' => $name]));
		$this->Redirect('/contacts');
	}
}
