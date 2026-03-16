<?php

/**
 * @file helpers.php
 * @brief Global helper functions for Pulse.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Core\Translator;

$__pulseTranslator = null;

/**
 * @brief Escapes a string for safe HTML output.
 * @param string $value Input string.
 * @return string
 */
function e(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @brief Registers the global translator.
 * @param Translator $translator Translator instance.
 */
function setTranslator(Translator $translator): void
{
	global $__pulseTranslator;

	$__pulseTranslator = $translator;
}

/**
 * @brief Performs a raw translation.
 * @param string $key Translation key.
 * @param array $params Parameters for the translation.
 * @return string
 */
function __(string $key, array $params = []): string
{
	global $__pulseTranslator;

	if ($__pulseTranslator === null)
	{
		return $key;
	}

	$text = $__pulseTranslator->Translate($key);

	if ($params !== [])
	{
		foreach ($params as $name => $value)
		{
			$text = str_replace(
				'{' . $name . '}',
				(string)$value,
				$text
			);
		}
	}

	return $text;
}

/**
 * @brief HTML-escaped translation.
 * @param string $key Translation key.
 * @param array $params Parameters for the translation.
 * @return string
 */
function e__(string $key, array $params = []): string
{
	return e(__( $key, $params ));
}

/**
 * @brief Abbreviates a string to a maximum length and appends "...".
 * @param string $text The input string.
 * @param int $maxLength Maximum allowed length.
 * @return string
 */
function abbrev(string $text, int $maxLength): string
{
	$text = trim($text);

	if ($text === '')
	{
		return '';
	}

	if (mb_strlen($text) <= $maxLength)
	{
		return $text;
	}

	return mb_substr($text, 0, $maxLength - 3) . '...';
}