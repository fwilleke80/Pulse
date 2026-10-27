<?php

declare(strict_types=1);

namespace Pulse\Tests\Support;

use Pulse\Controllers\AuthController;
use Pulse\Core\Database;
use Pulse\Core\EnvironmentFile;
use Pulse\Core\Logger;
use Pulse\Core\Session;
use Pulse\Core\TotpAlgorithm;
use Pulse\Repositories\TotpCredentialRepository;
use Pulse\Repositories\UserRepository;
use Pulse\Services\AuthService;
use Pulse\Services\TotpSecretProtector;
use Pulse\Services\TotpService;
use RuntimeException;

// These fakes never call the parent persistence/session/logging implementations.
final class AuthenticationUsers extends UserRepository
{
	public ?array $user;
	public array $passwordWrites = [];
	public array $loginWrites = [];
	public int $reads = 0;
	/** @brief Constructs this account component. */
	public function __construct(?array $user)
	{
		$this->user = $user;
	}
	/** @brief Find by email. */
	public function FindByEmail(string $email): ?array
	{
		return $this->user !== null && $this->user['email'] === $email ? $this->user : null;
	}
	/** @brief Find by id. */
	public function FindById(int $userId): ?array
	{
		++$this->reads; return $this->user !== null && (int)$this->user['id'] === $userId ? $this->user : null;
	}
	/** @brief Update password hash. */
	public function UpdatePasswordHash(int $userId, string $passwordHash): void
	{
		$this->passwordWrites[] = [$userId, $passwordHash];
	}
	/** @brief Update last login at. */
	public function UpdateLastLoginAt(int $userId): void
	{
		$this->loginWrites[] = $userId;
	}
}

final class AuthenticationSession extends Session
{
	public array $values = [];
	public ?int $userId = null;
	public int $regenerations = 0;
	public int $logouts = 0;
	/** @brief Start. */
	public function Start(): void
	{
	}
	/** @brief Regenerate. */
	public function Regenerate(): void
	{
		++$this->regenerations;
	}
	/** @brief Login user. */
	public function LoginUser(int $userId): void
	{
		$this->userId = $userId;
	}
	/** @brief Get user id. */
	public function GetUserId(): ?int
	{
		return $this->userId;
	}
	/** @brief Logout. */
	public function Logout(): void
	{
		++$this->logouts; $this->userId = null; $this->values = [];
	}
	/** @brief Set. */
	public function Set(string $key, mixed $value): void
	{
		$this->values[$key] = $value;
	}
	/** @brief Get. */
	public function Get(string $key, mixed $default = null): mixed
	{
		return $this->values[$key] ?? $default;
	}
	/** @brief Remove. */
	public function Remove(string $key): void
	{
		unset($this->values[$key]);
	}
	/** @brief Set flash. */
	public function SetFlash(string $type, string $message): void
	{
		$this->values['pulse_flash'] = [$type, $message];
	}
}

final class AuthenticationLogger extends Logger
{
	public array $events = [];
	/** @brief Constructs this account component. */
	public function __construct()
	{
	}
	/** @brief Info. */
	public function Info(string $message, array $context = []): void
	{
		$this->events[] = ['info', $message, $context];
	}
	/** @brief Warning. */
	public function Warning(string $message, array $context = []): void
	{
		$this->events[] = ['warning', $message, $context];
	}
	/** @brief Error. */
	public function Error(string $message, array $context = []): void
	{
		$this->events[] = ['error', $message, $context];
	}
}

final class AuthenticationNoDatabase extends Database
{
	/** @brief Constructs this account component. */
	public function __construct()
	{
	}
	/** @brief Get connection. */
	public function GetConnection(): \PDO
	{
		throw new RuntimeException('Regression test attempted database access');
	}
}

final class AuthenticationRedirect extends RuntimeException {}

final class AuthenticationControllerProbe extends AuthController
{
	// Only dependencies used BEFORE the version gate are wired. Accidentally reaching
	// throttling/verification fails on an uninitialized property, rather than doing I/O.
	/** @brief Constructs this account component. */
	public function __construct(AuthService $auth, AuthenticationSession $session, TotpService $totp)
	{
		$this->_auth = $auth;
		$this->_session = $session;
		(new \ReflectionProperty(AuthController::class, '_totp'))->setValue($this, $totp);
	}
	/** @brief Redirect. */
	protected function Redirect(string $path): void
	{
		throw new AuthenticationRedirect($path);
	}
}

final class AuthenticationRegressionCases
{
	/** @brief Same. */
	public static function same(mixed $expected, mixed $actual): void
	{
		if ($expected !== $actual)
		{
			throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
		}
	}

