<?php

/**
 * @file CheckInLocationTest.php
 * @brief Tests strict validation of optional browser check-in positions.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\CheckInLocation;
use Pulse\Core\Request;

final class CheckInLocationTest extends TestCase
{
	/** @brief Accepts, rounds, and cleans a complete location payload. */
	public function testValidLocationIsNormalized(): void
	{
		$request = new Request([], [
			'location_available' => '1',
			'location_latitude' => '52.264123456',
			'location_longitude' => '10.526123456',
			'location_accuracy' => '12.345',
			'location_address' => " Waterloostraße 18,\nBraunschweig, Germany ",
		], [], []);

		self::assertSame([
			'latitude' => 52.2641235,
			'longitude' => 10.5261235,
			'accuracy_meters' => 12.35,
			'address_label' => 'Waterloostraße 18, Braunschweig, Germany',
		], CheckInLocation::FromRequest($request));
	}

	/** @brief Treats absent, disabled, incomplete, and out-of-range positions as unavailable. */
	public function testInvalidLocationIsIgnored(): void
	{
		self::assertNull(CheckInLocation::FromRequest(new Request([], [], [], [])));
		self::assertNull(CheckInLocation::FromRequest(new Request([], [
			'location_available' => '0',
			'location_latitude' => '52',
			'location_longitude' => '10',
			'location_accuracy' => '20',
		], [], [])));
		self::assertNull(CheckInLocation::FromRequest(new Request([], [
			'location_available' => '1',
			'location_latitude' => '91',
			'location_longitude' => '10',
			'location_accuracy' => '20',
		], [], [])));
	}
}
