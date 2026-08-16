<?php

/**
 * @file AuthController.php
 * @brief Authentication request controller.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\CsrfTokenManager;
use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Core\WebsiteLanguagePreference;
use Pulse\Services\AuthService;
use Pulse\Services\LoginThrottleService;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\QuickCheckInService;

/**
 * @brief Handles login and logout without exposing account existence.
 */
class AuthController extends BaseController
{
	private LoginThrottleService $_loginThrottle;
	private CsrfTokenManager $_csrf;
	private QuickCheckInService $_quickCheckIn;
	private MonitorExecutionService $_monitorExecution;

	/**
	 * @brief Constructs the authentication controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 * @param Logger $logger Application logger.
	 * @param Request $request Current request.
	 * @param LoginThrottleService $loginThrottle Login throttle.
	 * @param CsrfTokenManager $csrf CSRF token manager.
	 * @param QuickCheckInService $quickCheckIn Quick-check-in token service.
	 * @param MonitorExecutionService $monitorExecution Global monitor check-in service.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		LoginThrottleService $loginThrottle,
		CsrfTokenManager $csrf,
		QuickCheckInService $quickCheckIn,
		MonitorExecutionService $monitorExecution
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_loginThrottle = $loginThrottle;
		$this->_csrf = $csrf;
		$this->_quickCheckIn = $quickCheckIn;
		$this->_monitorExecution = $monitorExecution;
	}

	/** @brief Displays the login form. @return string */
	public function ShowLogin(): string
	{
		$quickCheckInPending = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '') !== '';

		if ($this->_auth->IsAuthenticated() && !$quickCheckInPending)
		{
			$this->Redirect('/');
		}

		return $this->_view->Render('auth.login', [
			'quickCheckInPending' => $quickCheckInPending,
		]);
	}

	/** @brief Processes a throttled login attempt. */
	public function Login(): void
	{
		$email = $this->_request->PostString('email', 255);
		$password = $this->_request->PostString('password', 4096, false);
		$clientIp = $this->_request->ClientIp();

		if ($email === '' || $password === '')
		{
			$this->Flash('error', __('flash.login.required'));
			$this->Redirect('/login');
		}

		if ($this->_loginThrottle->IsBlocked($email, $clientIp))
		{
			$this->_logger->Warning('Blocked throttled login attempt');
			$this->Flash('error', __('flash.login.throttled'));
			$this->Redirect('/login');
		}

		if (!$this->_auth->Login($email, $password))
		{
			$this->_loginThrottle->RecordFailure($email, $clientIp);
			$this->Flash('error', __('flash.login.failed'));
			$this->Redirect('/login');
		}

		$this->_loginThrottle->Clear($email, $clientIp);
		$currentUser = $this->_auth->GetCurrentUser();

		if (is_array($currentUser) && !empty($currentUser['website_locale']))
		{
			WebsiteLanguagePreference::Write((string)$currentUser['website_locale'], $this->_request->IsSecure());
		}

		$this->_csrf->Rotate();

		$quickTokenHash = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '');

		if ($quickTokenHash !== '' && is_array($currentUser))
		{
			$userId = (int)$currentUser['id'];
			$context = $this->_quickCheckIn->ResolveHash($quickTokenHash);

			if (is_array($context) && (int)$context['user_id'] === $userId && $this->_quickCheckIn->Claim($quickTokenHash, $userId))
			{
				$result = $this->_monitorExecution->CheckInAllActiveForUser($userId, 'quick_password');
				$this->_session->Remove('pulse_quick_checkin_token_hash');
				$this->_session->Set('pulse_quick_checkin_result', ['count' => (int)$result['updated']]);
				$this->Redirect('/quick-check-in/success');
			}

			$this->_session->Remove('pulse_quick_checkin_token_hash');
			$this->Flash('warning', __('quick_checkin.invalid'));
			$this->Redirect('/');
		}

		$this->Flash('success', __('flash.login.successful'));
		$this->Redirect('/');
	}

	/** @brief Logs the current user out through a CSRF-protected POST request. */
	public function Logout(): void
	{
		$this->_auth->Logout();
		$this->Redirect('/login');
	}
}
