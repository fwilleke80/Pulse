<?php

declare(strict_types=1);

use Pulse\Core\Translator;

$__pulseTranslator = null;

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
	return htmlspecialchars(
		__( $key, $params ),
		ENT_QUOTES,
		'UTF-8'
	);
}