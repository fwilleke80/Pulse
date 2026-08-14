<?php

/**
 * @file WebsiteLanguagePreference.php
 * @brief Persists the non-sensitive website locale across login and logout.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Handles the small persistent locale cookie used by public/login pages.
 */
final class WebsiteLanguagePreference
{
	private const COOKIE_NAME = 'pulse_locale';
	private const COOKIE_LIFETIME_SECONDS = 31536000;

	/**
	 * @brief Reads the persisted locale cookie.
	 * @return string|null Locale code or null.
	 */
	public static function Read(): ?string
	{
		$value = $_COOKIE[self::COOKIE_NAME] ?? null;

		if (!is_string($value) || preg_match('/^[A-Za-z0-9_-]{2,10}$/', $value) !== 1)
		{
			return null;
		}

		return $value;
	}

	/**
	 * @brief Persists a locale for future logged-out requests.
	 * @param string $locale Locale code.
	 * @param bool $secure Whether the cookie should be HTTPS-only.
	 */
	public static function Write(string $locale, bool $secure): void
	{
		if (preg_match('/^[A-Za-z0-9_-]{2,10}$/', $locale) !== 1)
		{
			return;
		}

		setcookie(self::COOKIE_NAME, $locale, [
			'expires' => time() + self::COOKIE_LIFETIME_SECONDS,
			'path' => '/',
			'domain' => '',
			'secure' => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		]);

		$_COOKIE[self::COOKIE_NAME] = $locale;
	}
}
