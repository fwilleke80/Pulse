<?php

/**
 * @file EnvironmentFile.php
 * @brief Safe read/write access to Pulse's root .env configuration file.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Preserves comments and unknown keys while safely updating selected dotenv values.
 */
final class EnvironmentFile
{
	private string $_path;

	/**
	 * @brief Constructs an environment-file editor.
	 * @param string $path Absolute .env path.
	 */
	public function __construct(string $path)
	{
		$this->_path = $path;
	}

	/**
	 * @brief Returns the absolute file path.
	 * @return string
	 */
	public function Path(): string
	{
		return $this->_path;
	}

	/**
	 * @brief Returns whether the existing file, or its parent for a new file, is writable.
	 * @return bool
	 */
	public function IsWritable(): bool
	{
		if (is_file($this->_path))
		{
			return is_readable($this->_path) && is_writable($this->_path);
		}

		$directory = dirname($this->_path);
		return is_dir($directory) && is_writable($directory);
	}

	/**
	 * @brief Reads values defined specifically in the .env file, ignoring process overrides.
	 * @return array<string, string>
	 */
	public function ReadValues(): array
	{
		if (!is_file($this->_path) || !is_readable($this->_path))
		{
			return [];
		}

		$lines = file($this->_path, FILE_IGNORE_NEW_LINES);

		if (!is_array($lines))
		{
			throw new RuntimeException('Unable to read the environment file.');
		}

		$values = [];

		foreach ($lines as $line)
		{
			$parsed = self::ParseAssignment($line);

			if ($parsed === null)
			{
				continue;
			}

			$values[$parsed['name']] = $parsed['value'];
		}

		return $values;
	}

	/**
	 * @brief Updates selected values while preserving all unrelated content and comments.
	 * @param array<string, string> $values Values to write.
	 */
	public function Update(array $values): void
	{
		if (!$this->IsWritable())
		{
			throw new RuntimeException('The Pulse .env file is not writable.');
		}

		$lines = [];

		if (is_file($this->_path))
		{
			$loaded = file($this->_path, FILE_IGNORE_NEW_LINES);

			if (!is_array($loaded))
			{
				throw new RuntimeException('Unable to read the environment file.');
			}

			$lines = $loaded;
		}

		$remaining = $values;

		foreach ($lines as $index => $line)
		{
			$parsed = self::ParseAssignment($line);

			if ($parsed === null || !array_key_exists($parsed['name'], $values))
			{
				continue;
			}

			$lines[$index] = $parsed['prefix'] . $parsed['name'] . '=' . self::EncodeValue((string)$values[$parsed['name']]);
			unset($remaining[$parsed['name']]);
		}

		if ($remaining !== [])
		{
			if ($lines !== [] && trim((string)end($lines)) !== '')
			{
				$lines[] = '';
			}

			foreach ($remaining as $name => $value)
			{
				if (preg_match('/^[A-Z][A-Z0-9_]*$/', (string)$name) !== 1)
				{
					throw new RuntimeException('Invalid environment variable name.');
				}

				$lines[] = (string)$name . '=' . self::EncodeValue((string)$value);
			}
		}

		$content = implode("\n", $lines) . "\n";
		$directory = dirname($this->_path);

		if (is_writable($directory))
		{
			$this->WriteAtomically($content, $directory);
			return;
		}

		$this->WriteLockedInPlace($content);
	}

	/**
	 * @brief Replaces the environment file atomically when its directory is writable.
	 * @param string $content Complete file content.
	 * @param string $directory Parent directory.
	 */
	private function WriteAtomically(string $content, string $directory): void
	{
		$tempPath = tempnam($directory, '.pulse-env-');

		if ($tempPath === false)
		{
			throw new RuntimeException('Unable to create a temporary environment file.');
		}

		try
		{
			if (file_put_contents($tempPath, $content, LOCK_EX) === false)
			{
				throw new RuntimeException('Unable to write the temporary environment file.');
			}

			if (is_file($this->_path))
			{
				$permissions = fileperms($this->_path);

				if (is_int($permissions))
				{
					@chmod($tempPath, $permissions & 0777);
				}
			}
			else
			{
				@chmod($tempPath, 0600);
			}

			if (!@rename($tempPath, $this->_path))
			{
				throw new RuntimeException('Unable to replace the environment file atomically.');
			}
		}
		finally
		{
			if (is_file($tempPath))
			{
				@unlink($tempPath);
			}
		}
	}

	/**
	 * @brief Rewrites an existing .env under an exclusive file lock when directory replacement is unavailable.
	 * @param string $content Complete file content.
	 */
	private function WriteLockedInPlace(string $content): void
	{
		$handle = @fopen($this->_path, 'c+');

		if ($handle === false)
		{
			throw new RuntimeException('Unable to open the environment file for writing.');
		}

		try
		{
			if (!flock($handle, LOCK_EX))
			{
				throw new RuntimeException('Unable to lock the environment file for writing.');
			}

			if (!ftruncate($handle, 0) || fseek($handle, 0) !== 0)
			{
				throw new RuntimeException('Unable to prepare the environment file for writing.');
			}

			$remaining = $content;

			while ($remaining !== '')
			{
				$written = fwrite($handle, $remaining);

				if ($written === false || $written === 0)
				{
					throw new RuntimeException('Unable to write the environment file.');
				}

				$remaining = substr($remaining, $written);
			}

			fflush($handle);
		}
		finally
		{
			@flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/**
	 * @brief Parses one dotenv assignment.
	 * @param string $line Raw line.
	 * @return array{name:string,value:string,prefix:string}|null
	 */
	private static function ParseAssignment(string $line): ?array
	{
		if (preg_match('/^(\s*(?:export\s+)?)?([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches) !== 1)
		{
			return null;
		}

		return [
			'name' => (string)$matches[2],
			'value' => self::DecodeValue(trim((string)$matches[3])),
			'prefix' => (string)($matches[1] ?? ''),
		];
	}

	/**
	 * @brief Decodes the quoting emitted by this editor and common dotenv files.
	 * @param string $value Raw value portion.
	 * @return string
	 */
	private static function DecodeValue(string $value): string
	{
		if (strlen($value) < 2)
		{
			return $value;
		}

		$first = $value[0];
		$last = $value[strlen($value) - 1];

		if ($first === "'" && $last === "'")
		{
			return substr($value, 1, -1);
		}

		if ($first === '"' && $last === '"')
		{
			$decoded = substr($value, 1, -1);
			$decoded = str_replace('\\"', '"', $decoded);
			return str_replace('\\\\', '\\', $decoded);
		}

		return $value;
	}

	/**
	 * @brief Encodes a value in a parser-safe double-quoted representation when needed.
	 * @param string $value Plain value.
	 * @return string
	 */
	private static function EncodeValue(string $value): string
	{
		if ($value === '')
		{
			return '';
		}

		if (preg_match('/^[A-Za-z0-9_\.\/:,@%+\-]+$/', $value) === 1)
		{
			return $value;
		}

		$escaped = str_replace('\\', '\\\\', $value);
		$escaped = str_replace('"', '\\"', $escaped);
		return '"' . $escaped . '"';
	}
}
