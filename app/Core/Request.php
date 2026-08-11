<?php

/**
 * @file Request.php
 * @brief Immutable access to the current HTTP request.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Typed, immutable wrapper around HTTP request input.
 */
final class Request
{
	/** @var array<string, mixed> */
	private array $_query;

	/** @var array<string, mixed> */
	private array $_post;

	/** @var array<string, mixed> */
	private array $_files;

	/** @var array<string, mixed> */
	private array $_server;

	/**
	 * @brief Constructs a request from explicit input arrays.
	 * @param array<string, mixed> $query Query values.
	 * @param array<string, mixed> $post Form values.
	 * @param array<string, mixed> $files Uploaded files.
	 * @param array<string, mixed> $server Server values.
	 */
	public function __construct(array $query, array $post, array $files, array $server)
	{
		$this->_query = $query;
		$this->_post = $post;
		$this->_files = $files;
		$this->_server = $server;
	}

	/**
	 * @brief Creates a request from PHP globals.
	 * @return self
	 */
	public static function FromGlobals(): self
	{
		return new self($_GET, $_POST, $_FILES, $_SERVER);
	}

	/**
	 * @brief Returns the uppercase HTTP method.
	 * @return string
	 */
	public function Method(): string
	{
		return strtoupper((string)($this->_server['REQUEST_METHOD'] ?? 'GET'));
	}

	/**
	 * @brief Returns the normalized request path.
	 * @return string
	 */
	public function Path(): string
	{
		$uri = (string)($this->_server['REQUEST_URI'] ?? '/');
		$path = parse_url($uri, PHP_URL_PATH);

		return is_string($path) && $path !== '' ? $path : '/';
	}

	/**
	 * @brief Returns the safe local request target, including its query string.
	 * @return string
	 */
	public function Target(): string
	{
		$uri = (string)($this->_server['REQUEST_URI'] ?? '/');

		return SafeRedirect::Normalize($uri, $this->Path());
	}

	/**
	 * @brief Returns a trimmed POST string with a maximum length.
	 * @param string $name Input name.
	 * @param int $maximumLength Maximum returned byte length.
	 * @param bool $trim Whether surrounding whitespace is removed.
	 * @return string
	 */
	public function PostString(string $name, int $maximumLength = 65535, bool $trim = true): string
	{
		$value = $this->_post[$name] ?? '';

		if (!is_string($value) && !is_numeric($value))
		{
			return '';
		}

		$result = (string)$value;
		$result = $trim ? trim($result) : $result;

		return substr($result, 0, max(0, $maximumLength));
	}

	/**
	 * @brief Returns a POST integer.
	 * @param string $name Input name.
	 * @param int $default Default value.
	 * @return int
	 */
	public function PostInt(string $name, int $default = 0): int
	{
		$value = filter_var($this->_post[$name] ?? null, FILTER_VALIDATE_INT);

		return $value === false || $value === null ? $default : (int)$value;
	}

	/**
	 * @brief Returns whether a POST checkbox/value exists.
	 * @param string $name Input name.
	 * @return bool
	 */
	public function PostBool(string $name): bool
	{
		return array_key_exists($name, $this->_post);
	}

	/**
	 * @brief Returns a unique list of positive POST integers.
	 * @param string $name Input name.
	 * @return array<int>
	 */
	public function PostIntArray(string $name): array
	{
		$value = $this->_post[$name] ?? [];

		if (!is_array($value))
		{
			return [];
		}

		$items = array_map('intval', $value);
		$items = array_filter($items, static fn (int $item): bool => $item > 0);

		return array_values(array_unique($items));
	}

	/**
	 * @brief Returns a trimmed query string with a maximum length.
	 * @param string $name Input name.
	 * @param int $maximumLength Maximum returned byte length.
	 * @return string
	 */
	public function QueryString(string $name, int $maximumLength = 2048): string
	{
		$value = $this->_query[$name] ?? '';

		if (!is_string($value) && !is_numeric($value))
		{
			return '';
		}

		return substr(trim((string)$value), 0, max(0, $maximumLength));
	}

	/**
	 * @brief Returns a query integer.
	 * @param string $name Input name.
	 * @param int $default Default value.
	 * @return int
	 */
	public function QueryInt(string $name, int $default = 0): int
	{
		$value = filter_var($this->_query[$name] ?? null, FILTER_VALIDATE_INT);

		return $value === false || $value === null ? $default : (int)$value;
	}

	/**
	 * @brief Returns an uploaded-file entry.
	 * @param string $name Upload field name.
	 * @return array<string, mixed>|null
	 */
	public function UploadedFile(string $name): ?array
	{
		$value = $this->_files[$name] ?? null;

		return is_array($value) ? $value : null;
	}

	/**
	 * @brief Returns the direct client IP without trusting forwarding headers.
	 * @return string
	 */
	public function ClientIp(): string
	{
		$ip = (string)($this->_server['REMOTE_ADDR'] ?? 'unknown');

		return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
	}

	/**
	 * @brief Returns the request host without its port.
	 * @return string
	 */
	public function Host(): string
	{
		$host = strtolower((string)($this->_server['HTTP_HOST'] ?? ''));
		$host = preg_replace('/:\d+$/', '', $host) ?? '';

		return preg_match('/^[a-z0-9.-]+$/', $host) === 1 ? $host : '';
	}

	/**
	 * @brief Returns whether the direct request uses HTTPS.
	 * @return bool
	 */
	public function IsSecure(): bool
	{
		return strtolower((string)($this->_server['HTTPS'] ?? '')) === 'on'
			|| (string)($this->_server['SERVER_PORT'] ?? '') === '443';
	}
}
