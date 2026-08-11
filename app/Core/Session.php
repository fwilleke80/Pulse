<?php

/**
 * @file Session.php
 * @brief Hardened session wrapper for Pulse authentication and request state.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Manages secure session cookies, expiry, regeneration, and flash data.
 */
class Session
{
	private const USER_ID_KEY = 'pulse_user_id';
	private const CREATED_AT_KEY = 'pulse_session_created_at';
	private const LAST_ACTIVITY_KEY = 'pulse_session_last_activity_at';
	private const LAST_REGENERATION_KEY = 'pulse_session_last_regeneration_at';

	/** @var array<string, mixed> */
	private array $_config;

	/**
	 * @brief Constructs the session service.
	 * @param array<string, mixed> $config Session configuration.
	 */
	public function __construct(array $config = [])
	{
		$this->_config = $config;
	}

	/**
	 * @brief Starts and validates the PHP session if needed.
	 */
	public function Start(): void
	{
		if (session_status() === PHP_SESSION_NONE)
		{
			$this->ConfigureCookie();

			if (!session_start())
			{
				throw new \RuntimeException('Unable to start the session.');
			}
		}

		$this->EnforceLifetime();
	}

	/**
	 * @brief Regenerates the active session ID.
	 */
	public function Regenerate(): void
	{
		$this->Start();

		if (!session_regenerate_id(true))
		{
			throw new \RuntimeException('Unable to regenerate the session ID.');
		}

		$_SESSION[self::LAST_REGENERATION_KEY] = time();
	}

	/**
	 * @brief Logs a user in by storing their user ID.
	 * @param int $userId User ID.
	 */
	public function LoginUser(int $userId): void
	{
		$this->Start();
		$_SESSION[self::USER_ID_KEY] = $userId;
	}

	/**
	 * @brief Returns the currently logged-in user ID.
	 * @return int|null User ID or null.
	 */
	public function GetUserId(): ?int
	{
		$this->Start();

		if (!isset($_SESSION[self::USER_ID_KEY]))
		{
			return null;
		}

		return (int)$_SESSION[self::USER_ID_KEY];
	}

	/**
	 * @brief Returns whether a user is logged in.
	 * @return bool True if authenticated.
	 */
	public function IsLoggedIn(): bool
	{
		return $this->GetUserId() !== null;
	}

	/**
	 * @brief Logs the current user out and expires the session cookie.
	 */
	public function Logout(): void
	{
		$this->Start();
		$_SESSION = [];

		if (ini_get('session.use_cookies'))
		{
			$params = session_get_cookie_params();
			setcookie(session_name(), '', [
				'expires' => time() - 42000,
				'path' => (string)$params['path'],
				'domain' => (string)$params['domain'],
				'secure' => (bool)$params['secure'],
				'httponly' => (bool)$params['httponly'],
				'samesite' => (string)($params['samesite'] ?? 'Strict'),
			]);
		}

		session_destroy();
	}

	/**
	 * @brief Stores an arbitrary session value.
	 * @param string $key Session key.
	 * @param mixed $value Value to store.
	 */
	public function Set(string $key, mixed $value): void
	{
		$this->Start();
		$_SESSION[$key] = $value;
	}

	/**
	 * @brief Returns an arbitrary session value or a default if the key is not set.
	 * @param string $key Session key.
	 * @param mixed $default Default value if key does not exist.
	 * @return mixed
	 */
	public function Get(string $key, mixed $default = null): mixed
	{
		$this->Start();

		return $_SESSION[$key] ?? $default;
	}

	/**
	 * @brief Stores a flash message.
	 * @param string $type Message type.
	 * @param string $message Message text.
	 */
	public function SetFlash(string $type, string $message): void
	{
		$this->Start();
		$_SESSION['pulse_flash'] = [
			'type' => $type,
			'message' => $message,
		];
	}

	/**
	 * @brief Pulls and removes the current flash message.
	 * @return array<string, string>|null Flash data.
	 */
	public function PullFlash(): ?array
	{
		$this->Start();

		if (!isset($_SESSION['pulse_flash']) || !is_array($_SESSION['pulse_flash']))
		{
			return null;
		}

		$flash = $_SESSION['pulse_flash'];
		unset($_SESSION['pulse_flash']);

		return $flash;
	}

	/**
	 * @brief Applies secure PHP session and cookie settings before session_start().
	 */
	private function ConfigureCookie(): void
	{
		$name = (string)($this->_config['name'] ?? 'pulse_session');
		$sameSite = (string)($this->_config['cookie_samesite'] ?? 'Strict');

		if (!in_array($sameSite, ['Strict', 'Lax', 'None'], true))
		{
			$sameSite = 'Strict';
		}

		session_name($name);
		ini_set('session.use_strict_mode', '1');
		ini_set('session.use_only_cookies', '1');
		ini_set('session.use_trans_sid', '0');
		session_set_cookie_params([
			'lifetime' => 0,
			'path' => '/',
			'domain' => '',
			'secure' => (bool)($this->_config['cookie_secure'] ?? true),
			'httponly' => true,
			'samesite' => $sameSite,
		]);
	}

	/**
	 * @brief Enforces idle, absolute, and regeneration time limits.
	 */
	private function EnforceLifetime(): void
	{
		$now = time();
		$createdAt = (int)($_SESSION[self::CREATED_AT_KEY] ?? $now);
		$lastActivity = (int)($_SESSION[self::LAST_ACTIVITY_KEY] ?? $now);
		$lastRegeneration = (int)($_SESSION[self::LAST_REGENERATION_KEY] ?? $now);
		$idleTimeout = (int)($this->_config['idle_timeout_seconds'] ?? 1800);
		$absoluteTimeout = (int)($this->_config['absolute_timeout_seconds'] ?? 43200);
		$regenerationInterval = (int)($this->_config['regeneration_interval_seconds'] ?? 900);

		if (($now - $lastActivity) > $idleTimeout || ($now - $createdAt) > $absoluteTimeout)
		{
			$_SESSION = [];
			session_regenerate_id(true);
			$createdAt = $now;
			$lastRegeneration = $now;
		}
		elseif (($now - $lastRegeneration) > $regenerationInterval)
		{
			session_regenerate_id(true);
			$lastRegeneration = $now;
		}

		$_SESSION[self::CREATED_AT_KEY] = $createdAt;
		$_SESSION[self::LAST_ACTIVITY_KEY] = $now;
		$_SESSION[self::LAST_REGENERATION_KEY] = $lastRegeneration;
	}
}
