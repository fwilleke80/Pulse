<?php

/**
 * @file UiSimplificationAndRecoverySourceTest.php
 * @brief Source regressions for Pulse 1.2.7 UI simplification and recovery guidance.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UiSimplificationAndRecoverySourceTest extends TestCase
{
	/** @brief Ensures field help is visibly subordinate while remaining persistently available. */
	public function testFieldHelpUsesSharedSubduedStyles(): void
	{
		$styles = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');

		self::assertStringContainsString(".form-hint,\n.field-help,\nlabel > small,", $styles);
		self::assertStringContainsString('.field-grid input + small,', $styles);
		self::assertStringContainsString('font-size: 0.82rem;', $styles);
		self::assertStringContainsString(".checkbox-option > span,\n.checkbox-option strong", $styles);
	}

	/** @brief Ensures the primary check-in is distinctive and Dashboard activity stays compact. */
	public function testPrimaryCheckInAndDashboardAreCompact(): void
	{
		$root = dirname(__DIR__, 2);
		$dashboard = (string)file_get_contents($root . '/app/Views/home/dashboard.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/HomeController.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('check-in-button-icon', $dashboard);
		self::assertStringContainsString('border-radius: 999px;', $styles);
		self::assertStringContainsString("FindRecentActivityForUser((int)\$user['id'], 5)", $controller);
		self::assertStringNotContainsString('dashboard.monitors.hint', $dashboard);
	}

	/** @brief Ensures monitor views are tabs and commands remain a separate action area. */
	public function testMonitorViewTabsAreSeparatedFromCommands(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/index.php');

		self::assertStringContainsString('class="monitor-view-tabs"', $view);
		self::assertStringContainsString('class="monitor-index-command-bar"', $view);
		self::assertStringContainsString('class="btn-primary btn-check-in"', $view);
		self::assertStringNotContainsString('monitors.index.check_in_hint', $view);
		self::assertStringNotContainsString('monitor-view-toggle', $view);
	}

	/** @brief Ensures schedule help and editor layout requests remain represented. */
	public function testMonitorEditorUsesRequestedHelpAndLayouts(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		foreach (['check_interval_days_hint', 'response_window_days_hint', 'reminder_interval_days_hint', 'max_reminders_hint'] as $key)
		{
			self::assertStringContainsString('monitors.edit.' . $key, $view);
		}

		self::assertStringContainsString("'text_document_content', 'text_content', '', 9", $view);
		self::assertStringNotContainsString('monitors.escalation.authority.heading', $view);
		self::assertStringContainsString('.document-create-grid .monitor-document-card form > button', $styles);
		self::assertStringContainsString('margin: 1rem clamp(-1rem, -2vw, 0rem) 0;', $styles);
	}

	/** @brief Ensures recipient document assignments use responsive compact cards. */
	public function testRecipientDocumentsUseResponsiveCards(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/recipients/edit.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('recipient-document-content', $view);
		self::assertStringContainsString('grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr));', $styles);
	}

	/** @brief Ensures optional TOTP accounts receive actionable, non-bypass recovery guidance. */
	public function testRecoveryReadinessAppearsOnlyForEnabledTotp(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/profile/index.php');
		$english = (string)file_get_contents($root . '/app/Lang/en.php');

		self::assertStringContainsString('<?php if ($totpEnabled): ?>', $view);
		self::assertStringContainsString('recovery-readiness-card', $view);
		self::assertStringContainsString('$recoveryCodesRemaining > 3', $view);
		self::assertStringContainsString("'security.recovery.passkey.missing'", $english);
		self::assertStringContainsString('database and .env together', $english);
		self::assertStringContainsString('no email or security-question two-factor reset', $english);
	}
}
