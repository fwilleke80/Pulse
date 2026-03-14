<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Core\Logger;
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
		Logger $logger,
		UserRepository $userRepository
	)
	{
		parent::__construct($view, $session, $auth, $logger);
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
			$this->_logger->Warning('Profile update failed due to missing fields', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.update.required'));
			$this->Redirect('/profile');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		{
			$this->_logger->Warning('Profile update failed due to invalid email format', ['user_id' => $userId, 'email' => $email]);
			$this->Flash('error', __('profile.flash.update.invalid_email'));
			$this->Redirect('/profile');
		}

		$existingUser = $this->_userRepository->FindByEmailExcludingUserId($userId, $email);

		if ($existingUser !== null)
		{
			$this->_logger->Warning('Profile update failed due to email already taken', ['user_id' => $userId, 'email' => $email]);
			$this->Flash('error', __('profile.flash.update.email_taken'));
			$this->Redirect('/profile');
		}

		$this->_userRepository->UpdateProfile($userId, $displayName, $email);

		$this->_logger->Info('Profile updated successfully', ['user_id' => $userId]);
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
			$this->_logger->Warning('Password change failed due to missing fields', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.password.required'));
			$this->Redirect('/profile');
		}

		$passwordHash = (string)$user['password_hash'];

		if (!password_verify($currentPassword, $passwordHash))
		{
			$this->_logger->Warning('Password change failed due to incorrect current password', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.password.current_invalid'));
			$this->Redirect('/profile');
		}

		if ($newPassword !== $confirmPassword)
		{
			$this->_logger->Warning('Password change failed due to password confirmation mismatch', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.password.confirm_mismatch'));
			$this->Redirect('/profile');
		}

		if (strlen($newPassword) < 8)
		{
			$this->_logger->Warning('Password change failed due to new password being too short', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.password.too_short'));
			$this->Redirect('/profile');
		}

		$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
		$this->_userRepository->UpdatePasswordHash($userId, $newPasswordHash);

		$this->_logger->Info('Password changed successfully', ['user_id' => $userId]);
		$this->Flash('success', __('profile.flash.password.success'));
		$this->Redirect('/profile');
	}
}