	/** @brief User. */
	private static function user(array $changes = []): array
	{
		static $hash;
		$hash ??= password_hash('correct horse battery staple', PASSWORD_DEFAULT);
		return array_replace([
			'id' => 42, 'email' => 'owner@example.test', 'password_hash' => $hash,
			'is_active' => 1, 'email_verified_at' => '2026-10-01 12:00:00',
			'approved_at' => '2026-10-02 12:00:00', 'auth_version' => 7,
			'password_change_required' => 0, 'totp_required' => 0, 'website_locale' => 'de',
		], $changes);
	}

	/** @brief Fixture. */
	private static function fixture(?array $user, bool $loggedIn = false): array
	{
		$users = new AuthenticationUsers($user);
		$session = new AuthenticationSession();
		$logger = new AuthenticationLogger();
		if ($loggedIn)
		{
			$session->userId = 42;
			$session->Set('pulse_account_auth_version', 7);
		}
		return [new AuthService($users, $session, $logger), $users, $session, $logger];
	}

	/** @brief Totp. */
	private static function totp(AuthenticationSession $session): TotpService
	{
		return new TotpService(new TotpCredentialRepository(new AuthenticationNoDatabase()), new TotpAlgorithm(),
			new TotpSecretProtector('', new EnvironmentFile('/nonexistent/auth-regression.env')), $session);
	}

