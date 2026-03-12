<?php

declare(strict_types=1);

namespace Pulse\Controllers;

/**
 * @brief Controller for authentication routes.
 */
class AuthController extends BaseController
{
	/**
	 * @brief Displays the login form.
	 * @return string
	 */
	public function ShowLogin(): string
	{
		if ($this->_auth->IsAuthenticated())
		{
			$this->Redirect('/');
		}

		return $this->_view->Render('auth.login');
	}

	/**
	 * @brief Processes a login attempt.
	 */
	public function Login(): void
	{
		$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
		$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

		if ($email === '' || $password === '')
		{
			$this->Flash('error', e__('flash.login.required'));
			$this->Redirect('/login');
		}

		if (!$this->_auth->Login($email, $password))
		{
			$this->Flash('error', e__('flash.login.failed'));
			$this->Redirect('/login');
		}

		$this->Flash('success', e__('flash.login.successful'));
		$this->Redirect('/');
	}

	/**
	 * @brief Logs the current user out.
	 */
	public function Logout(): void
	{
		$this->_auth->Logout();
		$this->Redirect('/login');
	}
}