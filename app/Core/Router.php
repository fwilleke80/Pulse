<?php

declare(strict_types=1);

namespace Pulse\Core;

use Closure;

/**
 * @brief Very small router for exact GET and POST route matching.
 */
class Router
{
	/** @var array<string, callable> */
	private array $_getRoutes = [];

	/** @var array<string, callable> */
	private array $_postRoutes = [];

	/**
	 * @brief Registers a GET route.
	 * @param string $path Route path.
	 * @param callable $handler Route handler.
	 */
	public function Get(string $path, callable $handler): void
	{
		$this->_getRoutes[$path] = $handler;
	}

	/**
	 * @brief Registers a POST route.
	 * @param string $path Route path.
	 * @param callable $handler Route handler.
	 */
	public function Post(string $path, callable $handler): void
	{
		$this->_postRoutes[$path] = $handler;
	}

	/**
	 * @brief Dispatches the request to the matching route handler.
	 * @param string $method HTTP request method.
	 * @param string $path Request path.
	 * @return mixed
	 * @throws NotFoundException
	 */
	public function Dispatch(string $method, string $path): mixed
	{
		$routes = match (strtoupper($method))
		{
			'GET' => $this->_getRoutes,
			'POST' => $this->_postRoutes,
			default => [],
		};

		if (!isset($routes[$path]))
		{
			throw new NotFoundException('Route not found: ' . $method . ' ' . $path);
		}

		return ($routes[$path])();
	}
}