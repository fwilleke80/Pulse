<?php

/**
 * @file MonitorStatusFuturePlanTest.php
 * @brief Tests the conditional safety branch shown by the monitor status timeline.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Services\MonitorStatusService;
use ReflectionClass;

final class MonitorStatusFuturePlanTest extends TestCase
{
	/** @brief Keeps projected safety timing conditional until the gate has a delivery anchor. */
	public function testAwaitingSafetyPlanShowsBothBranchesWithoutInventedTimestamps(): void
	{
		$reflection = new ReflectionClass(MonitorStatusService::class);
		$service = $reflection->newInstanceWithoutConstructor();
		$method = $reflection->getMethod('FuturePlan');
		$plan = $method->invoke($service, [
			'is_archived' => 0,
			'is_paused' => 0,
			'cycle_status' => 'awaiting',
			'due_notice_sent_at' => '2026-10-06 08:00:00',
			'reminders_sent' => 0,
			'max_reminders' => 2,
			'reminder_interval_days' => 1,
			'response_deadline_at' => '2026-10-08 08:00:00',
			'escalation_policy_snapshot' => 'safety_contact',
			'safety_gate_started_at' => null,
			'safety_gate_deadline_at' => null,
			'safety_min_reminders_sent' => null,
			'safety_pending_request_count' => 0,
			'safety_response_window_days' => 2,
			'safety_reminder_interval_days' => 1,
			'safety_max_reminders' => 2,
		]);

		self::assertSame([
			'owner_reminder',
			'owner_reminder',
			'safety_start',
			'safety_postpone',
			'safety_reminder',
			'safety_reminder',
			'safety_expiry',
			'recipient_release',
		], array_column($plan, 'type'));
		self::assertSame('safety_confirmed', $plan[3]['condition']);
		self::assertSame(2, $plan[4]['relative_safety_days']);
		self::assertNull($plan[4]['at']);
		self::assertSame(3, $plan[5]['relative_safety_days']);
		self::assertSame(4, $plan[6]['relative_safety_days']);
		self::assertSame('safety_unconfirmed', $plan[7]['condition']);
	}

	/** @brief Uses persisted safety-gate timestamps once invitation delivery anchored the gate. */
	public function testStartedSafetyGateShowsExactRemainingTimes(): void
	{
		$reflection = new ReflectionClass(MonitorStatusService::class);
		$service = $reflection->newInstanceWithoutConstructor();
		$method = $reflection->getMethod('FuturePlan');
		$plan = $method->invoke($service, [
			'is_archived' => 0,
			'is_paused' => 0,
			'cycle_status' => 'safety_pending',
			'safety_gate_started_at' => '2026-10-10 09:00:00',
			'safety_gate_deadline_at' => '2026-10-14 09:00:00',
			'safety_min_reminders_sent' => 0,
			'safety_pending_request_count' => 2,
			'safety_response_window_days' => 2,
			'safety_reminder_interval_days' => 1,
			'safety_max_reminders' => 2,
		]);

		self::assertSame('safety_postpone', $plan[0]['type']);
		self::assertSame('2026-10-12 09:00:00', $plan[1]['at']);
		self::assertSame('2026-10-13 09:00:00', $plan[2]['at']);
		self::assertSame('2026-10-14 09:00:00', $plan[3]['at']);
		self::assertSame('2026-10-14 09:00:00', $plan[4]['at']);
		self::assertArrayNotHasKey('relative_safety_days', $plan[1]);
	}
}
