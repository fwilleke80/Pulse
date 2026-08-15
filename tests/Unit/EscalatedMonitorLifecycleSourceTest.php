<?php

/**
 * @file EscalatedMonitorLifecycleSourceTest.php
 * @brief Source-level regression checks for escalated-monitor lifecycle and row menus.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EscalatedMonitorLifecycleSourceTest extends TestCase
{
	/** @brief Ensures archived state is persisted by a new migration and hidden by default. */
	public function testArchiveStateUsesExplicitPersistentColumns(): void
	{
		$root = dirname(__DIR__, 2);
		$schema = (string)file_get_contents($root . '/database/migrations/001_initial_schema.sql');
		$repository = (string)file_get_contents($root . '/app/Repositories/MonitorRepository.php');

		self::assertStringContainsString('is_archived', $schema);
		self::assertStringContainsString('archived_at', $schema);
		self::assertStringContainsString('AND is_archived = :is_archived', $repository);
	}

	/** @brief Ensures escalated monitors require an explicit lifecycle choice. */
	public function testEscalatedMonitorsAreExcludedFromNormalCheckIn(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/MonitorExecutionService.php');
		$dashboard = (string)file_get_contents($root . '/app/Views/home/dashboard.php');

		self::assertStringContainsString("active_cc.status = \\'escalated\\'", $service);
		self::assertStringContainsString('ResetAndReactivateMonitorForUser', $service);
		self::assertStringContainsString('ArchiveEscalatedMonitorForUser', $service);
		self::assertStringNotContainsString("['awaiting', 'safety-pending', 'overdue', 'escalated']", $dashboard);
	}

	/** @brief Ensures reset does not revoke already released recipient portals. */
	public function testResetLeavesRecipientPortalRecordsUntouched(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/MonitorExecutionService.php');
		$start = strpos($service, 'public function ResetAndReactivateMonitorForUser');
		$end = strpos($service, 'public function ArchiveEscalatedMonitorForUser');
		self::assertIsInt($start);
		self::assertIsInt($end);
		$method = substr($service, $start, $end - $start);

		self::assertStringNotContainsString('portal_revoked_at', $method);
		self::assertStringNotContainsString('recipient_portal_codes', $method);
		self::assertStringNotContainsString('recipient_release_deliveries', $method);
		self::assertStringContainsString('InsertScheduledCycle', $method);
	}

	/** @brief Ensures the new lifecycle choices are visible in lifecycle history. */
	public function testLifecycleChoicesAppearInActivityHistory(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/MonitorExecutionService.php');
		$activity = (string)file_get_contents($root . '/app/Views/home/activity.php');

		self::assertStringContainsString("\'monitor.reset_reactivated\'", $service);
		self::assertStringContainsString("\'monitor.archived\'", $service);
		self::assertStringContainsString("'monitor.reset_reactivated' => 'dashboard.activity.reset_reactivated'", $activity);
		self::assertStringContainsString("'monitor.archived' => 'dashboard.activity.archived'", $activity);
	}

	/** @brief Ensures monitor overview rows expose one compact action-menu trigger. */
	public function testMonitorRowsUseCompactActionMenu(): void
	{
		$root = dirname(__DIR__, 2);
		$actions = (string)file_get_contents($root . '/app/Views/monitors/partials/actions.php');

		self::assertStringContainsString('row-action-menu-toggle', $actions);
		self::assertStringContainsString('⋮', $actions);
		self::assertStringContainsString('monitors/reset-reactivate', $actions);
		self::assertStringContainsString('monitors/archive', $actions);
		self::assertStringContainsString('monitors/index.table.buttons.delete', $actions);
	}
}
