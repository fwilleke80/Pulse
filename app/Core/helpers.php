<?php

/**
 * @file helpers.php
 * @brief Escaping, translation, CSRF, and date helpers for Pulse views.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Core\CsrfTokenManager;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Translator;

$__pulseTranslator = null;
$__pulseCsrfTokenManager = null;
$__pulseNotificationLanguage = null;
$__pulseDisplayTimezone = 'Europe/Berlin';

/** @brief Escapes a string for safe HTML output. @param string $value Input string. @return string */
function e(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @brief Registers the global translator. @param Translator $translator Translator instance. */
function setTranslator(Translator $translator): void
{
	global $__pulseTranslator;
	$__pulseTranslator = $translator;
}

/** @brief Registers the global CSRF token manager. @param CsrfTokenManager $manager Token manager. */
function setCsrfTokenManager(CsrfTokenManager $manager): void
{
	global $__pulseCsrfTokenManager;
	$__pulseCsrfTokenManager = $manager;
}

/** @brief Sets the timezone used to format UTC database timestamps. @param string $timezone IANA timezone. */
function setDisplayTimezone(string $timezone): void
{
	global $__pulseDisplayTimezone;
	$__pulseDisplayTimezone = $timezone;
}

/** @brief Registers the notification-language resolver used by views. @param NotificationLanguage $resolver Notification-language resolver. */
function setNotificationLanguageResolver(NotificationLanguage $resolver): void
{
	global $__pulseNotificationLanguage;
	$__pulseNotificationLanguage = $resolver;
}

/**
 * @brief Returns a translated language name with the deployment default as fallback.
 * @param string|null $locale Stored notification locale.
 * @return string Human-readable language name.
 */
function notification_language_name(?string $locale): string
{
	global $__pulseNotificationLanguage;

	if ($__pulseNotificationLanguage instanceof NotificationLanguage)
	{
		$locale = $__pulseNotificationLanguage->Resolve($locale);
	}
	else
	{
		$locale = is_string($locale) && trim($locale) !== '' ? trim($locale) : 'de';
	}

	$key = 'notification.language.' . $locale;
	$translated = __($key);
	return $translated !== $key ? $translated : (string)$locale;
}

/**
 * @brief Performs a raw translation.
 * @param string $key Translation key.
 * @param array<string, scalar|null> $params Translation parameters.
 * @return string
 */
function __(string $key, array $params = []): string
{
	global $__pulseTranslator;

	if (!$__pulseTranslator instanceof Translator)
	{
		return $key;
	}

	$text = $__pulseTranslator->Translate($key);

	foreach ($params as $name => $value)
	{
		$text = str_replace('{' . $name . '}', (string)$value, $text);
	}

	return $text;
}

/** @brief Returns an HTML-escaped translation. @param string $key Translation key. @param array<string, scalar|null> $params Translation parameters. @return string */
function e__(string $key, array $params = []): string
{
	return e(__($key, $params));
}

/** @brief Returns a hidden CSRF field for a state-changing form. @return string */
function csrf_field(): string
{
	global $__pulseCsrfTokenManager;

	if (!$__pulseCsrfTokenManager instanceof CsrfTokenManager)
	{
		throw new RuntimeException('CSRF token manager has not been initialized.');
	}

	return '<input type="hidden" name="_csrf_token" value="' . e($__pulseCsrfTokenManager->Token()) . '">' . PHP_EOL;
}

/** @brief Abbreviates a string to a maximum length. @param string $text Input text. @param int $maxLength Maximum length. @return string */
function abbrev(string $text, int $maxLength): string
{
	if ($maxLength <= 3 || strlen($text) <= $maxLength)
	{
		return $text;
	}

	return substr($text, 0, $maxLength - 3) . '...';
}

/**
 * @brief Formats a UTC database timestamp in the configured display timezone.
 * @param string|null $value UTC timestamp.
 * @param string $fallback Returned for empty or invalid input.
 * @return string
 */
function format_datetime(?string $value, string $fallback = '—'): string
{
	global $__pulseDisplayTimezone;

	if ($value === null || trim($value) === '')
	{
		return $fallback;
	}

	try
	{
		$date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
		return $date->setTimezone(new DateTimeZone($__pulseDisplayTimezone))->format('Y-m-d H:i');
	}
	catch (Throwable)
	{
		return $fallback;
	}
}

/**
 * @brief Returns whether a monitor is active and currently due.
 * @param array<string, mixed> $monitor Monitor row.
 * @return bool
 */
function is_monitor_due(array $monitor): bool
{
	return in_array(monitor_status($monitor), ['awaiting', 'safety-pending', 'overdue', 'escalated'], true);
}

/**
 * @brief Returns the user-facing monitor state identifier.
 * @param array<string, mixed> $monitor Monitor row.
 * @return string One of checked-in, awaiting, safety-pending, overdue, escalated, or paused.
 */
function monitor_status(array $monitor): string
{
	if (!empty($monitor['is_paused']))
	{
		return 'paused';
	}

	$cycleStatus = (string)($monitor['latest_cycle_status'] ?? '');

	if ($cycleStatus === 'scheduled')
	{
		return 'checked-in';
	}

	if ($cycleStatus === 'safety_pending')
	{
		return 'safety-pending';
	}

	if (in_array($cycleStatus, ['awaiting', 'overdue', 'escalated'], true))
	{
		return $cycleStatus;
	}

	$nextDue = $monitor['next_check_due_at'] ?? null;

	if (!is_string($nextDue) || $nextDue === '')
	{
		return 'awaiting';
	}

	$dueTimestamp = strtotime($nextDue . ' UTC');

	if ($dueTimestamp === false || $dueTimestamp > time())
	{
		return 'checked-in';
	}

	return 'awaiting';
}
