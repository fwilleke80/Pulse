<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Core\Logger;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Request;
use Pulse\Repositories\UserRepository;
use Pulse\Repositories\MailQueueRepository;
use Pulse\Services\AuthService;
use Pulse\Services\TestNotificationService;

/**
 * @brief Controller for the user profile page.
 */
class ProfileController extends BaseController
{
	private UserRepository $_userRepository;
	private int $_passwordMinimumLength;
	private MailQueueRepository $_mailQueueRepository;
	private TestNotificationService $_testNotificationService;
	private bool $_mailEnabled;
	private NotificationLanguage $_notificationLanguage;

	/**
	 * @brief Constructs the profile controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param Logger $logger Application logger.
	 * @param Request $request Current request.
	 * @param UserRepository $userRepository User repository.
	 * @param int $passwordMinimumLength Minimum accepted password length.
	 * @param MailQueueRepository $mailQueueRepository Mail queue status repository.
	 * @param TestNotificationService $testNotificationService SMTP test service.
	 * @param bool $mailEnabled Whether automatic mail delivery is enabled.
	 * @param NotificationLanguage $notificationLanguage Recipient-language resolver.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		UserRepository $userRepository,
		int $passwordMinimumLength,
		MailQueueRepository $mailQueueRepository,
		TestNotificationService $testNotificationService,
		bool $mailEnabled,
		NotificationLanguage $notificationLanguage
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_userRepository = $userRepository;
		$this->_passwordMinimumLength = $passwordMinimumLength;
		$this->_mailQueueRepository = $mailQueueRepository;
		$this->_testNotificationService = $testNotificationService;
		$this->_mailEnabled = $mailEnabled;
		$this->_notificationLanguage = $notificationLanguage;
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
			'mailEnabled' => $this->_mailEnabled,
			'mailQueueCounts' => $this->_mailQueueRepository->CountByStatusForUser((int)$user['id']),
			'latestTestNotification' => $this->_mailQueueRepository->FindLatestTestForUser((int)$user['id']),
			'notificationLocales' => $this->_notificationLanguage->SupportedLocales(),
			'notificationLocale' => $this->_notificationLanguage->Resolve(
				isset($user['notification_locale']) ? (string)$user['notification_locale'] : null
			),
		]);
	}

	/**
	 * @brief Queues and immediately attempts a test notification to the current profile address.
	 */
	public function SendTestNotification(): void
	{
		$user = $this->RequireUser();
		$status = $this->_testNotificationService->SendForUser($user);
		$key = match ($status)
		{
			'sent' => 'profile.notifications.test.sent',
			'retrying' => 'profile.notifications.test.retrying',
			'failed' => 'profile.notifications.test.failed',
			'disabled' => 'profile.notifications.test.disabled',
			default => 'profile.notifications.test.queued',
		};
		$type = $status === 'sent' ? 'success' : ($status === 'disabled' || $status === 'failed' ? 'error' : 'warning');
		$this->Flash($type, __($key));
		$this->Redirect('/profile#notifications');
	}

	/** @brief Requeues this owner's permanently failed notifications for another cron attempt. */
	public function RetryFailedNotifications(): void
	{
		$user = $this->RequireUser();
		$count = $this->_mailQueueRepository->RetryFailedForUser((int)$user['id']);
		$this->Flash(
			$count > 0 ? 'success' : 'warning',
			__($count > 0 ? 'profile.notifications.retry.success' : 'profile.notifications.retry.none', ['count' => $count])
		);
		$this->Redirect('/profile#notifications');
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
		$notificationLocale = $this->_request->PostString('notification_locale', 10);

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

		if (!$this->_notificationLanguage->IsSupported($notificationLocale))
		{
			$this->_logger->Warning('Profile update failed due to unsupported notification language', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.update.invalid_language'));
			$this->Redirect('/profile');
		}

		$existingUser = $this->_userRepository->FindByEmailExcludingUserId($userId, $email);

		if ($existingUser !== null)
		{
			$this->_logger->Warning('Profile update failed due to email already taken', ['user_id' => $userId]);
			$this->Flash('error', __('profile.flash.update.email_taken'));
			$this->Redirect('/profile');
		}

		$this->_userRepository->UpdateProfile($userId, $displayName, $email, $notificationLocale);

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
