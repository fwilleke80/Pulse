<?php

/**
 * @file location-map.php
 * @brief On-demand interactive map embedded in an authenticated recipient portal.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $locations */
/** @var string $locationMapTileUrl */
/** @var string $openStreetMapUrl */

$mapPoints = [];

foreach ($locations as $index => $location)
{
	$latitude = (float)$location['latitude'];
	$longitude = (float)$location['longitude'];
	$label = trim((string)($location['address_label'] ?? ''));
	$label = $label !== '' ? $label : __('portal.locations.coordinates', [
		'latitude' => number_format($latitude, 5, '.', ''),
		'longitude' => number_format($longitude, 5, '.', ''),
	]);

	$mapPoints[] = [
		'number' => $index + 1,
		'latitude' => $latitude,
		'longitude' => $longitude,
		'accuracy_meters' => (int)ceil((float)$location['accuracy_meters']),
		'accuracy_label' => __('portal.location_map.accuracy', [
			'meters' => (int)ceil((float)$location['accuracy_meters']),
		]),
		'label' => $label,
		'timestamp' => format_datetime((string)$location['checked_in_at']),
		'openstreetmap_url' => openstreetmap_location_url($openStreetMapUrl, $latitude, $longitude),
	];
}

$mapJson = json_encode($mapPoints, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>

<p id="portal-map-instructions" class="form-hint portal-map-instructions"><?= e__('portal.location_map.instructions') ?></p>

<div class="portal-interactive-map-shell">
	<div
		class="portal-interactive-map"
		data-interactive-location-map
		data-map-points="<?= e($mapJson) ?>"
		data-map-tile-url="<?= e($locationMapTileUrl) ?>"
		data-map-point-label="<?= e__('portal.location_map.point_label') ?>"
		tabindex="0"
		role="application"
		aria-label="<?= e__('portal.location_map.map_label') ?>"
		aria-describedby="portal-map-instructions"
	>
		<div class="portal-map-tile-layer" data-map-tile-layer aria-hidden="true"></div>
		<svg class="portal-map-overlay" data-map-overlay aria-hidden="true"></svg>
		<div class="portal-map-marker-layer" data-map-marker-layer></div>
	</div>

	<div class="portal-map-controls" aria-label="<?= e__('portal.location_map.controls_label') ?>">
		<button type="button" data-map-zoom-in aria-label="<?= e__('portal.location_map.zoom_in') ?>" title="<?= e__('portal.location_map.zoom_in') ?>">+</button>
		<button type="button" data-map-zoom-out aria-label="<?= e__('portal.location_map.zoom_out') ?>" title="<?= e__('portal.location_map.zoom_out') ?>">−</button>
		<button type="button" class="portal-map-fit-control" data-map-fit aria-label="<?= e__('portal.location_map.fit') ?>" title="<?= e__('portal.location_map.fit') ?>"><?= e__('portal.location_map.fit_short') ?></button>
	</div>

	<section class="portal-map-details" data-map-details aria-live="polite" hidden>
		<button type="button" class="portal-map-details-close" data-map-details-close aria-label="<?= e__('portal.location_map.details_close') ?>">×</button>
		<strong data-map-details-heading></strong>
		<span data-map-details-timestamp></span>
		<span data-map-details-accuracy></span>
		<a data-map-details-link href="#" target="_blank" rel="noopener noreferrer"><?= e__('portal.location_map.open_point') ?></a>
	</section>

	<a class="portal-map-attribution" href="<?= e(rtrim($openStreetMapUrl, '/')) ?>/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a>
</div>

<p class="form-hint portal-map-privacy-note"><?= e__('portal.location_map.privacy_notice') ?></p>
<p class="form-hint"><?= e__('portal.location_map.path_notice') ?></p>
