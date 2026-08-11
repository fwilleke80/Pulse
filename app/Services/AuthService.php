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
	 * @brief Attempts to authenticate a user.
	 * @param string $email Email address.
	 * @param string $password Plain-text password.
	 * @return bool True on success.
	 */
	public function Login(string $email, string $password): bool
	{
		$user = $this->_userRepository->FindByEmail($email);

		if ($user === null)
		{
			password_verify($password, self::DUMMY_PASSWORD_HASH);
			return false;
		}

		if (!(bool)$user['is_active'])
		{
			password_verify($password, (string)$user['password_hash']);
			$this->_logger->Warning('Login failed for inactive account');
			return false;
		}

		$passwordHash = (string)$user['password_hash'];

		if (!password_verify($password, $passwordHash))
		{
			$this->_logger->Warning('Login failed');
			return false;
		}

		$userId = (int)$user['id'];

		if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT))
		{
			$this->_userRepository->UpdatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT));
		}

		$this->_session->Regenerate();
		$this->_session->LoginUser($userId);
		$this->_userRepository->UpdateLastLoginAt($userId);

		$this->_logger->Info('Login successful', ['user_id' => $userId]);
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
	}

	/**
	 * @brief Returns the currently authenticated user.
	 * @return array<string, mixed>|null User row or null.
	 */
	public function GetCurrentUser(): ?array
	{
		$userId = $this->_session->GetUserId();

		if ($userId === null)
		{
			return null;
		}

		return $this->_userRepository->FindById($userId);
	}

	/**
	 * @brief Returns whether a user is authenticated.
	 * @return bool True if logged in.
	 */
	public function IsAuthenticated(): bool
	{
		return $this->_session->IsLoggedIn();
	}
}
