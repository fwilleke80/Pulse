<?php

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Simple translation service.
 */
class Translator
{
	private array $_strings;

	public function __construct(string $langPath, string $locale)
	{
		$file = $langPath . '/' . $locale . '.php';

		if (!is_file($file))
		{
			throw new \RuntimeException('Language file not found: ' . $locale);
		}

		$this->_strings = require $file;
	}

	public function Translate(string $key): string
	{
		return $this->_strings[$key] ?? $key;
	}
}