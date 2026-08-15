<?php

/**
 * @file SafetyAuditSourceTest.php
 * @brief Source-level regression checks for safety audit tooling and localization.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SafetyAuditSourceTest extends TestCase
{
	/** @brief Ensures debug timeout testing uses the real safety deadline rather than forcing Overdue directly. */
	public function testSafetyTimeoutDebugActionOnlyMovesTheDeadline(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/EscalationService.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$routes = (string)file_get_contents($root . '/public/index.php');
		$actions = (string)file_get_contents($root . '/app/Views/monitors/partials/actions.php');

		self::assertStringContainsString('ExpireDebugSafetyWindowForUser', $service);
		self::assertStringContainsString('SET safety_gate_deadline_at = :deadline', $service);
		self::assertStringNotContainsString("monitor.debug_safety_window_expired');\n\t\t\t\t\$this->_stateMachine", $service);
		self::assertStringContainsString('ExpireSafetyContactWindow', $controller);
		self::assertStringContainsString("Post('/monitors/expire-safety-contact-window'", $routes);
		self::assertStringContainsString('/monitors/expire-safety-contact-window', $actions);
	}

	/** @brief Ensures inactive safety links can still recover their snapshotted or selected language. */
	public function testInactiveSafetyLinksRestoreLanguageContext(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/EscalationService.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/SafetyController.php');

		self::assertStringContainsString('FindSafetyLanguageMetadata', $service);
		self::assertStringContainsString('UseUnavailableSafetyLanguage', $controller);
		self::assertStringContainsString("QueryString('lang', 10)", $controller);
	}
}
