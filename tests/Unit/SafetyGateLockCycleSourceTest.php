<?php

/**
 * @file SafetyGateLockCycleSourceTest.php
 * @brief Regression guard for all fields consumed by the safety gate.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** @brief Detects missing monitor columns before the cron scheduler can encounter them. */
final class SafetyGateLockCycleSourceTest extends TestCase
{
	/** @brief Both postponement columns must be projected by LockCycle, not merely present elsewhere. */
	public function testLockCycleSelectsPostponementConfiguration(): void
	{
		$source = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/EscalationService.php');
		$start = strpos($source, 'private function LockCycle(');
		self::assertNotFalse($start);
		$end = strpos($source, 'private function FindSafetyContacts(', $start);
		self::assertNotFalse($end);
		$method = substr($source, $start, $end - $start);
		self::assertStringContainsString('m.safety_postponement_limit', $method);
		self::assertStringContainsString('m.consecutive_safety_postponements', $method);
	}
}
