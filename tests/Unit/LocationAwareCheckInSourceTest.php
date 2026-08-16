<?php

/**
 * @file LocationAwareCheckInSourceTest.php
 * @brief Source regressions for optional check-in locations and immutable portal trails.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocationAwareCheckInSourceTest extends TestCase
{
	/** @brief Keeps recording and portal publication disabled unless explicitly enabled. */
	public function testLocationSettingsAreOptIn(): void
	{
		$root = dirname(__DIR__, 2);
		$migration = (string)file_get_contents($root . '/database/migrations/003_check_in_locations.sql');
		$repository = (string)file_get_contents($root . '/app/Repositories/MonitorRepository.php');

		self::assertStringContainsString('location_check_in_enabled TINYINT(1) NOT NULL DEFAULT 0', $migration);
		self::assertStringContainsString('portal_location_sharing_enabled TINYINT(1) NOT NULL DEFAULT 0', $migration);
		self::assertStringContainsString('$recordLocation && $shareWithRecipients', $repository);
	}

	/** @brief Stores location only for enabled monitors and sources every check-in path from validated input. */
	public function testEveryCheckInPathUsesValidatedOneShotLocation(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/MonitorExecutionService.php');
		$headers = (string)file_get_contents($root . '/app/Core/SecurityHeaders.php');

		self::assertStringContainsString("!empty(\$monitor['location_check_in_enabled'])", $service);
		self::assertStringContainsString('InsertCheckInLocation(', $service);
		self::assertStringContainsString('geolocation=(self)', $headers);
		self::assertStringContainsString('$geocodeOrigin', $headers);
		self::assertStringContainsString('$tileOrigin', $headers);

		foreach (['MonitorController.php', 'QuickCheckInController.php', 'SecurityController.php', 'AuthController.php'] as $controller)
		{
			$source = (string)file_get_contents($root . '/app/Controllers/' . $controller);
			self::assertStringContainsString('CheckInLocation::FromRequest($this->_request)', $source, $controller);
		}
	}

	/** @brief Snapshots a bounded trail at release time and exposes it only in authenticated portal access. */
	public function testPortalTrailIsBoundedImmutableAndOnDemand(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/EscalationService.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/RecipientPortalController.php');
		$view = (string)file_get_contents($root . '/app/Views/portal/access.php');
		$javascript = (string)file_get_contents($root . '/public/assets/app.js');

		self::assertStringContainsString('SnapshotRecipientLocations($connection, $releaseId', $service);
		self::assertStringContainsString('max(1, min(20', $service);
		self::assertStringContainsString("'locations' => \$this->_portalService->LocationsForDelivery", $controller);
		self::assertStringNotContainsString('LocationsForDelivery', (string)file_get_contents($root . '/app/Views/portal/index.php'));
		self::assertStringContainsString('data-location-map-load', $view);
		self::assertStringContainsString("loadButton.addEventListener('click'", $javascript);
	}
}
