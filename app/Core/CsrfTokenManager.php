<?php

/**
 * @file CsrfTokenManager.php
 * @brief Session-bound CSRF token generation and validation.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Protects state-changing requests with a random session token.
 */
final class CsrfTokenManager
{
	private const SESSION_KEY = 'pulse_csrf_token';

	private Session $_session;

	/**
	 * @brief Constructs the CSRF token manager.
	 * @param Session $session Session service.
	 */
	public function __construct(Session $session)
	{
		$this->_session = $session;
	}

	/**
	 * @brief Returns the active token, generating one when necessary.
	 * @return string
	 */
	public function Token(): string
	{
		$token = $this->_session->Get(self::SESSION_KEY);

		if (!is_string($token) || strlen($token) !== 64)
		{
			$token = bin2hex(random_bytes(32));
			$this->_session->Set(self::SESSION_KEY, $token);
		}

		return $token;
	}

	/**
	 * @brief Validates a submitted token using a timing-safe comparison.
	 * @param string $submittedToken Submitted token.
	 * @return bool
	 */
	public function IsValid(string $submittedToken): bool
	{
		return $submittedToken !== '' && hash_equals($this->Token(), $submittedToken);
	}

	/**
	 * @brief Replaces the active token after an authentication boundary.
	 */
	public function Rotate(): void
	{
		$this->_session->Set(self::SESSION_KEY, bin2hex(random_bytes(32)));
	}
}
