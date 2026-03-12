<?php

declare(strict_types=1);

namespace Pulse\Core;

use Closure;
use RuntimeException;
use Pulse\Core\NotFoundException;

/**
 * @brief Minimal HTTP router.
 */
class Router
{
	/** @var array<string, array<string, Closure>> */
	private array $_routes;

	public function __construct()
	{
		$this->_routes = [];
	}

	/**
	 * @brief Registers a GET route.
	 * @param string $path URL path.
	 * @param Closure $handler Route handler.
	 */
	public function Get(string $path, Closure $handler): void
	{
		$this->AddRoute('GET', $path, $handler);
	}

	/**
	 * @brief Registers a POST route.
	 * @param string $path URL path.
	 * @param Closure $handler Route handler.
	 */
	public function Post(string $path, Closure $handler): void
	{
		$this->AddRoute('POST', $path, $handler);
	}

	/**
	 * @brief Dispatches the current request.
	 * @param string $method HTTP method.
	 * @param string $path Request path.
	 * @return mixed Route result.
	 */
	public function Dispatch(string $method, string $path): mixed
	{
		$normalizedMethod = strtoupper($method);
		$normalizedPath = $this->NormalizePath($path);

		if (!isset($this->_routes[$normalizedMethod][$normalizedPath]))
		{
			throw new NotFoundException('Route not found.');
		}

		return ($this->_routes[$normalizedMethod][$normalizedPath])();
	}

	/**
	 * @brief Adds a route entry.
	 * @param string $method HTTP method.
	 * @param string $path URL path.
	 * @param Closure $handler Route handler.
	 */
	private function AddRoute(string $method, string $path, Closure $handler): void
	{
		$normalizedMethod = strtoupper($method);
		$normalizedPath = $this->NormalizePath($path);
		$this->_routes[$normalizedMethod][$normalizedPath] = $handler;
	}

	/**
	 * @brief Normalizes a URL path.
	 * @param string $path Raw path.
	 * @return string Normalized path.
	 */
	private function NormalizePath(string $path): string
	{
		$trimmedPath = trim($path);

		if ($trimmedPath === '' || $trimmedPath === '/')
		{
			return '/';
		}

		return '/' . trim($trimmedPath, '/');
	}
}