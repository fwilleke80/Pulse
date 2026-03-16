<?php

/**
 * @file Translator.php
 * @brief Simple translation service.
 * Loads the appropriate language file based on the locale and provides a method to translate keys to their corresponding strings.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Simple translation service.
 * Loads the appropriate language file based on the locale and provides a method to translate keys to their corresponding strings.
 */
class Translator
{
	private array $_strings;

	/**
	 * @brief Constructs the translator by loading the appropriate language file.
	 * @param string $langPath Path to the language files.
	 * @param string $locale Locale to load (e.g. "en", "de").
	 * @throws \RuntimeException If the language file cannot be found.
	 */
	public function __construct(string $langPath, string $locale)
	{
		$file = $langPath . '/' . $locale . '.php';

		if (!is_file($file))
		{
			throw new \RuntimeException('Language file not found: ' . $locale);
		}

		$this->_strings = require $file;
	}

	/**
	 * @brief Returns the translated string for a key, or the key itself if no translation exists.
	 * @param string $key Translation key.
	 * @return string
	 */
	public function Translate(string $key): string
	{
		return $this->_strings[$key] ?? $key;
	}
}