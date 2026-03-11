<?php

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\Session;
use Pulse\Repositories\UserRepository;

/**
 * @brief Authentication service.
 */
class AuthService
{
	private UserRepository $_userRepository;
	private Session $_session;

	/**
	 * @brief Constructs the authentication service.
	 * @param UserRepository $userRepository User repository.
	 * @param Session $session Session service.
	 */
	public function __construct(UserRepository $userRepository, Session $session)
	{
		$this->_userRepository = $userRepository;
		$this->_session = $session;
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
			return false;
		}

		if (!(bool)$user['is_active'])
		{
			return false;
		}

		$passwordHash = (string)$user['password_hash'];

		if (!password_verify($password, $passwordHash))
		{
			return false;
		}

		$userId = (int)$user['id'];
		$this->_session->Regenerate();
		$this->_session->LoginUser($userId);
		$this->_userRepository->UpdateLastLoginAt($userId);

		return true;
	}

	/**
	 * @brief Logs the current user out.
	 */
	public function Logout(): void
	{
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