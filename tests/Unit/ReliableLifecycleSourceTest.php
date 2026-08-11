<?php

/**
 * @file ReliableLifecycleSourceTest.php
 * @brief Guards the global check-in and explicit pause/resume integration.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ReliableLifecycleSourceTest extends TestCase
{
	public function testGlobalCheckInDoesNotRequireAMonitorId(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/MonitorController.php');
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');

		self::assertStringContainsString('CheckInAllActiveForUser', $controller);
		self::assertStringNotContainsString("PostInt('id');\n\n\t\tif (\$this->_monitorRepository->ConfirmDueForUser", $controller);
		self::assertStringContainsString('/monitors/check-in', $dashboard);
		self::assertStringNotContainsString('name="id"', $this->GlobalCheckInForm($dashboard));
	}

	public function testPauseAndResumeAreRuntimeActions(): void
	{
		$routes = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
		$editor = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');

		self::assertStringContainsString("Post('/monitors/pause'", $routes);
		self::assertStringContainsString("Post('/monitors/resume'", $routes);
		self::assertStringNotContainsString('name="is_paused"', $editor);
	}

	/** @brief Extracts the dashboard global check-in form. @param string $source Template source. @return string */
	private function GlobalCheckInForm(string $source): string
	{
		$start = strpos($source, 'action="<?= e($base_url) ?>/monitors/check-in"');

		if ($start === false)
		{
			return '';
		}

		$end = strpos($source, '</form>', $start);
		return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
	}
}
