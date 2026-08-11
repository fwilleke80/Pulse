<?php

/**
 * @file Logger.php
 * @brief Privacy-conscious file logger for Pulse.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Writes structured application events while redacting sensitive values.
 */
class Logger
{
	private string $_logFile;

	/**
	 * @brief Constructs the logger.
	 * @param string $logFile Absolute path to the log file.
	 */
	public function __construct(string $logFile)
	{
		$this->_logFile = $logFile;
	}

	/** @brief Writes an informational log entry. @param string $message Log message. @param array<string, mixed> $context Structured context. */
	public function Info(string $message, array $context = []): void
	{
		$this->Write('INFO', $message, $context);
	}

	/** @brief Writes a warning log entry. @param string $message Log message. @param array<string, mixed> $context Structured context. */
	public function Warning(string $message, array $context = []): void
	{
		$this->Write('WARNING', $message, $context);
	}

	/** @brief Writes an error log entry. @param string $message Log message. @param array<string, mixed> $context Structured context. */
	public function Error(string $message, array $context = []): void
	{
		$this->Write('ERROR', $message, $context);
	}

	/**
	 * @brief Writes a log entry to disk.
	 * @param string $level Log level.
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Structured context.
	 */
	private function Write(string $level, string $message, array $context): void
	{
		$directory = dirname($this->_logFile);
		$fileExisted = is_file($this->_logFile);

		if (!is_dir($directory))
		{
			@mkdir($directory, 0770, true);
		}

		$contextJson = $context !== []
			? ' ' . json_encode($this->Redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			: '';
		$line = sprintf("%s [%s] %s%s\n", gmdate('c'), $level, $message, $contextJson);

		$written = @file_put_contents($this->_logFile, $line, FILE_APPEND | LOCK_EX);

		if ($written !== false && !$fileExisted)
		{
			@chmod($this->_logFile, 0600);
		}
	}

	/**
	 * @brief Recursively redacts common secret and personally identifying context fields.
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, mixed>
	 */
	private function Redact(array $context): array
	{
		$result = [];

		foreach ($context as $key => $value)
		{
			$keyName = strtolower((string)$key);

			if (preg_match('/password|secret|token|email|phone|filename|display_name|contact_name/', $keyName) === 1)
			{
				$result[$key] = '[redacted]';
				continue;
			}

			$result[$key] = is_array($value) ? $this->Redact($value) : $value;
		}

		return $result;
	}
}
