<?php

/**
 * @file MonitorOperationalOverviewSourceTest.php
 * @brief Source regressions for monitor status visibility and recipient assignment UX.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MonitorOperationalOverviewSourceTest extends TestCase
{
	/** @brief Keeps the dashboard focused on the next actual lifecycle action. */
	public function testDashboardShowsDerivedNextAction(): void
	{
		$root = dirname(__DIR__, 2);
		$dashboard = (string)file_get_contents($root . '/app/Views/home/dashboard.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/HomeController.php');

		self::assertStringContainsString("monitors.index.table.next_action", $dashboard);
		self::assertStringContainsString('monitor_action_time_label($nextAction)', $dashboard);
		self::assertStringContainsString('monitor_action_label($nextAction)', $dashboard);
		self::assertStringContainsString('&amp;tab=status', $dashboard);
		self::assertStringContainsString('FindAllForUser', $controller);
	}

	/** @brief Keeps a read-only status tab with future plan and monitor-specific history. */
	public function testMonitorEditorHasOperationalStatusTab(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$service = (string)file_get_contents($root . '/app/Services/MonitorStatusService.php');

		self::assertStringContainsString("'status' => 'monitors.tabs.status'", $view);
		self::assertStringContainsString('data-tab-panel="status"', $view);
		self::assertStringContainsString('monitors.status.next.heading', $view);
		self::assertStringContainsString('monitors.status.history.heading', $view);
		self::assertStringContainsString('FindHistoryForMonitorForUser', $controller);
		self::assertStringContainsString("'type' => 'owner_reminder'", $service);
		self::assertStringContainsString("'type' => 'safety_reminder'", $service);
		self::assertStringContainsString('OwnerEscalationTime', $service);
	}

	/** @brief Keeps recipients in one scrollable checkbox-card assignment list. */
	public function testRecipientsUseUnifiedSortableAssignmentCards(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$script = (string)file_get_contents($root . '/public/assets/app.js');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');

		self::assertStringContainsString('data-recipient-assignment-list', $view);
		self::assertStringContainsString('data-recipient-assignment-checkbox', $view);
		self::assertStringContainsString('data-recipient-sort="assigned"', $view);
		self::assertStringContainsString('data-recipient-sort="name"', $view);
		self::assertStringContainsString('monitors/recipients/assignments', $view);
		self::assertStringNotContainsString('id="add_recipient_contact"', $view);
		self::assertStringContainsString('sortCards', $script);
		self::assertStringContainsString('data-initially-assigned', $view);
		self::assertStringContainsString('max-height: min(62vh, 42rem);', $styles);
		self::assertStringContainsString('UpdateRecipientAssignments', $controller);
		self::assertStringContainsString('FindContactIdsByMonitorId', $controller);
		self::assertStringContainsString('$retainedContactIds', $controller);
		self::assertStringContainsString('$newContactIds', $controller);
	}

	/** @brief Keeps visual separation between the contact name and email card grid. */
	public function testContactNameHasSpacingBeforeEmailCards(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/contacts/edit.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('class="contact-name-field"', $view);
		self::assertStringContainsString('.contact-name-field', $styles);
		self::assertStringContainsString('margin-bottom: 1.1rem;', $styles);
	}
}
