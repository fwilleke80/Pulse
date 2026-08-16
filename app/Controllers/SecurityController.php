<?php

/**
 * @file SecurityController.php
 * @brief Account security-method controller.
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
use Pulse\Repositories\SecurityCredentialRepository;
use Pulse\Services\AuthService;
use Pulse\Services\PasskeyService;
use Pulse\Services\QuickCheckInService;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\SecurityAttemptThrottleService;
use Pulse\Services\TotpService;
use Throwable;

/**
 * @brief Manages passkeys and optional TOTP through Pulse's extensible account-security layer.
 */
final class SecurityController extends BaseController
{
	private SecurityCredentialRepository $_credentials;
	private PasskeyService $_passkeys;
	private CsrfTokenManager $_csrf;
	private QuickCheckInService $_quickCheckIn;
	private MonitorExecutionService $_monitorExecution;
	private TotpService $_totp;
	private SecurityAttemptThrottleService $_securityThrottle;

	/** @brief Constructs the security controller. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		SecurityCredentialRepository $credentials,
		PasskeyService $passkeys,
		CsrfTokenManager $csrf,
		QuickCheckInService $quickCheckIn,
		MonitorExecutionService $monitorExecution,
		TotpService $totp,
		SecurityAttemptThrottleService $securityThrottle
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_credentials = $credentials;
		$this->_passkeys = $passkeys;
		$this->_csrf = $csrf;
		$this->_quickCheckIn = $quickCheckIn;
		$this->_monitorExecution = $monitorExecution;
		$this->_totp = $totp;
		$this->_securityThrottle = $securityThrottle;
	}

	/** @brief Starts an authenticated passkey-registration ceremony. */
	public function RegisterOptions(): string
	{
		$user = $this->RequireUser();
		$currentPassword = $this->_request->PostString('current_password', 4096, false);
		$label = $this->_request->PostString('label', 255);

		if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password_hash']))
		{
			return $this->JsonError(__('security.passkeys.current_password_invalid'), 403);
		}

		try
		{
			return $this->Json(['publicKey' => $this->_passkeys->BeginRegistration($user, $label)]);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Warning('Passkey registration could not be started', ['user_id' => (int)$user['id'], 'error' => $throwable->getMessage()]);
			return $this->JsonError(__('security.passkeys.registration_failed'), 400);
		}
	}

	/** @brief Verifies and stores a newly created passkey. */
	public function RegisterVerify(): string
	{
		$user = $this->RequireUser();

		try
		{
			$result = $this->_passkeys->CompleteRegistration((int)$user['id'], $this->PasskeyResponse());
			$this->_logger->Info('Passkey registered', ['user_id' => (int)$user['id'], 'credential_id' => (int)$result['id']]);
			$this->Flash('success', __('security.passkeys.registered'));
			return $this->Json([
				'ok' => true,
				'message' => __('security.passkeys.registered'),
				'reload' => true,
			]);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Warning('Passkey registration failed', ['user_id' => (int)$user['id'], 'error' => $throwable->getMessage()]);
			return $this->JsonError(__('security.passkeys.registration_failed'), 400);
		}
	}

	/** @brief Deletes one passkey after password re-authentication. */
	public function DeletePasskey(): void
	{
		$user = $this->RequireUser();
		$currentPassword = $this->_request->PostString('current_password', 4096, false);
		$credentialId = $this->_request->PostInt('credential_id');

		if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password_hash']))
		{
			$this->Flash('error', __('security.passkeys.current_password_invalid'));
			$this->Redirect('/profile?tab=security');
		}

		if ($credentialId > 0 && $this->_credentials->DeletePasskeyForUser($credentialId, (int)$user['id']))
		{
			$this->_logger->Info('Passkey removed', ['user_id' => (int)$user['id'], 'credential_id' => $credentialId]);
			$this->Flash('success', __('security.passkeys.removed'));
		}
		else
		{
			$this->Flash('warning', __('security.passkeys.not_found'));
		}

		$this->Redirect('/profile?tab=security');
	}

	/** @brief Starts optional TOTP enrollment after password re-authentication. */
	public function BeginTotpEnrollment(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$scope = 'totp_management';
		$clientIp = $this->_request->ClientIp();

		if ($this->_securityThrottle->IsBlocked($scope, $userId, $clientIp))
		{
			$this->Flash('error', __('security.totp.throttled'));
			$this->Redirect('/profile?tab=security');
		}

		$currentPassword = $this->_request->PostString('current_password', 4096, false);

		if ($currentPassword === '' || !password_verify($currentPassword, (string)$user['password_hash']))
		{
			$this->_securityThrottle->RecordFailure($scope, $userId, $clientIp);
			$this->Flash('error', __('security.totp.current_password_invalid'));
			$this->Redirect('/profile?tab=security');
		}

		try
		{
			$this->_totp->BeginEnrollment($user);
			$this->_securityThrottle->Clear($scope, $userId, $clientIp);
			$this->Flash('success', __('security.totp.setup_started'));
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Error('TOTP enrollment could not be started', ['user_id' => $userId, 'error' => $throwable->getMessage()]);
			$this->Flash('error', __('security.totp.setup_failed'));
		}

		$this->Redirect('/profile?tab=security');
	}

