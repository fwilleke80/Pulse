<?php

/**
 * @file dashboard.php
 * @brief Dashboard page view.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $user */
/** @var int $contactCount */
/** @var int $monitorCount */
/** @var array<int, array<string, mixed>> $monitors */
/** @var bool $allowForceDue */
/** @var string $base_url */

ob_start();
?>

<h1><?= e__('dashboard.heading') ?></h1>
<p><?= e__('dashboard.message.1', ['name' => $user['display_name']]) ?></p>
<p><?= e__('dashboard.message.2') ?></p>
<div class="dashboard-stats">
	<a href="<?= e($base_url) ?>/contacts" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.contacts') ?></div>
		<div class="dashboard-stat-value"><?= (int)$contactCount ?></div>
	</a>
	<a href="<?= e($base_url) ?>/monitors" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.monitors') ?></div>
		<div class="dashboard-stat-value"><?= (int)$monitorCount ?></div>
	</a>
</div>

<?php $dueMonitors = array_values(array_filter($monitors, 'is_monitor_due')); ?>
<section class="dashboard-monitor-section">
	<h2><?= e__('dashboard.monitors.heading') ?></h2>
	<?php if ($dueMonitors === []): ?>
		<p class="status-ok"><?= e__('dashboard.monitors.none_due') ?></p>
	<?php else: ?>
		<div class="due-monitor-list">
			<?php foreach ($dueMonitors as $monitor): ?>
				<div class="due-monitor-card">
					<div>
						<strong><?= e((string)$monitor['name']) ?></strong><br>
						<small><?= e__('dashboard.monitors.due_since', ['date' => format_datetime(isset($monitor['next_check_due_at']) ? (string)$monitor['next_check_due_at'] : null)]) ?></small>
					</div>
					<form method="post" action="<?= e($base_url) ?>/monitors/check-in">
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
						<input type="hidden" name="redirect" value="/">
						<button type="submit" class="btn-primary"><?= e__('monitors.check_in.submit') ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php
$content = ob_get_clean();
$title = e__('dashboard.title');
require __DIR__ . '/../layouts/main.php';
