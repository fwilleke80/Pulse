<?php

/**
 * @file RecipientPortalAccess.php
 * @brief Session-key helpers for authenticated recipient portal access.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Keeps recipient authentication scoped to one portal token without storing that token in session data.
 */
final class RecipientPortalAccess
{
	/**
	 * @brief Returns the authentication-session key for one raw portal token.
	 * @param string $rawToken Raw portal invitation token.
	 * @return string Session key containing only a SHA-256 digest.
	 */
	public static function SessionKey(string $rawToken): string
	{
		return 'pulse_recipient_portal_access_' . hash('sha256', $rawToken);
	}

	/**
	 * @brief Returns the session key for the deliberate permanent-close confirmation challenge.
	 * @param string $rawToken Raw portal invitation token.
	 * @return string Session key containing only a SHA-256 digest of the portal token.
	 */
	public static function CloseConfirmationKey(string $rawToken): string
	{
		return 'pulse_recipient_portal_close_' . hash('sha256', $rawToken);
	}
}
