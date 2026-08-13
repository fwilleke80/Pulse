<?php

/**
 * @file LanguageCatalog.php
 * @brief Discovers installed Pulse language files and their display names.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Treats app/Lang/*.php as the source of truth for installed languages.
 */
final class LanguageCatalog
{
	private string $_languagePath;
	private string $_fallbackLocale;
	/** @var array<int, string> */
	private array $_locales = [];
	/** @var array<string, string> */
	private array $_names = [];

	/**
	 * @brief Discovers installed language files.
	 * @param string $languagePath Directory containing locale PHP files.
	 * @param string $fallbackLocale Locale used by Translator when a key is missing.
	 */
	public function __construct(string $languagePath, string $fallbackLocale = 'en')
	{
		$this->_languagePath = rtrim($languagePath, '/\\');
		$this->_fallbackLocale = trim($fallbackLocale);
		$this->Discover();

		if (!$this->Has($this->_fallbackLocale))
		{
			throw new RuntimeException('Fallback language file not found: ' . $this->_fallbackLocale);
		}
	}

	/** @return array<int, string> @brief Returns installed locales in stable display order. */
	public function Locales(): array
	{
		return $this->_locales;
	}

	/** @brief Returns whether a locale has an installed language file. */
	public function Has(string $locale): bool
	{
		return in_array($locale, $this->_locales, true);
	}

	/** @brief Returns the native display name declared by a language file. */
	public function Name(string $locale): string
	{
		return $this->_names[$locale] ?? $locale;
	}

	/** @brief Returns the locale used as the translation-key fallback. */
	public function FallbackLocale(): string
	{
		return $this->_fallbackLocale;
	}

	/** @brief Scans the language directory and loads lightweight locale metadata. */
	private function Discover(): void
	{
		$files = glob($this->_languagePath . '/*.php');

		if ($files === false || $files === [])
		{
			throw new RuntimeException('No Pulse language files found in: ' . $this->_languagePath);
		}

		$locales = [];
		$names = [];

		foreach ($files as $file)
		{
			$locale = pathinfo($file, PATHINFO_FILENAME);

			if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,9}$/', $locale) !== 1)
			{
				continue;
			}

			$strings = require $file;

			if (!is_array($strings))
			{
				throw new RuntimeException('Language file must return an array: ' . $file);
			}

			$name = isset($strings['_language.name']) && is_string($strings['_language.name'])
				? trim($strings['_language.name'])
				: '';

			$locales[] = $locale;
			$names[$locale] = $name !== '' ? $name : $locale;
		}

		if ($locales === [])
		{
			throw new RuntimeException('No valid Pulse locale files found in: ' . $this->_languagePath);
		}

		usort($locales, function (string $left, string $right) use ($names): int
		{
			if ($left === $this->_fallbackLocale)
			{
				return -1;
			}

			if ($right === $this->_fallbackLocale)
			{
				return 1;
			}

			return strnatcasecmp($names[$left] ?? $left, $names[$right] ?? $right);
		});

		$this->_locales = array_values(array_unique($locales));
		$this->_names = $names;
	}
}
