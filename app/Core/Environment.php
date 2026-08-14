<?php

/**
 * @file Environment.php
 * @brief Loads Pulse configuration values from the process environment and an optional local .env file.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Read-only environment configuration provider.
 */
final class Environment
{
	/** @var array<string, string> */
	private static array $_fileValues = [];

	private static bool $_loaded = false;

	/**
	 * @brief Loads values from a dotenv-style file without overwriting process environment variables.
	 * @param string $path Absolute path to the optional .env file.
	 */
	public static function Load(string $path): void
	{
		if (self::$_loaded)
		{
			return;
		}

		self::$_loaded = true;

		if (!is_file($path) || !is_readable($path))
		{
			return;
		}

		$lines = file($path, FILE_IGNORE_NEW_LINES);

		if (!is_array($lines))
		{
			throw new RuntimeException('Unable to read the environment file.');
		}

		foreach ($lines as $line)
		{
			$line = trim($line);

			if ($line === '' || str_starts_with($line, '#'))
			{
				continue;
			}

			if (str_starts_with($line, 'export '))
			{
				$line = trim(substr($line, 7));
			}

			$separator = strpos($line, '=');

			if ($separator === false)
			{
				continue;
			}

			$name = trim(substr($line, 0, $separator));
			$value = trim(substr($line, $separator + 1));

			if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name))
			{
				continue;
			}

			if (strlen($value) >= 2)
			{
				$first = $value[0];
				$last = $value[strlen($value) - 1];

				if ($first === "'" && $last === "'")
				{
					$value = substr($value, 1, -1);
				}
				else if ($first === '"' && $last === '"')
				{
					$value = substr($value, 1, -1);
					$value = str_replace('\\"', '"', $value);
					$value = str_replace('\\\\', '\\', $value);
				}
			}

			self::$_fileValues[$name] = $value;
		}
	}

	/**
	 * @brief Returns a string environment value.
	 * @param string $name Environment variable name.
	 * @param string $default Default value.
	 * @return string
	 */
	public static function Get(string $name, string $default = ''): string
	{
		$value = getenv($name);

		if (is_string($value))
		{
			return $value;
		}

		return self::$_fileValues[$name] ?? $default;
	}

	/**
	 * @brief Returns a boolean environment value.
	 * @param string $name Environment variable name.
	 * @param bool $default Default value.
	 * @return bool
	 */
	public static function GetBool(string $name, bool $default = false): bool
	{
		$value = self::Get($name, $default ? 'true' : 'false');
		$parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

		return $parsed ?? $default;
	}

	/**
	 * @brief Returns an integer environment value.
	 * @param string $name Environment variable name.
	 * @param int $default Default value.
	 * @param int|null $minimum Optional minimum accepted value.
	 * @param int|null $maximum Optional maximum accepted value.
	 * @return int
	 */
	public static function GetInt(
		string $name,
		int $default,
		?int $minimum = null,
		?int $maximum = null
	): int
	{
		$value = filter_var(self::Get($name, (string)$default), FILTER_VALIDATE_INT);
		$result = $value === false ? $default : (int)$value;

		if ($minimum !== null && $result < $minimum)
		{
			return $minimum;
		}

		if ($maximum !== null && $result > $maximum)
		{
			return $maximum;
		}

		return $result;
	}

	/**
	 * @brief Returns a comma-separated environment value as a normalized list.
	 * @param string $name Environment variable name.
	 * @param array<int, string> $default Default values.
	 * @return array<int, string>
	 */
	public static function GetList(string $name, array $default = []): array
	{
		$value = self::Get($name);

		if ($value === '')
		{
			return $default;
		}

		$items = array_map('trim', explode(',', $value));
		$items = array_filter($items, static fn (string $item): bool => $item !== '');

		return array_values(array_unique($items));
	}

	/**
	 * @brief Returns a comma-separated list of non-negative integers.
	 * @param string $name Environment variable name.
	 * @param array<int> $default Default values.
	 * @return array<int>
	 */
	public static function GetIntList(string $name, array $default = []): array
	{
		$values = self::GetList($name, array_map('strval', $default));
		$result = [];

		foreach ($values as $value)
		{
			if (preg_match('/^\d+$/', $value) !== 1)
			{
				continue;
			}

			$result[] = (int)$value;
		}

		return $result !== [] ? $result : $default;
	}
}
