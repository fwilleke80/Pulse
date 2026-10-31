<?php

/**
 * @file CronHealth.php
 * @brief Shared stale-cron classification used by operating surfaces.
 */

declare(strict_types=1);

namespace Pulse\Services;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** @brief Classifies a successful-cron timestamp using Pulse's 24-hour health threshold. */
final class CronHealth
{
	/** @brief Returns recent, stale, or never. @param string|null $lastRun UTC database timestamp. */
	public static function Status(?string $lastRun): string
	{
		if ($lastRun === null || trim($lastRun) === '')
		{
			return 'never';
		}
		try
		{
			$timestamp = new DateTimeImmutable($lastRun, new DateTimeZone('UTC'));
			return (time() - $timestamp->getTimestamp()) > 86400 ? 'stale' : 'recent';
		}
		catch (Throwable)
		{
			return 'never';
		}
	}
}
