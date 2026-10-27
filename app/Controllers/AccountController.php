<?php

/** @file AccountController.php @brief Public account confirmation and administrator account management. */
declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\NotFoundException;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Repositories\AccountRepository;
use Pulse\Services\AccountService;
use Pulse\Services\AuthService;
use Pulse\Services\LoginThrottleService;
use Throwable;

class AccountController extends BaseController
{
	/** @brief Constructs this account component. */
	public function __construct(View $view, Session $session, AuthService $auth, Logger $logger, Request $request,
		private AccountService $_accounts, private AccountRepository $_repository,
		private LoginThrottleService $_throttle, private int $_passwordMinimumLength)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
	}

	/** @brief Index. */
	public function Index(): string
	{
		$actor = $this->RequireAdministrator();
		return $this->_view->Render('administration.users.index', [
			'accounts' => $this->_accounts->AdminList((int)$actor['id']),
			'registrationSettings' => $this->_accounts->RegistrationSettings(),
			'accountAudit' => $this->_repository->RecentAudit(),
		]);
	}

	/** @brief Edit. */
	public function Edit(): string
	{
		$this->RequireAdministrator();
		$id = $this->_request->QueryInt('id');
		$account = $id > 0 ? $this->_repository->FindById($id) : null;
		if ($id > 0 && $account === null)
		{
			throw new NotFoundException();
		}
		if (is_array($account) && !empty($account['is_super_user']))
		{
			$this->Flash('warning', __('accounts.protected_hint'));
			$this->Redirect('/administration/users');
		}
		if (is_array($account))
		{
			unset($account['password_hash']);
		}
		return $this->_view->Render('administration.users.edit', [
			'account' => $account, 'passwordMinimumLength' => $this->_passwordMinimumLength,
		]);
	}

	/** @brief Create. */
	public function Create(): void
	{
		$this->AdminMutation(fn (int $actorId) => $this->_accounts->AdminCreate($actorId, $this->AccountInput()), 'accounts.saved');
	}

	/** @brief Update. */
	public function Update(): void
	{
		$this->AdminMutation(fn (int $actorId) => $this->_accounts->AdminUpdate($actorId, $this->_request->PostInt('id'), $this->AccountInput()), 'accounts.saved');
	}

	/** @brief Delete. */
	public function Delete(): void
	{
		$this->AdminMutation(function (int $actorId): void {
			if (!$this->_request->PostBool('confirm_delete'))
			{
				throw new \InvalidArgumentException('Deletion confirmation required.');
			}
			$this->_accounts->AdminDelete($actorId, $this->_request->PostInt('id'));
		}, 'accounts.deleted');
	}

	/** @brief Invite. */
	public function Invite(): void
	{
		$this->AdminMutation(fn (int $actorId) => $this->_accounts->Invite($actorId, $this->_request->PostInt('id')), 'accounts.invitation_sent');
	}

	/** @brief Approve. */
	public function Approve(): void
	{
		$this->AdminMutation(fn (int $actorId) => $this->_accounts->AdminUpdate($actorId, $this->_request->PostInt('id'), ['approved' => true]), 'accounts.approved_message');
	}

	/** @brief Resets an authenticator without revealing its secret or recovery codes. */
	public function ResetTotp(): void
	{
		$this->AdminMutation(fn (int $actorId) => $this->_accounts->ResetTotp($actorId,
			$this->_request->PostInt('id'), $this->_request->PostBool('totp_required')), 'accounts.security_reset');
	}

	/** @brief Settings. */
	public function Settings(): void
	{
		$this->AdminMutation(fn (int $actorId) => $this->_accounts->UpdateRegistrationSettings($actorId,
			$this->_request->PostBool('registration_enabled'), $this->_request->PostBool('registration_requires_approval')), 'accounts.settings_saved', '/administration/configuration?tab=security');
	}

	/** @brief Show registration. */
	public function ShowRegistration(): string
	{
		$this->RequireRegistration();
		return $this->_view->Render('account.register', ['passwordMinimumLength' => $this->_passwordMinimumLength]);
	}

	/** @brief Register. */
	public function Register(): void
	{
		$this->RequireRegistration();
		$password = $this->_request->PostString('password', 4097, false);
		if ($password !== $this->_request->PostString('password_confirm', 4097, false))
		{
			$this->Flash('error', __('accounts.password_mismatch'));
			$this->Redirect('/register');
		}

		if (strlen($password) < $this->_passwordMinimumLength || strlen($password) > 72 || str_contains($password, "\0")
			|| trim($this->_request->PostString('display_name', 256)) === ''
			|| !filter_var($this->_request->PostString('email', 256), FILTER_VALIDATE_EMAIL))
		{
			$this->Flash('error', __('accounts.validation'));
			$this->Redirect('/register');
		}
		// Throttle each client network without a global registration lockout.
		$ip = $this->_request->ClientIp();
		$identity = 'pulse:registration:' . $ip;
		if ($this->_throttle->IsBlocked($identity, $ip))
		{
			$this->Flash('error', __('flash.login.throttled'));
			$this->Redirect('/register');
		}
		$this->_throttle->RecordFailure($identity, $ip);
		try
		{
			$this->_accounts->Register([
				'display_name' => $this->_request->PostString('display_name', 256),
				'email' => $this->_request->PostString('email', 256), 'password' => $password,
			]);
		}
		catch (Throwable $error)
		{
			// Do not disclose whether an address already has an account, or log submitted secrets.
			$this->_logger->Warning('Public account registration was not completed', ['exception' => get_class($error)]);
		}
		$this->Flash('success', __('accounts.registered'));
		$this->Redirect('/login');
	}

	/** @brief Show verification. */
	public function ShowVerification(): string
	{
		return $this->Confirmation(false);
	}
	/** @brief Show invitation. */
	public function ShowInvitation(): string
	{
		return $this->Confirmation(true);
	}

	/** @brief Verify. */
	public function Verify(): void
	{
		$this->Consume(false);
	}

	/** @brief Accept invitation. */
	public function AcceptInvitation(): void
	{
		$this->Consume(true);
	}

	/** @brief GET only renders a form. No token is consumed, revoked, or exchanged. */
	private function Confirmation(bool $invitation): string
	{
		$token = $this->_request->QueryString('token', 256);
		return $this->_view->Render('account.confirm', [
			'invitation' => $invitation, 'token' => $token,
			'valid' => preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1,
			'passwordMinimumLength' => $this->_passwordMinimumLength,
		]);
	}

	/** @brief The front controller validates CSRF before reaching any POST action. */
	private function Consume(bool $invitation): void
	{
		$ip = $this->_request->ClientIp();
		$identity = 'pulse:account-link:' . $ip;
		if ($this->_throttle->IsBlocked($identity, $ip))
		{
			$this->Flash('error', __('flash.login.throttled'));
			$this->Redirect('/login');
		}
		$this->_throttle->RecordFailure($identity, $ip);
		$token = $this->_request->PostString('token', 256);
		$password = $this->_request->PostString('password', 4097, false);
		if ($invitation && $password !== $this->_request->PostString('password_confirm', 4097, false))
		{
			$this->Flash('error', __('accounts.password_mismatch'));
			$this->Redirect('/account/set-password?token=' . rawurlencode($token));
		}
		try
		{
			if ($invitation)
			{
				$this->_accounts->SetPassword($token, $password);
			}
			else
			{
				$this->_accounts->Confirm($token);
			}
			$this->Flash('success', __($invitation ? 'accounts.invited_message' : 'accounts.verified_message'));
			$this->Redirect('/login');
		}
		catch (Throwable $error)
		{
			$this->_logger->Warning('Account link submission rejected', ['exception' => get_class($error)]);
			$this->Flash('error', __('accounts.failed'));
			$this->Redirect(($invitation ? '/account/set-password' : '/register/confirm') . '?token=' . rawurlencode($token));
		}
	}

	/** @brief Require registration. */
	private function RequireRegistration(): void
	{
		if (empty($this->_accounts->RegistrationSettings()['registration_enabled']))
		{
			throw new NotFoundException();
		}
	}

	/** @brief Account input. */
	private function AccountInput(): array
	{
		return [
			'display_name' => $this->_request->PostString('display_name', 256),
			'email' => $this->_request->PostString('email', 256),
			'password' => $this->_request->PostString('password', 4097, false),
			'role' => $this->_request->PostString('role', 32),
			'password_change_required' => $this->_request->PostBool('password_change_required'),
			'totp_required' => $this->_request->PostBool('totp_required'),
			'is_active' => $this->_request->PostBool('is_active'),
		];
	}

	/** @brief Admin mutation. */
	private function AdminMutation(callable $operation, string $message, string $redirect = '/administration/users'): void
	{
		$actor = $this->RequireAdministrator();
		try
		{
			$operation((int)$actor['id']);
			$this->Flash('success', __($message));
		}
		catch (Throwable $error)
		{
			$this->_logger->Warning('Account management request rejected', ['actor_id' => (int)$actor['id'], 'exception' => get_class($error)]);
			$this->Flash('error', __('accounts.failed'));
		}
		$this->Redirect($redirect);
	}
}
