<?php

/**
 * @file SafetyLanguagePreference.php
 * @brief Scopes explicit language choices to one public safety-contact request.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Generates stable session keys for per-token safety-page language overrides.
 */
final class SafetyLanguagePreference
{
	/**
	 * @brief Returns the session key for one raw safety token without storing the token itself.
	 * @param string $rawToken Raw 64-character safety token.
	 * @return string Session key containing only a SHA-256 digest.
	 */
	public static function SessionKey(string $rawToken): string
	{
		return 'pulse_safety_locale_' . hash('sha256', $rawToken);
	}

	/**
	 * @brief Extracts a valid safety token from a local confirmation-page redirect target.
	 * @param string $redirect Safe local redirect target.
	 * @return string|null Raw token or null when the target is not a safety confirmation page.
	 */
	public static function TokenFromRedirect(string $redirect): ?string
	{
		$path = parse_url($redirect, PHP_URL_PATH);

		if (!is_string($path) || !str_ends_with($path, '/safety/confirm'))
		{
			return null;
		}

		$query = parse_url($redirect, PHP_URL_QUERY);

		if (!is_string($query))
		{
			return null;
		}

		$values = [];
		parse_str($query, $values);
		$token = $values['token'] ?? null;

		if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/i', $token) !== 1)
		{
			return null;
		}

		return $token;
	}
}
