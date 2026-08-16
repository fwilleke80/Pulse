<?php

/**
 * @file activity.php
 * @brief Paginated complete check-in lifecycle history.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $activity */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $base_url */
/** @var string $openStreetMapUrl */

$activityTranslationKeys = [
	'monitor.checked_in' => 'dashboard.activity.checked_in',
	'monitor.awaiting' => 'dashboard.activity.awaiting',
	'monitor.safety_requested' => 'dashboard.activity.safety_requested',
	'monitor.safety_expired' => 'dashboard.activity.safety_expired',
	'monitor.safety_confirmed' => 'dashboard.activity.safety_confirmed',
	'monitor.overdue' => 'dashboard.activity.overdue',
	'monitor.escalated' => 'dashboard.activity.escalated',
	'monitor.reset_reactivated' => 'dashboard.activity.reset_reactivated',
	'monitor.archived' => 'dashboard.activity.archived',
	'monitor.paused' => 'dashboard.activity.paused',
	'monitor.resumed' => 'dashboard.activity.resumed',
	'monitor.forced_due' => 'dashboard.activity.forced_due',
	'mail.due_notice_sent' => 'dashboard.activity.due_notice_sent',
	'mail.reminder_sent' => 'dashboard.activity.reminder_sent',
	'mail.safety_invitation_sent' => 'dashboard.activity.safety_invitation_sent',
	'mail.safety_reminder_sent' => 'dashboard.activity.safety_reminder_sent',
	'mail.recipient_sent' => 'dashboard.activity.recipient_sent',
	'mail.recipient_failed' => 'dashboard.activity.recipient_failed',
];

ob_start();
?>

<div class="section-title-row">
	<div>
		<h1><?= e__('activity.heading') ?></h1>
		<p><?= e__('activity.summary', ['count' => $total]) ?></p>
	</div>
	<a href="<?= e($base_url) ?>/"><?= e__('activity.back') ?></a>
</div>

<?php if ($activity === []): ?>
	<p><?= e__('dashboard.activity.none') ?></p>
<?php else: ?>
	<ol class="activity-list activity-list-complete" start="<?= (($page - 1) * 50) + 1 ?>">
		<?php foreach ($activity as $entry): ?>
			<?php
			$key = $activityTranslationKeys[(string)$entry['event_type']] ?? null;
			$monitorName = !empty($entry['monitor_name'])
				? (string)$entry['monitor_name']
				: __('dashboard.activity.unknown_monitor');
			?>
			<?php if (is_string($key)): ?>
				<li>
					<?php if ((string)$entry['event_type'] === 'monitor.checked_in' && $entry['location_latitude'] !== null): ?>
						<?php
						$latitude = (float)$entry['location_latitude'];
						$longitude = (float)$entry['location_longitude'];
						$locationLabel = trim((string)($entry['location_address_label'] ?? ''));
						$locationLabel = $locationLabel !== '' ? $locationLabel : __('location.coordinates', [
							'latitude' => number_format($latitude, 5, '.', ''),
							'longitude' => number_format($longitude, 5, '.', ''),
						]);
						?>
						<span><?= e__('dashboard.activity.checked_in_from', ['name' => $monitorName]) ?> <a href="<?= e(openstreetmap_location_url($openStreetMapUrl, $latitude, $longitude)) ?>" target="_blank" rel="noopener noreferrer"><?= e($locationLabel) ?></a>.</span>
					<?php else: ?>
						<span><?= e__($key, ['name' => $monitorName]) ?></span>
					<?php endif; ?>
					<time datetime="<?= e((string)$entry['created_at']) ?>"><?= e(format_datetime((string)$entry['created_at'])) ?></time>
				</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ol>

	<?php if ($totalPages > 1): ?>
		<nav class="pagination" aria-label="<?= e__('activity.pagination.label') ?>">
			<?php if ($page > 1): ?>
				<a class="button-link" href="<?= e($base_url) ?>/activity?page=<?= $page - 1 ?>"><?= e__('activity.pagination.previous') ?></a>
			<?php endif; ?>
			<span><?= e__('activity.pagination.page', ['page' => $page, 'pages' => $totalPages]) ?></span>
			<?php if ($page < $totalPages): ?>
				<a class="button-link" href="<?= e($base_url) ?>/activity?page=<?= $page + 1 ?>"><?= e__('activity.pagination.next') ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = __('activity.title');
require __DIR__ . '/../layouts/main.php';
