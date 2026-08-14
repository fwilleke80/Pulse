<?php

/**
 * @file RecipientMessageValidator.php
 * @brief Central validation for recipient notification templates.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

/**
 * @brief Validates the subject/body pair used for a recipient notification.
 */
final class RecipientMessageValidator
{
	public const INCOMPLETE_MESSAGE = 'incomplete_message';
	public const PORTAL_URL_MISSING = 'recipient_portal_url_missing';
	public const PORTAL_URL_IN_SUBJECT = 'recipient_portal_url_in_subject';

	/**
	 * @brief Returns configuration issue codes for one recipient mail template.
	 *
	 * An empty subject/body pair is valid because Pulse then uses the built-in
	 * localized recipient template, which already contains {url}.
	 *
	 * @return array<int, string>
	 */
	public static function Validate(string $subject, string $body): array
	{
		$subject = trim($subject);
		$body = trim($body);
		$issues = [];

		if (($subject === '') !== ($body === ''))
		{
			$issues[] = self::INCOMPLETE_MESSAGE;
		}

		if ($body !== '' && !str_contains($body, '{url}'))
		{
			$issues[] = self::PORTAL_URL_MISSING;
		}

		if (str_contains($subject, '{url}'))
		{
			$issues[] = self::PORTAL_URL_IN_SUBJECT;
		}

		return array_values(array_unique($issues));
	}
}
