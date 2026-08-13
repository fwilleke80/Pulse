<?php

/**
 * @file Translator.php
 * @brief Simple translation service with English fallback support.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Loads one locale and falls back to another locale for missing keys.
 */
class Translator
{
	/** @var array<string, mixed> */
	private array $_strings;
	/** @var array<string, mixed> */
	private array $_fallbackStrings;

	/**
	 * @brief Constructs the translator by loading the requested and fallback language files.
	 * @param string $langPath Path to the language files.
	 * @param string $locale Locale to load (e.g. "en", "de", "it").
	 * @param string $fallbackLocale Locale used when a translation key is absent.
	 * @throws \RuntimeException If a required language file cannot be found or is invalid.
	 */
	public function __construct(string $langPath, string $locale, string $fallbackLocale = 'en')
	{
		$this->_strings = $this->LoadFile($langPath, $locale);
		$this->_fallbackStrings = $locale === $fallbackLocale
			? $this->_strings
			: $this->LoadFile($langPath, $fallbackLocale);
	}

	/**
	 * @brief Returns the translated string for a key, then the fallback translation, then the key itself.
	 * @param string $key Translation key.
	 * @return string
	 */
	public function Translate(string $key): string
	{
		$value = $this->_strings[$key] ?? null;

		if (is_string($value))
		{
			return $value;
		}

		$fallbackValue = $this->_fallbackStrings[$key] ?? null;
		return is_string($fallbackValue) ? $fallbackValue : $key;
	}

	/**
	 * @return array<string, mixed>
	 * @brief Loads one language file.
	 */
	private function LoadFile(string $langPath, string $locale): array
	{
		$file = rtrim($langPath, '/\\') . '/' . $locale . '.php';

		if (!is_file($file))
		{
			throw new \RuntimeException('Language file not found: ' . $locale);
		}

		$strings = require $file;

		if (!is_array($strings))
		{
			throw new \RuntimeException('Language file must return an array: ' . $locale);
		}

		return $strings;
	}
}
