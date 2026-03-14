<?php

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Core\Logger;
use Pulse\Services\AuthService;

/**
 * @brief Small shared base controller for common controller utilities.
 */
abstract class BaseController
{
	protected View $_view;
	protected Session $_session;
	protected AuthService $_auth;

	protected Logger $_logger;

	/**
	 * @brief Constructs the base controller.
	 * @param View $view View renderer.
	 * @param Session $session Session service.
	 * @param AuthService $auth Authentication service.
	 */
	public function __construct(
		View $view,
		Session $session,
		AuthService $auth,
		Logger $logger
	)
	{
		$this->_view = $view;
		$this->_session = $session;
		$this->_auth = $auth;
		$this->_logger = $logger;
	}

	/**
	 * @brief Redirects to a different path and terminates execution.
	 * @param string $path Target path.
	 */
	protected function Redirect(string $path): void
	{
		header('Location: ' . $path);
		exit;
	}

	/**
	 * @brief Ensures a user is authenticated.
	 */
	protected function RequireAuth(): void
	{
		if (!$this->_auth->IsAuthenticated())
		{
			$this->Redirect('/login');
		}
	}

	/**
	 * @brief Returns the current authenticated user.
	 * @return array<string, mixed>
	 */
	protected function RequireUser(): array
	{
		$this->RequireAuth();

		$user = $this->_auth->GetCurrentUser();

		if (!is_array($user))
		{
			$this->Redirect('/login');
		}

		return $user;
	}

	/**
	 * @brief Stores a flash message.
	 * @param string $type Message type.
	 * @param string $message Message text.
	 */
	protected function Flash(string $type, string $message): void
	{
		$this->_session->SetFlash($type, $message);
	}
}