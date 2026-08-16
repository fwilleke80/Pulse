<?php

/**
 * @file AuthService.php
 * @brief Authentication service.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\Session;
use Pulse\Core\Logger;
use Pulse\Repositories\UserRepository;

/**
 * @brief Authentication service.
 */
class AuthService
{
	private const DUMMY_PASSWORD_HASH = '$2y$12$Zl/fapXT2oA15HFG.e9O2egnXQBhHEh9nL632fHBVf2bRewV2EskC';

	private UserRepository $_userRepository;
	private Session $_session;
	private Logger $_logger;
	private bool $_currentUserResolved = false;
	/** @var array<string, mixed>|null */
	private ?array $_currentUser = null;

	/**
	 * @brief Constructs the authentication service.
	 * @param UserRepository $userRepository User repository.
	 * @param Session $session Session service.
	 * @param Logger $logger Application logger.
	 */
	public function __construct(UserRepository $userRepository, Session $session, Logger $logger)
	{
		$this->_userRepository = $userRepository;
		$this->_session = $session;
		$this->_logger = $logger;
	}

	/**
	 * @brief Verifies password credentials without yet establishing a session.
	 * @param string $email Email address.
	 * @param string $password Plain-text password.
	 * @return array<string, mixed>|null Active verified user, or null on failure.
	 */
	public function VerifyPassword(string $email, string $password): ?array
	{
		$user = $this->_userRepository->FindByEmail($email);

		if ($user === null)
		{
			password_verify($password, self::DUMMY_PASSWORD_HASH);
			return null;
		}

		if (!(bool)$user['is_active'])
		{
			password_verify($password, (string)$user['password_hash']);
			$this->_logger->Warning('Login failed for inactive account');
			return null;
		}

		$passwordHash = (string)$user['password_hash'];

		if (!password_verify($password, $passwordHash))
		{
			$this->_logger->Warning('Login failed');
			return null;
		}

		$userId = (int)$user['id'];

		if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT))
		{
			$this->_userRepository->UpdatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT));
		}

		return $user;
	}

	/**
	 * @brief Establishes an authenticated session after any verified authentication method.
	 * @param int $userId Authenticated user ID.
	 * @param string $method Security method that verified the user.
	 * @return bool True when the active account was logged in.
	 */
	public function LoginUser(int $userId, string $method): bool
	{
		$user = $this->_userRepository->FindById($userId);

		if (!is_array($user) || !(bool)($user['is_active'] ?? false))
		{
			$this->_logger->Warning('Authentication completed for an unavailable account', [
				'user_id' => $userId,
				'method' => $method,
			]);
			return false;
		}

		$this->_session->Regenerate();
		$this->_session->LoginUser($userId);
		$this->_currentUserResolved = false;
		$this->_currentUser = null;

		if (!empty($user['website_locale']))
		{
			$this->_session->Set('locale', (string)$user['website_locale']);
		}

		$this->_userRepository->UpdateLastLoginAt($userId);
		$this->_logger->Info('Login successful', ['user_id' => $userId, 'method' => $method]);
		return true;
	}

	/**
	 * @brief Logs the current user out.
	 */
	public function Logout(): void
	{
		$userId = $this->_session->GetUserId();
		$this->_logger->Info('User with id logged out', [
			'user_id' => $userId,
		]);
		$this->_session->Logout();
		$this->_currentUserResolved = true;
		$this->_currentUser = null;
	}

	/**
	 * @brief Returns the currently authenticated user.
	 * @return array<string, mixed>|null User row or null.
	 */
	public function GetCurrentUser(): ?array
	{
		if ($this->_currentUserResolved)
		{
			return $this->_currentUser;
		}

		$this->_currentUserResolved = true;
		$userId = $this->_session->GetUserId();

		if ($userId === null)
		{
			$this->_currentUser = null;
			return null;
		}

		$user = $this->_userRepository->FindById($userId);

		if (!is_array($user) || !(bool)($user['is_active'] ?? false))
		{
			$this->_logger->Warning('Invalidated stale or inactive authenticated session', [
				'user_id' => $userId,
			]);
			$this->_session->Logout();
			$this->_currentUser = null;
			return null;
		}

		$this->_currentUser = $user;
		return $this->_currentUser;
	}

	/**
	 * @brief Returns whether a user is authenticated.
	 * @return bool True if logged in.
	 */
	public function IsAuthenticated(): bool
	{
		return $this->GetCurrentUser() !== null;
	}
}
