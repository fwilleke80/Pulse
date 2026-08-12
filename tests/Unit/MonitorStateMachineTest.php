<?php

/**
 * @file MonitorStateMachineTest.php
 * @brief Tests legal and illegal check-cycle transitions.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Pulse\Services\MonitorStateMachine;

class MonitorStateMachineTest extends TestCase
{
	public function testNormalLifecycleTransitionsAreLegal(): void
	{
		$stateMachine = new MonitorStateMachine();

		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::SCHEDULED, MonitorStateMachine::AWAITING));
		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::AWAITING, MonitorStateMachine::OVERDUE));
		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::OVERDUE, MonitorStateMachine::ESCALATED));
		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::ESCALATED, MonitorStateMachine::CONFIRMED));
	}

	public function testSafetyGateCanEitherPostponeOrExpire(): void
	{
		$stateMachine = new MonitorStateMachine();

		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::AWAITING, MonitorStateMachine::SAFETY_PENDING));
		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::SAFETY_PENDING, MonitorStateMachine::CONFIRMED));
		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::SAFETY_PENDING, MonitorStateMachine::OVERDUE));
		self::assertFalse($stateMachine->CanTransition(MonitorStateMachine::SAFETY_PENDING, MonitorStateMachine::ESCALATED));
	}

	public function testEarlyGlobalCheckInIsLegal(): void
	{
		$stateMachine = new MonitorStateMachine();

		self::assertTrue($stateMachine->CanTransition(MonitorStateMachine::SCHEDULED, MonitorStateMachine::CONFIRMED));
	}

	public function testPauseMayCancelEveryOpenState(): void
	{
		$stateMachine = new MonitorStateMachine();

		foreach (MonitorStateMachine::OpenStates() as $state)
		{
			self::assertTrue($stateMachine->CanTransition($state, MonitorStateMachine::CANCELLED));
		}
	}

	public function testTerminalCycleCannotBeReopened(): void
	{
		$stateMachine = new MonitorStateMachine();
		$this->expectException(LogicException::class);
		$stateMachine->AssertTransition(MonitorStateMachine::CONFIRMED, MonitorStateMachine::AWAITING);
	}
}
