<?php

/**
 * @file SecurityController.php
 * @brief Account security-method controller.
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
use Pulse\Repositories\SecurityCredentialRepository;
use Pulse\Services\AuthService;
use Pulse\Services\PasskeyService;
use Pulse\Services\QuickCheckInService;
use Pulse\Services\MonitorExecutionService;
use Throwable;

/**
 * @brief Manages passkeys through Pulse's extensible account-security layer.
 */
final class SecurityController extends BaseController
{
	private SecurityCredentialRepository $_credentials;
	private PasskeyService $_passkeys;
	private CsrfTokenManager $_csrf;
	private QuickCheckInService $_quickCheckIn;
	private MonitorExecutionService $_monitorExecution;

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
		MonitorExecutionService $monitorExecution
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_credentials = $credentials;
		$this->_passkeys = $passkeys;
		$this->_csrf = $csrf;
		$this->_quickCheckIn = $quickCheckIn;
		$this->_monitorExecution = $monitorExecution;
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
			$this->Redirect('/profile#security');
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

		$this->Redirect('/profile#security');
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

			if ($quickTokenHash !== '')
			{
				$context = $this->_quickCheckIn->ResolveHash($quickTokenHash);

				if (is_array($context) && (int)$context['user_id'] === $userId && $this->_quickCheckIn->Claim($quickTokenHash, $userId))
				{
					$result = $this->_monitorExecution->CheckInAllActiveForUser($userId, 'quick_passkey_login');
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
