<?php

/**
 * @file AuthController.php
 * @brief Authentication request controller.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\CheckInLocation;
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
use Pulse\Services\SecurityAttemptThrottleService;
use Pulse\Services\TotpService;
use Throwable;

/**
 * @brief Handles login and logout without exposing account existence.
 */
class AuthController extends BaseController
{
	private LoginThrottleService $_loginThrottle;
	private CsrfTokenManager $_csrf;
	private QuickCheckInService $_quickCheckIn;
	private MonitorExecutionService $_monitorExecution;
	private TotpService $_totp;
	private SecurityAttemptThrottleService $_securityThrottle;

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
	 * @param TotpService $totp Optional TOTP second-factor service.
	 * @param SecurityAttemptThrottleService $securityThrottle TOTP attempt throttle.
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
		MonitorExecutionService $monitorExecution,
		TotpService $totp,
		SecurityAttemptThrottleService $securityThrottle
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_loginThrottle = $loginThrottle;
		$this->_csrf = $csrf;
		$this->_quickCheckIn = $quickCheckIn;
		$this->_monitorExecution = $monitorExecution;
		$this->_totp = $totp;
		$this->_securityThrottle = $securityThrottle;
	}

	/** @brief Displays the login form. @return string */
	public function ShowLogin(): string
	{
		$quickCheckInPending = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '') !== '';

		if ($this->_totp->PendingLogin() !== null)
		{
			$this->Redirect('/login/totp');
		}

		if ($this->_auth->IsAuthenticated() && !$quickCheckInPending)
		{
			$this->Redirect('/');
		}

		return $this->_view->Render('auth.login', [
			'quickCheckInPending' => $quickCheckInPending,
			'locationRequested' => $quickCheckInPending && $this->QuickCheckInLocationRequested(),
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

		$user = $this->_auth->VerifyPassword($email, $password);

		if (!is_array($user))
		{
			$this->_loginThrottle->RecordFailure($email, $clientIp);
			$this->Flash('error', __('flash.login.failed'));
			$this->Redirect('/login');
		}

		$userId = (int)$user['id'];

		if ($this->_totp->IsEnabled($userId))
		{
			$this->_session->Regenerate();
			$this->ApplyWebsiteLanguage($user);
			$this->_totp->BeginLogin($userId, $email, CheckInLocation::FromRequest($this->_request));
			$this->_csrf->Rotate();
			$this->Redirect('/login/totp');
		}

		if (!$this->_auth->LoginUser($userId, 'password'))
		{
			$this->_loginThrottle->RecordFailure($email, $clientIp);
			$this->Flash('error', __('flash.login.failed'));
			$this->Redirect('/login');
		}

		$this->_loginThrottle->Clear($email, $clientIp);
		$currentUser = $this->_auth->GetCurrentUser();

		if (!is_array($currentUser))
		{
			$this->Flash('error', __('flash.login.failed'));
			$this->Redirect('/login');
		}

		$this->FinishLogin($currentUser, 'quick_password', CheckInLocation::FromRequest($this->_request));
	}

	/** @brief Displays the second-factor challenge after password verification. @return string */
	public function ShowTotp(): string
	{
		if ($this->_auth->IsAuthenticated())
		{
			$this->Redirect('/');
		}

		$pending = $this->_totp->PendingLogin();

		if (!is_array($pending))
		{
			$this->Flash('warning', __('login.totp.expired'));
			$this->Redirect('/login');
		}

		$quickCheckInPending = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '') !== '';
		return $this->_view->Render('auth.totp', [
			'quickCheckInPending' => $quickCheckInPending,
			'locationRequested' => $quickCheckInPending && $this->QuickCheckInLocationRequested(),
		]);
	}

	/** @brief Verifies a throttled TOTP or recovery-code challenge and completes password login. */
	public function VerifyTotp(): void
	{
		$pending = $this->_totp->PendingLogin();

		if (!is_array($pending))
		{
			$this->Flash('warning', __('login.totp.expired'));
			$this->Redirect('/login');
		}

		$userId = (int)$pending['user_id'];
		$email = (string)$pending['email'];
		$clientIp = $this->_request->ClientIp();
		$scope = 'totp_login';

		if ($this->_securityThrottle->IsBlocked($scope, $userId, $clientIp))
		{
			$this->_logger->Warning('Blocked throttled TOTP login attempt', ['user_id' => $userId]);
			$this->Flash('error', __('login.totp.throttled'));
			$this->Redirect('/login/totp');
		}

		$code = $this->_request->PostString('code', 64);
		$method = null;

		try
		{
			$method = $code !== '' ? $this->_totp->VerifyAuthentication($userId, $code) : null;
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Error('TOTP login verification failed internally', ['user_id' => $userId, 'error' => $throwable->getMessage()]);
		}

		if (!is_string($method))
		{
			$this->_securityThrottle->RecordFailure($scope, $userId, $clientIp);
			$this->Flash(
				'error',
				$this->_securityThrottle->IsBlocked($scope, $userId, $clientIp)
					? __('login.totp.throttled')
					: __('login.totp.failed')
			);
			$this->Redirect('/login/totp');
		}

		if (!$this->_auth->LoginUser($userId, $method))
		{
			$this->_totp->CancelPendingLogin();
			$this->Flash('error', __('flash.login.failed'));
			$this->Redirect('/login');
		}

		$this->_securityThrottle->Clear($scope, $userId, $clientIp);
		$this->_loginThrottle->Clear($email, $clientIp);
		$this->_totp->CancelPendingLogin();
		$currentUser = $this->_auth->GetCurrentUser();

		if (!is_array($currentUser))
		{
			$this->Flash('error', __('flash.login.failed'));
			$this->Redirect('/login');
		}

		$source = $method === 'password_recovery' ? 'quick_password_recovery' : 'quick_password_totp';
		$this->FinishLogin($currentUser, $source, $pending['location']);
	}

	/** @brief Cancels a pending second-factor login without consuming the quick-check-in token. */
	public function CancelTotp(): void
	{
		$this->_totp->CancelPendingLogin();
		$this->Redirect('/login');
	}

	/** @brief Logs the current user out through a CSRF-protected POST request. */
	public function Logout(): void
	{
		$this->_auth->Logout();
		$this->Redirect('/login');
	}

	/**
	 * @brief Applies final session preferences and completes a pending quick check-in when present.
	 * @param array<string, mixed> $currentUser Authenticated user row.
	 * @param string $quickSource Audit source for a quick check-in.
	 * @param array<string, mixed>|null $location Previously validated optional location.
	 */
	private function FinishLogin(array $currentUser, string $quickSource, ?array $location): void
	{
		$this->ApplyWebsiteLanguage($currentUser);
		$this->_csrf->Rotate();
		$quickTokenHash = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '');

		if ($quickTokenHash !== '')
		{
			$userId = (int)$currentUser['id'];
			$context = $this->_quickCheckIn->ResolveHash($quickTokenHash);

			if (is_array($context) && (int)$context['user_id'] === $userId && $this->_quickCheckIn->Claim($quickTokenHash, $userId))
			{
				$result = $this->_monitorExecution->CheckInAllActiveForUser($userId, $quickSource, $location);
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

	/** @param array<string, mixed> $user @brief Applies the verified account's persistent website language. */
	private function ApplyWebsiteLanguage(array $user): void
	{
		if (!empty($user['website_locale']))
		{
			$this->_session->Set('locale', (string)$user['website_locale']);
			WebsiteLanguagePreference::Write((string)$user['website_locale'], $this->_request->IsSecure());
		}
	}

	/** @brief Resolves whether the session-bound quick check-in account requests location. */
	private function QuickCheckInLocationRequested(): bool
	{
		$tokenHash = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '');
		$context = $tokenHash !== '' ? $this->_quickCheckIn->ResolveHash($tokenHash) : null;

		return is_array($context)
			&& $this->_monitorExecution->HasLocationEnabledActiveMonitorForUser((int)$context['user_id']);
	}
}
