<?php

/**
 * @file CheckInLocation.php
 * @brief Validates optional browser geolocation submitted with a check-in.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Creates a bounded location value from untrusted request fields.
 */
final class CheckInLocation
{
	/**
	 * @brief Reads a complete valid location or returns null when it is absent or malformed.
	 * @param Request $request Current request.
	 * @return array{latitude: float, longitude: float, accuracy_meters: float, address_label: string|null}|null
	 */
	public static function FromRequest(Request $request): ?array
	{
		if ($request->PostString('location_available', 1) !== '1')
		{
			return null;
		}

		$latitude = filter_var($request->PostString('location_latitude', 32), FILTER_VALIDATE_FLOAT);
		$longitude = filter_var($request->PostString('location_longitude', 32), FILTER_VALIDATE_FLOAT);
		$accuracy = filter_var($request->PostString('location_accuracy', 32), FILTER_VALIDATE_FLOAT);

		if (
			$latitude === false
			|| $longitude === false
			|| $accuracy === false
			|| !is_finite((float)$latitude)
			|| !is_finite((float)$longitude)
			|| !is_finite((float)$accuracy)
			|| (float)$latitude < -90.0
			|| (float)$latitude > 90.0
			|| (float)$longitude < -180.0
			|| (float)$longitude > 180.0
			|| (float)$accuracy <= 0.0
			|| (float)$accuracy > 1000000.0
		)
		{
			return null;
		}

		$address = $request->PostString('location_address', 1000);
		$address = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $address) ?? '';
		$address = trim(preg_replace('/\s+/u', ' ', $address) ?? '');

		return [
			'latitude' => round((float)$latitude, 7),
			'longitude' => round((float)$longitude, 7),
			'accuracy_meters' => round((float)$accuracy, 2),
			'address_label' => $address !== '' ? $address : null,
		];
	}
}
