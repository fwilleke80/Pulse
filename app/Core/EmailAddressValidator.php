<?php

/**
 * @file EmailAddressValidator.php
 * @brief Email format validation and conservative typo suggestions.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Validates recipient addresses without contacting their owners.
 */
final class EmailAddressValidator
{
	/** @var array<string, string> */
	private const DOMAIN_SUGGESTIONS = [
		'gamil.com' => 'gmail.com',
		'gmial.com' => 'gmail.com',
		'gmail.con' => 'gmail.com',
		'hotnail.com' => 'hotmail.com',
		'hotmail.con' => 'hotmail.com',
		'outlok.com' => 'outlook.com',
		'outlook.con' => 'outlook.com',
		'yahoo.con' => 'yahoo.com',
		'icloud.con' => 'icloud.com',
		'protonmail.con' => 'protonmail.com',
	];

	/** @brief Returns whether an address has a valid email format. @param string $email Address to validate. @return bool */
	public static function IsValid(string $email): bool
	{
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * @brief Suggests a likely domain correction for a small set of common typos.
	 * @param string $email Address to inspect.
	 * @return string|null Suggested full address, or null when none is known.
	 */
	public static function Suggestion(string $email): ?string
	{
		$separator = strrpos($email, '@');

		if ($separator === false)
		{
			return null;
		}

		$localPart = substr($email, 0, $separator);
		$domain = strtolower(substr($email, $separator + 1));
		$suggestion = self::DOMAIN_SUGGESTIONS[$domain] ?? null;

		return is_string($suggestion) ? $localPart . '@' . $suggestion : null;
	}
}
