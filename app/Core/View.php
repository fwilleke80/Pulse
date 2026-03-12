<?php

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Very small PHP view renderer.
 */
class View
{
	private string $_viewsPath;

	/** @var array<string, mixed> */
	private array $_globals;

	/**
	 * @brief Constructs the view service.
	 * @param string $viewsPath Absolute path to the views directory.
	 */
	public function __construct(string $viewsPath)
	{
		$this->_viewsPath = rtrim($viewsPath, '/');
		$this->_globals = [];
	}

	/**
	 * @brief Sets global variables available in every view.
	 * @param array<string, mixed> $globals Global variables.
	 */
	public function SetGlobals(array $globals): void
	{
		$this->_globals = $globals;
	}

	/**
	 * @brief Renders a view and returns the generated HTML.
	 * @param string $view Relative view path without .php suffix.
	 * @param array<string, mixed> $data View data.
	 * @return string Rendered HTML.
	 */
	public function Render(string $view, array $data = []): string
	{
		$viewFile = $this->_viewsPath . '/' . str_replace('.', '/', $view) . '.php';

		if (!is_file($viewFile))
		{
			throw new RuntimeException('View not found: ' . $view);
		}

		$variables = array_merge($this->_globals, $data);
		extract($variables, EXTR_SKIP);

		ob_start();
		require $viewFile;
		$output = ob_get_clean();

		if ($output === false)
		{
			throw new RuntimeException('Failed to render view: ' . $view);
		}

		return $output;
	}
}