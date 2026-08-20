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
	private const MAXIMUM_DIAGNOSTIC_TOKEN_LENGTH = 512;

	/** @brief Returns whether a sufficiently strong web-cron token is configured. */
	public static function IsConfigured(string $configuredToken): bool
	{
		return strlen($configuredToken) >= self::MINIMUM_TOKEN_LENGTH
			&& strpbrk($configuredToken, "\r\n") === false;
	}

	/**
	 * @brief Produces a bounded scalar token value suitable for administrator diagnostics.
	 * @param mixed $providedToken Raw token query value.
	 * @return array{token:string|null,truncated:bool} Diagnostic token and truncation state.
	 */
	public static function DiagnosticToken(mixed $providedToken): array
	{
		if (!is_string($providedToken))
		{
			return ['token' => null, 'truncated' => false];
		}

		$truncated = strlen($providedToken) > self::MAXIMUM_DIAGNOSTIC_TOKEN_LENGTH;

		return [
			'token' => $truncated ? substr($providedToken, 0, self::MAXIMUM_DIAGNOSTIC_TOKEN_LENGTH) : $providedToken,
			'truncated' => $truncated,
		];
	}

	/** @brief Returns whether the supplied token exactly matches the configured token. */
	public static function IsTokenValid(mixed $providedToken, string $configuredToken): bool
	{
		return self::IsConfigured($configuredToken)
			&& is_string($providedToken)
			&& hash_equals($configuredToken, $providedToken);
	}

	/** @brief Authorizes one GET request without exposing token comparison timing. */
	public static function IsAuthorized(string $method, mixed $providedToken, string $configuredToken): bool
	{
		return $method === 'GET' && self::IsTokenValid($providedToken, $configuredToken);
	}
}
