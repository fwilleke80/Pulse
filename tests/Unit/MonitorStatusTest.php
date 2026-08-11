<?php

/**
 * @file MonitorStatusTest.php
 * @brief Tests the user-facing monitor status terminology.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MonitorStatusTest extends TestCase
{
	public function testPausedTakesPrecedence(): void
	{
		self::assertSame('paused', monitor_status([
			'is_paused' => 1,
			'latest_cycle_status' => 'escalated',
		]));
	}

	public function testEscalatedCycleIsReported(): void
	{
		self::assertSame('escalated', monitor_status([
			'is_paused' => 0,
			'latest_cycle_status' => 'escalated',
		]));
	}

	public function testFutureDueDateMeansCheckedIn(): void
	{
		self::assertSame('checked-in', monitor_status([
			'is_paused' => 0,
			'next_check_due_at' => gmdate('Y-m-d H:i:s', time() + 3600),
		]));
	}

	public function testDueAndOverdueWindowsAreDistinguished(): void
	{
		$base = [
			'is_paused' => 0,
			'response_window_days' => 1,
			'reminder_interval_days' => 1,
			'max_reminders' => 2,
		];

		self::assertSame('awaiting', monitor_status($base + [
			'next_check_due_at' => gmdate('Y-m-d H:i:s', time() - 3600),
		]));
		self::assertSame('overdue', monitor_status($base + [
			'next_check_due_at' => gmdate('Y-m-d H:i:s', time() - (4 * 86400)),
		]));
	}
}
