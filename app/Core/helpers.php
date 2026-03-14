<?php

declare(strict_types=1);

use Pulse\Core\Translator;

$__pulseTranslator = null;

/**
 * Escapes a string for safe output in HTML.
 */
function e(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Registers the global translator.
 */
function setTranslator(Translator $translator): void
{
	global $__pulseTranslator;

	$__pulseTranslator = $translator;
}

/**
 * Raw translation.
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
 * HTML-escaped translation.
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