<?php

/**
 * @file QuickCheckInController.php
 * @brief Passkey/password-authenticated global quick check-in controller.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\CheckInLocation;
use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Services\AuthService;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\PasskeyService;
use Pulse\Services\QuickCheckInService;
use Throwable;

/**
 * @brief Resolves reminder links while keeping the link itself non-authenticating.
 */
final class QuickCheckInController extends BaseController
{
	private const SESSION_TOKEN_HASH = 'pulse_quick_checkin_token_hash';
	private const SESSION_RESULT = 'pulse_quick_checkin_result';

	private QuickCheckInService $_quickCheckIn;
	private PasskeyService $_passkeys;
	private MonitorExecutionService $_monitorExecution;

	/** @brief Constructs the controller. */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger,
		Request $request,
		QuickCheckInService $quickCheckIn,
		PasskeyService $passkeys,
		MonitorExecutionService $monitorExecution
	)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_quickCheckIn = $quickCheckIn;
		$this->_passkeys = $passkeys;
		$this->_monitorExecution = $monitorExecution;
	}

	/** @brief Accepts the emailed token once, then removes it from the browser address bar. */
	public function Open(): string
	{
		$rawToken = $this->_request->QueryString('token', 256);

		if ($rawToken !== '')
		{
			$context = $this->_quickCheckIn->ResolveRawToken($rawToken);

			if (!is_array($context))
			{
				return $this->InvalidView();
			}

			$this->_session->Set(self::SESSION_TOKEN_HASH, $this->_quickCheckIn->HashRawToken($rawToken));
			$this->Redirect('/quick-check-in');
		}

		$context = $this->CurrentContext();

		if (!is_array($context))
		{
			return $this->InvalidView();
		}

		return $this->_view->Render('security.quick-checkin', [
			'hasPasskeys' => $this->_passkeys->HasPasskeys((int)$context['user_id']),
			'locationRequested' => $this->_monitorExecution->HasLocationEnabledActiveMonitorForUser((int)$context['user_id']),
		]);
	}

	/** @brief Starts a passkey assertion limited to the account named by the email token. */
	public function PasskeyOptions(): string
	{
		$context = $this->CurrentContext();

		if (!is_array($context) || !$this->_passkeys->HasPasskeys((int)$context['user_id']))
		{
			return $this->JsonError(__('quick_checkin.passkey_unavailable'), 400);
		}

		try
		{
			return $this->Json(['publicKey' => $this->_passkeys->BeginAuthentication('quick-checkin', (int)$context['user_id'])]);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Warning('Quick check-in passkey ceremony could not start', ['error' => $throwable->getMessage()]);
			return $this->JsonError(__('quick_checkin.failed'), 400);
		}
	}

	/** @brief Completes passkey authentication and checks in all active monitors. */
	public function PasskeyVerify(): string
	{
		$context = $this->CurrentContext();
		$tokenHash = (string)$this->_session->Get(self::SESSION_TOKEN_HASH, '');

		if (!is_array($context) || $tokenHash === '')
		{
			return $this->JsonError(__('quick_checkin.invalid'), 400);
		}

		try
		{
			$userId = $this->_passkeys->CompleteAuthentication('quick-checkin', $this->PasskeyResponse());

			if ($userId !== (int)$context['user_id'] || !$this->_quickCheckIn->Claim($tokenHash, $userId))
			{
				throw new \RuntimeException('Quick check-in token is no longer claimable.');
			}

			$result = $this->_monitorExecution->CheckInAllActiveForUser(
				$userId,
				'quick_passkey',
				CheckInLocation::FromRequest($this->_request)
			);
			$this->StoreSuccess((int)$result['updated']);
			return $this->Json(['ok' => true, 'redirect' => '/quick-check-in/success']);
		}
		catch (Throwable $throwable)
		{
			$this->_logger->Warning('Passkey quick check-in failed', ['error' => $throwable->getMessage()]);
			return $this->JsonError(__('quick_checkin.failed'), 401);
		}
	}

	/** @brief Displays the one-time result after a completed quick check-in. */
	public function Success(): string
	{
		$result = $this->_session->Get(self::SESSION_RESULT, null);
		$this->_session->Remove(self::SESSION_RESULT);

		if (!is_array($result))
		{
			$this->Redirect('/');
		}

		return $this->_view->Render('security.quick-checkin-success', [
			'count' => (int)($result['count'] ?? 0),
		]);
	}

	/** @return array<string, mixed>|null @brief Resolves the session-bound quick-check-in token. */
	private function CurrentContext(): ?array
	{
		$tokenHash = (string)$this->_session->Get(self::SESSION_TOKEN_HASH, '');
		return $tokenHash !== '' ? $this->_quickCheckIn->ResolveHash($tokenHash) : null;
	}

	/** @brief Stores a success result and clears the pending token. */
	private function StoreSuccess(int $count): void
	{
		$this->_session->Remove(self::SESSION_TOKEN_HASH);
		$this->_session->Set(self::SESSION_RESULT, ['count' => $count]);
	}

	/** @return array<string, string> @brief Reads a WebAuthn assertion from POST fields. */
	private function PasskeyResponse(): array
	{
		return [
			'credential_id' => $this->_request->PostString('credential_id', 8192),
			'client_data_json' => $this->_request->PostString('client_data_json', 32768),
			'authenticator_data' => $this->_request->PostString('authenticator_data', 32768),
			'signature' => $this->_request->PostString('signature', 32768),
			'user_handle' => $this->_request->PostString('user_handle', 8192),
		];
	}

	/** @brief Returns the generic invalid/expired-link page. */
	private function InvalidView(): string
	{
		http_response_code(410);
		return $this->_view->Render('security.quick-checkin-invalid');
	}

	/** @brief Returns a JSON payload. */
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
