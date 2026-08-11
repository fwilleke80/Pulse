<?php

/**
 * @file SafeRedirect.php
 * @brief Validates local redirect targets.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Prevents redirects to external or malformed destinations.
 */
final class SafeRedirect
{
	/**
	 * @brief Returns a safe local target or the supplied fallback.
	 * @param string $target Requested redirect target.
	 * @param string $fallback Safe fallback path.
	 * @return string
	 */
	public static function Normalize(string $target, string $fallback = '/'): string
	{
		$target = trim($target);

		if (
			$target === ''
			|| !str_starts_with($target, '/')
			|| str_starts_with($target, '//')
			|| str_contains($target, '\\')
			|| preg_match('/[\x00-\x1F\x7F]/', $target) === 1
		)
		{
			return $fallback;
		}

		$parts = parse_url($target);

		if (
			$parts === false
			|| isset($parts['scheme'])
			|| isset($parts['host'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
		)
		{
			return $fallback;
		}

		return $target;
	}
}
