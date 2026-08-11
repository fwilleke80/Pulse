<?php

/**
 * @file WebCronAuthenticator.php
 * @brief Constant-time authorization for the public notification cron endpoint.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Protects state-changing web cron execution with a deployment secret.
 */
final class WebCronAuthenticator
{
	private const MINIMUM_TOKEN_LENGTH = 32;

	/** @brief Returns whether a sufficiently strong web-cron token is configured. */
	public static function IsConfigured(string $configuredToken): bool
	{
		return strlen($configuredToken) >= self::MINIMUM_TOKEN_LENGTH
			&& strpbrk($configuredToken, "\r\n") === false;
	}

	/** @brief Authorizes one GET request without exposing token comparison timing. */
	public static function IsAuthorized(string $method, mixed $providedToken, string $configuredToken): bool
	{
		return $method === 'GET'
			&& self::IsConfigured($configuredToken)
			&& is_string($providedToken)
			&& hash_equals($configuredToken, $providedToken);
	}
}
