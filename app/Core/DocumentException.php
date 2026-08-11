<?php

/**
 * @file DocumentException.php
 * @brief User-safe document operation error.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Carries a translation key without exposing filesystem or database details.
 */
class DocumentException extends RuntimeException
{
	private string $_translationKey;

	/** @brief Constructs the exception. @param string $translationKey User-facing translation key. */
	public function __construct(string $translationKey)
	{
		parent::__construct($translationKey);
		$this->_translationKey = $translationKey;
	}

	/** @brief Returns the user-facing translation key. @return string */
	public function TranslationKey(): string
	{
		return $this->_translationKey;
	}
}
