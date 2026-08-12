<?php

/**
 * @file MonitorStateMachine.php
 * @brief Defines legal persisted check-cycle transitions.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use LogicException;

/**
 * @brief Validates every transition in a monitor check cycle.
 */
final class MonitorStateMachine
{
	public const SCHEDULED = 'scheduled';
	public const AWAITING = 'awaiting';
	public const SAFETY_PENDING = 'safety_pending';
	public const OVERDUE = 'overdue';
	public const ESCALATED = 'escalated';
	public const CONFIRMED = 'confirmed';
	public const CANCELLED = 'cancelled';

	/**
	 * @brief Returns whether a persisted cycle may move between two states.
	 * @param string $from Current state.
	 * @param string $to Requested state.
	 * @return bool
	 */
	public function CanTransition(string $from, string $to): bool
	{
		$transitions = [
			self::SCHEDULED => [self::AWAITING, self::CONFIRMED, self::CANCELLED],
			self::AWAITING => [self::SAFETY_PENDING, self::OVERDUE, self::CONFIRMED, self::CANCELLED],
			self::SAFETY_PENDING => [self::OVERDUE, self::CONFIRMED, self::CANCELLED],
			self::OVERDUE => [self::ESCALATED, self::CONFIRMED, self::CANCELLED],
			self::ESCALATED => [self::CONFIRMED, self::CANCELLED],
			self::CONFIRMED => [],
			self::CANCELLED => [],
		];

		return in_array($to, $transitions[$from] ?? [], true);
	}

	/**
	 * @brief Rejects an illegal persisted cycle transition.
	 * @param string $from Current state.
	 * @param string $to Requested state.
	 */
	public function AssertTransition(string $from, string $to): void
	{
		if (!$this->CanTransition($from, $to))
		{
			throw new LogicException(sprintf('Illegal monitor cycle transition: %s -> %s.', $from, $to));
		}
	}

	/**
	 * @brief Returns states representing a currently open cycle.
	 * @return array<int, string>
	 */
	public static function OpenStates(): array
	{
		return [self::SCHEDULED, self::AWAITING, self::SAFETY_PENDING, self::OVERDUE, self::ESCALATED];
	}
}
