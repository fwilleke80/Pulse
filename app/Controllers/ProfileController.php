<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;

/**
 * @brief Controller for the user profile page.
 */
class ProfileController extends BaseController
{
	private UserRepository $_userRepository;

	/**
	 * @brief Constructs the profile controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param UserRepository $userRepository User repository.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		UserRepository $userRepository
	)
	{
		parent::__construct($view, $session, $auth);
		$this->_userRepository = $userRepository;
	}

	/**
	 * @brief Displays the profile page.
	 * @return string
	 */
	public function Index(): string
	{
		$user = $this->RequireUser();

		return $this->_view->Render('profile.index', [
			'user' => $user,
		]);
	}

	/**
	 * @brief Updates the basic profile data.
	 */
	public function Update(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];

		$displayName = trim((string)($_POST['display_name'] ?? ''));
		$email = trim((string)($_POST['email'] ?? ''));

		if ($displayName === '' || $email === '')
		{
			$this->Flash('error', __('profile.flash.update.required'));
			$this->Redirect('/profile');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		{
			$this->Flash('error', __('profile.flash.update.invalid_email'));
			$this->Redirect('/profile');
		}

		$existingUser = $this->_userRepository->FindByEmailExcludingUserId($userId, $email);

		if ($existingUser !== null)
		{
			$this->Flash('error', __('profile.flash.update.email_taken'));
			$this->Redirect('/profile');
		}

		$this->_userRepository->UpdateProfile($userId, $displayName, $email);

		$this->Flash('success', __('profile.flash.update.success'));
		$this->Redirect('/profile');
	}

	/**
	 * @brief Changes the password of the current user.
	 */
	public function ChangePassword(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];

		$currentPassword = (string)($_POST['current_password'] ?? '');
		$newPassword = (string)($_POST['new_password'] ?? '');
		$confirmPassword = (string)($_POST['confirm_password'] ?? '');

		if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '')
		{
			$this->Flash('error', __('profile.flash.password.required'));
			$this->Redirect('/profile');
		}

		$passwordHash = (string)$user['password_hash'];

		if (!password_verify($currentPassword, $passwordHash))
		{
			$this->Flash('error', __('profile.flash.password.current_invalid'));
			$this->Redirect('/profile');
		}

		if ($newPassword !== $confirmPassword)
		{
			$this->Flash('error', __('profile.flash.password.confirm_mismatch'));
			$this->Redirect('/profile');
		}

		if (strlen($newPassword) < 8)
		{
			$this->Flash('error', __('profile.flash.password.too_short'));
			$this->Redirect('/profile');
		}

		$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
		$this->_userRepository->UpdatePasswordHash($userId, $newPasswordHash);

		$this->Flash('success', __('profile.flash.password.success'));
		$this->Redirect('/profile');
	}
}