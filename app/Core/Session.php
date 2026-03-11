<?php

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Small session wrapper for Pulse authentication.
 */
class Session
{
	private const USER_ID_KEY = 'pulse_user_id';

	/**
	 * @brief Starts the PHP session if needed.
	 */
	public function Start(): void
	{
		if (session_status() === PHP_SESSION_NONE)
		{
			session_start();
		}
	}

	/**
	 * @brief Regenerates the active session ID.
	 */
	public function Regenerate(): void
	{
		$this->Start();
		session_regenerate_id(true);
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
	 * @brief Logs the current user out.
	 */
	public function Logout(): void
	{
		$this->Start();
		$_SESSION = [];

		if (ini_get('session.use_cookies'))
		{
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				(bool)$params['secure'],
				(bool)$params['httponly']
			);
		}

		session_destroy();
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
}