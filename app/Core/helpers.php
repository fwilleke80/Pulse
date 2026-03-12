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
function __(string $key): string
{
	global $__pulseTranslator;

	if ($__pulseTranslator === null)
	{
		return $key;
	}

	return $__pulseTranslator->Translate($key);
}

/**
 * HTML-escaped translation.
 */
function e__(string $key): string
{
	return htmlspecialchars(
		__( $key ),
		ENT_QUOTES,
		'UTF-8'
	);
}