<?php

/**
 * @file RecipientPortalLanguagePreference.php
 * @brief Scopes explicit language choices to one public recipient portal invitation.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Generates stable session keys for per-token recipient-portal language overrides.
 */
final class RecipientPortalLanguagePreference
{
	/**
	 * @brief Returns the session key for one portal token without storing the raw token itself.
	 * @param string $rawToken Raw 64-character portal token.
	 * @return string Session key containing only a SHA-256 digest.
	 */
	public static function SessionKey(string $rawToken): string
	{
		return 'pulse_recipient_portal_locale_' . hash('sha256', $rawToken);
	}

	/**
	 * @brief Extracts a valid portal token from a local recipient-portal redirect target.
	 * @param string $redirect Safe local redirect target.
	 * @return string|null Raw token or null when the target is not a recipient portal page.
	 */
	public static function TokenFromRedirect(string $redirect): ?string
	{
		$path = parse_url($redirect, PHP_URL_PATH);

		if (!is_string($path) || !in_array($path, ['/portal', '/portal/access'], true))
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
