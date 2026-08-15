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

	public function testArchivedTakesPrecedence(): void
	{
		self::assertSame('archived', monitor_status([
			'is_archived' => 1,
			'is_paused' => 0,
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
			'latest_cycle_status' => 'scheduled',
			'next_check_due_at' => gmdate('Y-m-d H:i:s', time() + 3600),
		]));
	}

	public function testPersistedRuntimeStatesAreReported(): void
	{
		self::assertSame('awaiting', monitor_status(['is_paused' => 0, 'latest_cycle_status' => 'awaiting']));
		self::assertSame('safety-pending', monitor_status(['is_paused' => 0, 'latest_cycle_status' => 'safety_pending']));
		self::assertSame('overdue', monitor_status(['is_paused' => 0, 'latest_cycle_status' => 'overdue']));
		self::assertSame('escalated', monitor_status(['is_paused' => 0, 'latest_cycle_status' => 'escalated']));
	}

	public function testElapsedTimeAloneNeverPretendsRemindersWereSent(): void
	{
		self::assertSame('awaiting', monitor_status([
			'is_paused' => 0,
			'latest_cycle_status' => null,
			'next_check_due_at' => gmdate('Y-m-d H:i:s', time() - (30 * 86400)),
		]));
	}
}
