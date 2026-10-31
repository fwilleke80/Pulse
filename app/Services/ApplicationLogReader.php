<?php

/**
 * @file ApplicationLogReader.php
 * @brief Bounded, reverse-order inspection of Pulse's private application log.
 */

declare(strict_types=1);

namespace Pulse\Services;

use Generator;
use RuntimeException;

/** @brief Reads application logs without loading the entire file into memory. */
final class ApplicationLogReader
{
	private string $_path;

	/** @brief Constructs a reader for an administrator-controlled log path. @param string $path Absolute trusted log file path. */
	public function __construct(string $path)
	{
		$this->_path = $path;
	}


	/**
	 * @brief Safely truncates the private application log while coordinating with active writers.
	 * @return bool True if an existing log was cleared; false if no log exists.
	 */
	public function Clear(): bool
	{
		if (is_link($this->_path))
		{
			throw new RuntimeException('Refusing to clear a symbolic-link log.');
		}

		if (!is_file($this->_path))
		{
			return false;
		}

		$handle = @fopen($this->_path, 'c+b');
		if ($handle === false)
		{
			throw new RuntimeException('Application log is not writable.');
		}

		try
		{
			if (!flock($handle, LOCK_EX))
			{
				throw new RuntimeException('Unable to lock the application log.');
			}
			try
			{
				if (!ftruncate($handle, 0) || !fflush($handle))
				{
					throw new RuntimeException('Unable to truncate the application log.');
				}
			}
			finally
			{
				flock($handle, LOCK_UN);
			}
		}
		finally
		{
			fclose($handle);
		}

		return true;
	}

	/**
	 * @brief Returns a page of the newest matching log messages.
	 * @param string $level Empty string for every level, otherwise an allowed severity.
	 * @param string $search Case-insensitive literal substring.
	 * @param int $page One-based page number.
	 * @param int $limit Maximum matching lines per page.
	 * @return array{entries:array<int,array{timestamp:string,level:string,message:string}>,has_more:bool,available:bool}
	 */
	public function Read(string $level = '', string $search = '', int $page = 1, int $limit = 100): array
	{
		if (!in_array($level, ['', 'ERROR', 'WARNING', 'INFO', 'DEBUG'], true))
		{
			$level = '';
		}
		$page = max(1, min(100, $page));
		$limit = max(1, min(200, $limit));
		$search = substr(trim($search), 0, 200);
		if (!is_file($this->_path))
		{
			return ['entries' => [], 'has_more' => false, 'available' => false];
		}
		$handle = @fopen($this->_path, 'rb');
		if ($handle === false)
		{
			throw new RuntimeException('Application log is not readable.');
		}

		$entries = [];
		$seen = 0;
		$skip = ($page - 1) * $limit;
		$hasMore = false;
		try
		{
			foreach ($this->LinesNewestFirst($handle) as $line)
			{
				if (!preg_match('/^(\S+) \[([A-Z]+)\] (.*)$/s', $line, $matches))
				{
					continue;
				}
				if (($level !== '' && $matches[2] !== $level)
					|| ($search !== '' && stripos($line, $search) === false))
				{
					continue;
				}
				if ($seen++ < $skip)
				{
					continue;
				}
				if (count($entries) === $limit)
				{
					$hasMore = true;
					break;
				}
				$entries[] = ['timestamp' => $matches[1], 'level' => $matches[2], 'message' => $matches[3]];
			}
		}
		finally
		{
			fclose($handle);
		}
		return ['entries' => $entries, 'has_more' => $hasMore, 'available' => true];
	}

	/** @brief Streams complete lines in reverse order using fixed-size file chunks. @return Generator<int,string> */
	private function LinesNewestFirst(mixed $handle): Generator
	{
		fseek($handle, 0, SEEK_END);
		$position = ftell($handle);
		$remaining = '';
		while ($position > 0)
		{
			$length = min($position, 8192);
			$position -= $length;
			fseek($handle, $position);
			$chunk = fread($handle, $length);
			if ($chunk === false)
			{
				throw new RuntimeException('Could not read application log.');
			}
			$parts = explode("\n", $chunk . $remaining);
			$remaining = array_shift($parts) ?? '';
			for ($index = count($parts) - 1; $index >= 0; --$index)
			{
				$line = rtrim($parts[$index], "\r");
				if ($line !== '')
				{
					yield $line;
				}
			}
		}
		if ($remaining !== '')
		{
			yield rtrim($remaining, "\r");
		}
	}
}
