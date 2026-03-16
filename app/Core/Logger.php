<?php

/**
 * @file Logger.php
 * @brief Very small file logger for Pulse.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Very small file logger for Pulse.
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

	/**
	 * @brief Writes an info log entry.
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	public function Info(string $message, array $context = []): void
	{
		$this->Write('INFO', $message, $context);
	}

	/**
	 * @brief Writes a warning log entry.
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	public function Warning(string $message, array $context = []): void
	{
		$this->Write('WARNING', $message, $context);
	}

	/**
	 * @brief Writes an error log entry.
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	public function Error(string $message, array $context = []): void
	{
		$this->Write('ERROR', $message, $context);
	}

	/**
	 * @brief Writes a log entry to disk.
	 * @param string $level Log level.
	 * @param string $message Log message.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	private function Write(string $level, string $message, array $context = []): void
	{
		$timestamp = date('c');
		$contextJson = $context !== []
			? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			: '';

		$line = sprintf(
			"%s [%s] %s%s\n",
			$timestamp,
			$level,
			$message,
			$contextJson
		);

		file_put_contents($this->_logFile, $line, FILE_APPEND | LOCK_EX);
	}
}