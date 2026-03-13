<?php

declare(strict_types=1);

namespace Pulse\Controllers;

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
	 * @param ContactRepository $contactRepository Contact repository.
	 */
	public function __construct(
		\Pulse\Core\View $view,
		\Pulse\Core\Session $session,
		\Pulse\Services\AuthService $auth,
		\Pulse\Core\Logger $logger,
		ContactRepository $contactRepository
	)
	{
		parent::__construct($view, $session, $auth, $logger);
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

		$name = trim((string)($_POST['name'] ?? ''));
		$email = trim((string)($_POST['email'] ?? ''));
		$cellPhone = trim((string)($_POST['cell_phone'] ?? ''));
		$notes = trim((string)($_POST['notes'] ?? ''));

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

		$this->Flash('success', e__('contacts.add.flash.created'));
		$this->Redirect('/contacts');
	}

	/**
	 * @brief Deletes a contact owned by the current user.
	 */
	public function Delete(): void
	{
		$user = $this->RequireUser();
		$contactId = (int)($_POST['id'] ?? 0);

		if ($contactId > 0)
		{
			$this->_contactRepository->DeleteForUser($contactId, (int)$user['id']);
			$this->Flash('success', e__('contacts.index.flash.deleted'));
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
		$contactId = (int)($_GET['id'] ?? 0);

		if ($contactId <= 0)
		{
			$this->Flash('error', e__('contacts.edit.flash.notfound'));
			$this->Redirect('/contacts');
		}

		$contact = $this->_contactRepository->FindByIdForUser($contactId, (int)$user['id']);

		if ($contact === null)
		{
			$this->Flash('error', e__('contacts.edit.flash.notfound'));
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

		$contactId = (int)($_POST['id'] ?? 0);
		$name = trim((string)($_POST['name'] ?? ''));
		$email = trim((string)($_POST['email'] ?? ''));
		$cellPhone = trim((string)($_POST['cell_phone'] ?? ''));
		$notes = trim((string)($_POST['notes'] ?? ''));

		if ($contactId <= 0)
		{
			$this->Flash('error', e__('contacts.edit.flash.notfound'));
			$this->Redirect('/contacts');
		}

		$existingContact = $this->_contactRepository->FindByIdForUser($contactId, (int)$user['id']);

		if ($existingContact === null)
		{
			$this->Flash('error', e__('contacts.edit.flash.notfound'));
			$this->Redirect('/contacts');
		}

		if ($name === '' || $email === '')
		{
			$this->Flash('error', e__('contacts.edit.flash.required'));
			$this->Redirect('/contacts/edit?id=' . $contactId);
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		{
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

		$this->Flash('success', e__('contacts.edit.flash.updated'));
		$this->Redirect('/contacts');
	}
}