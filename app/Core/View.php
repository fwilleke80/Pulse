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

	/**
	 * @brief Constructs the view service.
	 * @param string $viewsPath Absolute path to the views directory.
	 */
	public function __construct(string $viewsPath)
	{
		$this->_viewsPath = rtrim($viewsPath, '/');
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

		extract($data, EXTR_SKIP);

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