<?php

/**
 * @file NotificationLanguage.php
 * @brief Validates and resolves recipient-specific notification languages.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use InvalidArgumentException;

/**
 * @brief Keeps notification language choices independent from the active interface language.
 */
final class NotificationLanguage
{
	/** @var array<int, string> */
	private array $_supportedLocales;
	private string $_defaultLocale;

	/**
	 * @brief Constructs the resolver.
	 * @param array<int, string> $supportedLocales Supported notification locales.
	 * @param string $defaultLocale Fallback locale for legacy recipients.
	 */
	public function __construct(array $supportedLocales, string $defaultLocale)
	{
		$this->_supportedLocales = array_values(array_unique($supportedLocales));

		if (!in_array($defaultLocale, $this->_supportedLocales, true))
		{
			throw new InvalidArgumentException('The default notification locale must be supported.');
		}

		$this->_defaultLocale = $defaultLocale;
	}

	/** @brief Returns whether a submitted locale is supported. */
	public function IsSupported(string $locale): bool
	{
		return in_array($locale, $this->_supportedLocales, true);
	}

	/** @brief Resolves an optional stored locale with the deployment default as fallback. */
	public function Resolve(?string $locale): string
	{
		return $locale !== null && $this->IsSupported($locale) ? $locale : $this->_defaultLocale;
	}

	/** @return array<int, string> @brief Returns the supported locales in display order. */
	public function SupportedLocales(): array
	{
		return $this->_supportedLocales;
	}
}
