<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;

/**
 * @brief Controller for the user profile page.
 */
class ProfileController extends BaseController
{
	private UserRepository $_userRepository;
	private int $_passwordMinimumLength;

	/**
	 * @brief Constructs the profile controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param Logger $logger Application logger.
	 * @param Request $request Current request.
	 * @param UserRepository $userRepository User repository.
	 * @param int $passwordMinimumLength Minimum accepted password length.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		UserRepository $userRepository,
		int $passwordMinimumLength
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_userRepository = $userRepository;
		$this->_passwordMinimumLength = $passwordMinimumLength;
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

		$displayName = $this->_request->PostString('display_name', 255);
		$email = $this->_request->PostString('email', 255);

		if ($displayName === '' || $email === '')
		{
			$this->_logger->Warning('Profile update failed due to missing fields', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.update.required'));
			$this->Redirect('/profile');
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		{
			$this->_logger->Warning('Profile update failed due to invalid email format', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.update.invalid_email'));
			$this->Redirect('/profile');
		}

		$existingUser = $this->_userRepository->FindByEmailExcludingUserId($userId, $email);

		if ($existingUser !== null)
		{
			$this->_logger->Warning('Profile update failed due to email already taken', ['user_id' => $userId]);
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

		$currentPassword = $this->_request->PostString('current_password', 4096, false);
		$newPassword = $this->_request->PostString('new_password', 4096, false);
		$confirmPassword = $this->_request->PostString('confirm_password', 4096, false);

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

		if (strlen($newPassword) < $this->_passwordMinimumLength)
		{
			$this->_logger->Warning('Password change failed due to new password being too short', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.password.too_short', ['minimum' => $this->_passwordMinimumLength]));
			$this->Redirect('/profile');
		}

		$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
		$this->_userRepository->UpdatePasswordHash($userId, $newPasswordHash);

		$this->_logger->Info('Password changed successfully', ['user_id' => $userId]);
		$this->Flash('success', __('profile.flash.password.success'));
		$this->Redirect('/profile');
	}
}
