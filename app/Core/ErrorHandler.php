<?php

/**
 * @file ErrorHandler.php
 * @brief Production-safe PHP error and exception handling.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use ErrorException;
use Throwable;

/**
 * @brief Converts PHP errors to exceptions, logs details, and hides them in production responses.
 */
final class ErrorHandler
{
	private Logger $_logger;
	private bool $_debug;

	/**
	 * @brief Constructs the handler.
	 * @param Logger $logger Application logger.
	 * @param bool $debug Whether diagnostic responses may be shown.
	 */
	public function __construct(Logger $logger, bool $debug)
	{
		$this->_logger = $logger;
		$this->_debug = $debug;
	}

	/**
	 * @brief Registers error, exception, and fatal-shutdown handlers.
	 */
	public function Register(): void
	{
		error_reporting(E_ALL);
		ini_set('display_errors', '0');
		ini_set('display_startup_errors', '0');

		set_error_handler(function (int $severity, string $message, string $file, int $line): bool
		{
			if (!(error_reporting() & $severity))
			{
				return false;
			}

			throw new ErrorException($message, 0, $severity, $file, $line);
		});

		set_exception_handler(function (Throwable $throwable): void
		{
			$this->RenderThrowable($throwable);
		});

		register_shutdown_function(function (): void
		{
			$error = error_get_last();

			if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true))
			{
				return;
			}

			$this->RenderThrowable(new ErrorException(
				(string)$error['message'],
				0,
				(int)$error['type'],
				(string)$error['file'],
				(int)$error['line']
			));
		});
	}

	/**
	 * @brief Changes diagnostic response visibility after configuration has loaded.
	 * @param bool $debug Whether diagnostic responses may be shown.
	 */
	public function SetDebug(bool $debug): void
	{
		$this->_debug = $debug;
	}

	/**
	 * @brief Logs an exception and renders an appropriate response.
	 * @param Throwable $throwable Unhandled throwable.
	 */
	private function RenderThrowable(Throwable $throwable): void
	{
		$errorId = bin2hex(random_bytes(8));

		$this->_logger->Error('Unhandled application exception', [
			'error_id' => $errorId,
			'exception_class' => $throwable::class,
			'exception_message' => $throwable->getMessage(),
			'file' => $throwable->getFile(),
			'line' => $throwable->getLine(),
			'trace' => $throwable->getTraceAsString(),
		]);

		if (PHP_SAPI === 'cli')
		{
			fwrite(STDERR, $this->_debug ? (string)$throwable . PHP_EOL : 'Pulse failed. Error ID: ' . $errorId . PHP_EOL);
			return;
		}

		if (headers_sent())
		{
			return;
		}

		http_response_code(500);
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-store');

		$title = $this->_debug ? 'Pulse development error' : 'Pulse is temporarily unavailable';
		$detail = $this->_debug
			? nl2br(htmlspecialchars((string)$throwable, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
			: 'The error was recorded. Reference: ' . htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8');

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>';
		echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
		echo '</title></head><body><main><h1>';
		echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
		echo '</h1><p>' . $detail . '</p></main></body></html>';
	}
}