	/** @brief Confirms the first authenticator code before enabling TOTP. */
	public function ConfirmTotpEnrollment(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];
		$scope = 'totp_enrollment';
		$clientIp = $this->_request->ClientIp();

		if ($this->_securityThrottle->IsBlocked($scope, $userId, $clientIp))
		{
			$this->Flash('error', __('security.totp.throttled'));
			$this->Redirect('/profile?tab=security');
		}

		$code = $this->_request->PostString('code', 32);
		$recoveryCodes = null;

		try
		{
			$recoveryCodes = $code !== '' ? $this->_totp->ConfirmEnrollment($userId, $code) : null;
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Error('TOTP enrollment confirmation failed internally', ['user_id' => $userId, 'error' => $throwable->getMessage()]);
		}

		if (!is_array($recoveryCodes))
		{
			$this->_securityThrottle->RecordFailure($scope, $userId, $clientIp);
			$this->Flash('error', __('security.totp.confirm_failed'));
			$this->Redirect('/profile?tab=security');
		}

		$this->_securityThrottle->Clear($scope, $userId, $clientIp);
		$this->_logger->Info('TOTP two-factor authentication enabled', ['user_id' => $userId]);
		$this->Flash('success', __('security.totp.enabled'));
		$this->Redirect('/profile?tab=security');
	}

	/** @brief Cancels an unfinished TOTP enrollment. */
	public function CancelTotpEnrollment(): void
	{
		$user = $this->RequireUser();
		$this->_totp->CancelEnrollment((int)$user['id']);
		$this->Flash('warning', __('security.totp.setup_cancelled'));
		$this->Redirect('/profile?tab=security');
	}

	/** @brief Removes plaintext recovery codes from the authenticated session after acknowledgement. */
	public function AcknowledgeTotpRecoveryCodes(): void
	{
		$user = $this->RequireUser();
		$this->_totp->AcknowledgeRecoveryCodes((int)$user['id']);
		$this->Redirect('/profile?tab=security');
	}

	/** @brief Replaces recovery codes after password and second-factor re-authentication. */
	public function RegenerateTotpRecoveryCodes(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];

		if (!$this->VerifyTotpManagement($user, 'totp_recovery_regeneration'))
		{
			$this->Redirect('/profile?tab=security');
		}

		try
		{
			$this->_totp->RegenerateRecoveryCodes($userId);
			$this->_logger->Info('TOTP recovery codes regenerated', ['user_id' => $userId]);
			$this->Flash('success', __('security.totp.recovery.regenerated'));
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Error('TOTP recovery codes could not be regenerated', ['user_id' => $userId, 'error' => $throwable->getMessage()]);
			$this->Flash('error', __('security.totp.management_failed'));
		}

		$this->Redirect('/profile?tab=security');
	}

	/** @brief Disables TOTP after password and second-factor re-authentication. */
	public function DisableTotp(): void
	{
		$user = $this->RequireUser();
		$userId = (int)$user['id'];

		if (!$this->VerifyTotpManagement($user, 'totp_disable'))
		{
			$this->Redirect('/profile?tab=security');
		}

		try
		{
			if ($this->_totp->Disable($userId))
			{
				$this->_logger->Info('TOTP two-factor authentication disabled', ['user_id' => $userId]);
				$this->Flash('success', __('security.totp.disabled'));
			}
			else
			{
				$this->Flash('warning', __('security.totp.not_enabled'));
			}
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Error('TOTP could not be disabled', ['user_id' => $userId, 'error' => $throwable->getMessage()]);
			$this->Flash('error', __('security.totp.management_failed'));
		}

		$this->Redirect('/profile?tab=security');
	}

	/** @brief Starts passkey login, constrained to the pending quick-check-in account when applicable. */
	public function LoginOptions(): string
	{
		$quickCheckInPending = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '') !== '';

		if ($this->_auth->IsAuthenticated() && !$quickCheckInPending)
		{
			return $this->JsonError(__('security.passkeys.already_authenticated'), 409);
		}

		try
		{
			$targetUserId = null;

			if ($quickCheckInPending)
			{
				$tokenHash = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '');
				$context = $this->_quickCheckIn->ResolveHash($tokenHash);

				if (!is_array($context))
				{
					return $this->JsonError(__('quick_checkin.invalid'), 410);
				}

				$targetUserId = (int)$context['user_id'];
			}
			else
			{
				$pendingTotp = $this->_totp->PendingLogin();
				$targetUserId = is_array($pendingTotp) ? (int)$pendingTotp['user_id'] : null;
			}

			return $this->Json(['publicKey' => $this->_passkeys->BeginAuthentication('login', $targetUserId)]);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Warning('Passkey login could not be started', ['error' => $throwable->getMessage()]);
			return $this->JsonError(__('security.passkeys.login_failed'), 400);
		}
	}

	/** @brief Verifies a passkey assertion and establishes the normal Pulse session. */
	public function LoginVerify(): string
	{
		$quickTokenHash = (string)$this->_session->Get('pulse_quick_checkin_token_hash', '');
		$alreadyAuthenticated = $this->_auth->IsAuthenticated();

		if ($alreadyAuthenticated && $quickTokenHash === '')
		{
			return $this->Json(['ok' => true, 'redirect' => '/']);
		}

		try
		{
			$userId = $this->_passkeys->CompleteAuthentication('login', $this->PasskeyResponse());
			$currentUser = $this->_auth->GetCurrentUser();
			$currentUserId = is_array($currentUser) ? (int)$currentUser['id'] : 0;

			if (!$alreadyAuthenticated || $currentUserId !== $userId)
			{
				if (!$this->_auth->LoginUser($userId, 'passkey'))
				{
					throw new \RuntimeException('Authenticated passkey account is unavailable.');
				}

				$currentUser = $this->_auth->GetCurrentUser();
			}

			if (is_array($currentUser) && !empty($currentUser['website_locale']))
			{
				WebsiteLanguagePreference::Write((string)$currentUser['website_locale'], $this->_request->IsSecure());
			}

			$this->_csrf->Rotate();
			$this->_totp->CancelPendingLogin();

			if ($quickTokenHash !== '')
			{
				$context = $this->_quickCheckIn->ResolveHash($quickTokenHash);

				if (is_array($context) && (int)$context['user_id'] === $userId && $this->_quickCheckIn->Claim($quickTokenHash, $userId))
				{
					$result = $this->_monitorExecution->CheckInAllActiveForUser(
						$userId,
						'quick_passkey_login',
						CheckInLocation::FromRequest($this->_request)
					);
					$this->_session->Remove('pulse_quick_checkin_token_hash');
					$this->_session->Set('pulse_quick_checkin_result', ['count' => (int)$result['updated']]);
					return $this->Json(['ok' => true, 'redirect' => '/quick-check-in/success']);
				}

				$this->_session->Remove('pulse_quick_checkin_token_hash');
			}

			return $this->Json(['ok' => true, 'redirect' => '/']);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Warning('Passkey login failed', ['error' => $throwable->getMessage()]);
			return $this->JsonError(__('security.passkeys.login_failed'), 401);
		}
	}

	/** @param array<string, mixed> $user @brief Verifies password plus one current TOTP or recovery code for a sensitive action. */
	private function VerifyTotpManagement(array $user, string $scope): bool
	{
		$userId = (int)$user['id'];
		$clientIp = $this->_request->ClientIp();

		if ($this->_securityThrottle->IsBlocked($scope, $userId, $clientIp))
		{
			$this->Flash('error', __('security.totp.throttled'));
			return false;
		}

		$currentPassword = $this->_request->PostString('current_password', 4096, false);
		$code = $this->_request->PostString('code', 64);
		$verified = false;

		try
		{
			$verified = $currentPassword !== ''
				&& $code !== ''
				&& password_verify($currentPassword, (string)$user['password_hash'])
				&& $this->_totp->VerifyManagementCode($userId, $code);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Error('TOTP management re-authentication failed internally', ['user_id' => $userId, 'error' => $throwable->getMessage()]);
		}

		if (!$verified)
		{
			$this->_securityThrottle->RecordFailure($scope, $userId, $clientIp);
			$this->Flash('error', __('security.totp.reauthentication_failed'));
			return false;
		}

		$this->_securityThrottle->Clear($scope, $userId, $clientIp);
		return true;
	}

	/** @return array<string, string> @brief Reads a WebAuthn response from form fields. */
	private function PasskeyResponse(): array
	{
		return [
			'credential_id' => $this->_request->PostString('credential_id', 8192),
			'client_data_json' => $this->_request->PostString('client_data_json', 32768),
			'attestation_object' => $this->_request->PostString('attestation_object', 131072),
			'authenticator_data' => $this->_request->PostString('authenticator_data', 32768),
			'signature' => $this->_request->PostString('signature', 32768),
			'user_handle' => $this->_request->PostString('user_handle', 8192),
			'transports' => $this->_request->PostString('transports', 255),
		];
	}

	/** @brief Returns a JSON success payload. */
	private function Json(array $payload, int $status = 200): string
	{
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		return (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/** @brief Returns a JSON error payload. */
	private function JsonError(string $message, int $status): string
	{
		return $this->Json(['ok' => false, 'message' => $message], $status);
	}
}