	/** @brief Named executable behavior cases, shared by PHPUnit and the standalone runner. */
	public static function cases(): iterable
	{
		// Exhaustively test all combinations, including historical active-but-unapproved rows.
		for ($mask = 0; $mask < 8; ++$mask)
		{
			yield "activation requirements combination $mask" => static function () use ($mask): void
			{
				$user = self::user(['is_active' => ($mask & 1) ? '1' : '0',
					'email_verified_at' => ($mask & 2) ? '2026-10-01' : null,
					'approved_at' => ($mask & 4) ? '2026-10-02' : null]);
				[$auth, $users, $session] = self::fixture($user);
				$allowed = $mask === 7;
				self::same($allowed, $auth->AccountCanAuthenticate($user));
				self::same($allowed ? $user : null, $auth->VerifyPassword($user['email'], 'correct horse battery staple'));
				self::same(null, $session->userId); // Password verification is not a login.
				self::same($allowed, $auth->LoginUser(42, 'passkey'));
				self::same($allowed ? 1 : 0, $session->regenerations);
				self::same($allowed ? [42] : [], $users->loginWrites);
				[$existing, , $existingSession] = self::fixture($user, true);
				self::same($allowed, $existing->IsAuthenticated());
				self::same($allowed ? 0 : 1, $existingSession->logouts);
			};
		}
		foreach (['is_active', 'email_verified_at', 'approved_at'] as $field)
		{
			yield "missing activation field $field fails closed" => static function () use ($field): void
			{
				$user = self::user(); unset($user[$field]);
				[$auth] = self::fixture($user);
				self::same(false, $auth->AccountCanAuthenticate($user));
				self::same(false, $auth->LoginUser(42, 'password_totp'));
			};
		}
		yield 'unknown account and incorrect password never establish session' => static function (): void
		{
			[$auth, $users, $session] = self::fixture(self::user());
			self::same(null, $auth->VerifyPassword('unknown@example.test', 'wrong'));
			self::same(null, $auth->VerifyPassword('owner@example.test', 'wrong'));
			self::same(false, $auth->LoginUser(999, 'passkey'));
			self::same([], $users->passwordWrites);
			self::same([], $users->loginWrites);
			self::same(null, $session->userId);
		};
		yield 'successful login records version locale audit and resets anonymous cache' => static function (): void
		{
			[$auth, $users, $session, $logger] = self::fixture(self::user());
			self::same(null, $auth->GetCurrentUser());
			self::same(true, $auth->LoginUser(42, 'password_totp'));
			self::same(7, $session->Get('pulse_account_auth_version'));
			self::same('de', $session->Get('locale'));
			self::same(42, $auth->GetCurrentUser()['id']);
			self::same([42], $users->loginWrites);
			self::same(['user_id' => 42, 'method' => 'password_totp'], $logger->events[0][2]);
			$auth->Logout();
			self::same(false, $auth->IsAuthenticated());
			self::same(null, $session->Get('pulse_account_auth_version'));
		};
		foreach ([null, 0, 6, 8, '7'] as $version)
		{
			yield 'session version ' . var_export($version, true) => static function () use ($version): void
			{
				[$auth, , $session] = self::fixture(self::user(), true);
				$session->Set('pulse_account_auth_version', $version);
				self::same($version === '7', $auth->IsAuthenticated());
				self::same($version === '7' ? 0 : 1, $session->logouts);
			};
		}
		yield 'revocation is observed by next request and deleted accounts log out' => static function (): void
		{
			[$auth, $users, $session, $logger] = self::fixture(self::user(), true);
			self::same(true, $auth->IsAuthenticated());
			$users->user['auth_version'] = 8;
			self::same(false, (new AuthService($users, $session, $logger))->IsAuthenticated());
			[$deleted, , $deletedSession] = self::fixture(null, true);
			self::same(null, $deleted->GetCurrentUser());
			self::same(1, $deletedSession->logouts);
		};
		yield 'account disabled after password verification cannot complete login' => static function (): void
		{
			[$auth, $users, $session] = self::fixture(self::user());
			self::same(42, $auth->VerifyPassword('owner@example.test', 'correct horse battery staple')['id']);
			$users->user['is_active'] = 0;
			self::same(false, $auth->LoginUser(42, 'password'));
			self::same(null, $session->userId);
		};
		foreach ([0, 1] as $passwordRequired)
		{
			foreach ([0, 1] as $totpRequired)
			{
				foreach ([false, true] as $enabled)
				{
					yield "setup password=$passwordRequired totp=$totpRequired enabled=" . (int)$enabled => static function () use ($passwordRequired, $totpRequired, $enabled): void
					{
						[$auth] = self::fixture(self::user(['password_change_required' => $passwordRequired, 'totp_required' => $totpRequired]), true);
						self::same($passwordRequired ? '/profile?tab=password' : ($totpRequired && !$enabled ? '/profile?tab=security' : null), $auth->RequiredAccountAction($enabled));
					};
				}
			}
		}
		yield 'anonymous users have no account setup action' => static function (): void
		{
			[$auth] = self::fixture(self::user(['password_change_required' => 1, 'totp_required' => 1]));
			self::same(null, $auth->RequiredAccountAction(false));
		};
		yield 'pending TOTP preserves explicit version and location without authenticating' => static function (): void
		{
			$session = new AuthenticationSession(); $totp = self::totp($session);
			$location = ['latitude' => 52.5, 'longitude' => 13.4];
			$totp->BeginLogin(42, ' OWNER@example.test ', $location, 7);
			self::same(['user_id' => 42, 'email' => 'owner@example.test', 'auth_version' => 7, 'location' => $location], $totp->PendingLogin());
			self::same(null, $session->userId);
			$totp->CancelPendingLogin();
			self::same(null, $totp->PendingLogin());
		};
		yield 'expired pending TOTP is removed' => static function (): void
		{
			$session = new AuthenticationSession(); $totp = self::totp($session);
			$totp->BeginLogin(42, 'owner@example.test', null, 7);
			$session->values['pulse_totp_login']['created_at'] = time() - 301;
			self::same(null, $totp->PendingLogin());
			self::same(null, $session->Get('pulse_totp_login'));
		};
		foreach (['revoked', 'legacy', 'inactive', 'unverified', 'unapproved', 'deleted'] as $reason)
		{
			yield "controller rejects $reason pending TOTP before verification" => static function () use ($reason): void
			{
				$user = self::user();
				if ($reason === 'revoked')
				{
					$user['auth_version'] = 8;
				}
				if ($reason === 'inactive')
				{
					$user['is_active'] = 0;
				}
				if ($reason === 'unverified')
				{
					$user['email_verified_at'] = null;
				}
				if ($reason === 'unapproved')
				{
					$user['approved_at'] = null;
				}
				[$auth, $users, $session] = self::fixture($reason === 'deleted' ? null : $user);
				$totp = self::totp($session);
				$totp->BeginLogin(42, 'owner@example.test', null, 7);
				if ($reason === 'legacy')
				{
					unset($session->values['pulse_totp_login']['auth_version']);
				}
				$controller = new AuthenticationControllerProbe($auth, $session, $totp);
				try { $controller->VerifyTotp(); throw new RuntimeException('Expected rejection redirect'); }
				catch (AuthenticationRedirect $redirect)
				{
					self::same('/login', $redirect->getMessage());
				}
				self::same(null, $totp->PendingLogin());
				self::same(null, $session->userId);
				self::same([], $users->loginWrites);
			};
		}
		yield 'version validation accepts current account and does not cache revocation' => static function (): void
		{
			[$auth, $users] = self::fixture(self::user());
			self::same(true, $auth->IsAuthenticationVersionCurrent(42, 7));
			self::same(false, $auth->IsAuthenticationVersionCurrent(42, 6));
			$users->user['auth_version'] = 8;
			self::same(false, $auth->IsAuthenticationVersionCurrent(42, 7));
		};
	}
}
