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
/** @var array<int, array<string, mixed>> $recentActivity */
/** @var bool $mailEnabled */
/** @var string $base_url */

$activeMonitors = array_values(array_filter(
	$monitors,
	static fn (array $monitor): bool => empty($monitor['is_paused'])
));
$attentionCount = count(array_filter(
	$activeMonitors,
	static fn (array $monitor): bool => in_array(monitor_status($monitor), ['awaiting', 'overdue', 'escalated'], true)
));
$activityTranslationKeys = [
	'monitor.checked_in' => 'dashboard.activity.checked_in',
	'monitor.awaiting' => 'dashboard.activity.awaiting',
	'monitor.overdue' => 'dashboard.activity.overdue',
	'monitor.escalated' => 'dashboard.activity.escalated',
	'monitor.paused' => 'dashboard.activity.paused',
	'monitor.resumed' => 'dashboard.activity.resumed',
	'monitor.forced_due' => 'dashboard.activity.forced_due',
	'mail.due_notice_sent' => 'dashboard.activity.due_notice_sent',
	'mail.reminder_sent' => 'dashboard.activity.reminder_sent',
];

ob_start();
?>

<h1><?= e__('dashboard.heading') ?></h1>
<p><?= e__('dashboard.message.1', ['name' => $user['display_name']]) ?></p>
<p><?= e__('dashboard.message.2') ?></p>

<?php if (!$mailEnabled): ?>
	<section class="dashboard-system-warning" role="alert">
		<div>
			<strong><?= e__('dashboard.notifications.disabled.heading') ?></strong>
			<p><?= e__('dashboard.notifications.disabled.message') ?></p>
		</div>
		<a href="<?= e($base_url) ?>/profile#notifications" class="button-link"><?= e__('dashboard.notifications.disabled.action') ?></a>
	</section>
<?php endif; ?>

<div class="dashboard-stats">
	<a href="<?= e($base_url) ?>/monitors" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.monitors') ?></div>
		<div class="dashboard-stat-value"><?= (int)$monitorCount ?></div>
	</a>
	<a href="<?= e($base_url) ?>/monitors" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.active') ?></div>
		<div class="dashboard-stat-value"><?= count($activeMonitors) ?></div>
	</a>
	<a href="<?= e($base_url) ?>/monitors" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.attention') ?></div>
		<div class="dashboard-stat-value"><?= $attentionCount ?></div>
	</a>
	<a href="<?= e($base_url) ?>/contacts" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.contacts') ?></div>
		<div class="dashboard-stat-value"><?= (int)$contactCount ?></div>
	</a>
</div>

<?php if ($activeMonitors !== []): ?>
	<section class="global-check-in-card">
		<div>
			<h2><?= e__('dashboard.check_in.heading') ?></h2>
			<p><?= e__('dashboard.check_in.message', ['count' => count($activeMonitors)]) ?></p>
		</div>
		<form method="post" action="<?= e($base_url) ?>/monitors/check-in">
			<?= csrf_field() ?>
			<input type="hidden" name="redirect" value="/">
			<button type="submit" class="btn-primary btn-check-in"><?= e__('monitors.check_in.submit') ?></button>
		</form>
	</section>
<?php else: ?>
	<div class="global-check-in-card global-check-in-card-empty">
		<div>
			<h2><?= e__('dashboard.check_in.heading') ?></h2>
			<p><?= e__('dashboard.check_in.none_active') ?></p>
		</div>
	</div>
<?php endif; ?>

<section class="dashboard-monitor-section">
	<div class="section-title-row">
		<div>
			<h2><?= e__('dashboard.monitors.heading') ?></h2>
			<p><?= e__('dashboard.monitors.hint') ?></p>
		</div>
		<a href="<?= e($base_url) ?>/monitors"><?= e__('dashboard.monitors.manage') ?></a>
	</div>
	<?php if ($monitors === []): ?>
		<p><?= e__('dashboard.monitors.none') ?></p>
	<?php else: ?>
		<div class="dashboard-monitor-list">
			<?php foreach ($monitors as $monitor): ?>
				<?php
				$monitorStatus = monitor_status($monitor);
				$failedNotificationCount = (int)($monitor['failed_notification_count'] ?? 0);
				?>
				<article class="dashboard-monitor-card monitor-row-<?= e($monitorStatus) ?>">
					<div class="dashboard-monitor-identity">
						<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>"><strong><?= e((string)$monitor['name']) ?></strong></a>
						<span class="status-badge status-<?= e($monitorStatus) ?>"><?= e__('monitors.status.' . $monitorStatus) ?></span>
					</div>
					<?php if ($failedNotificationCount > 0): ?>
						<div class="dashboard-delivery-warning" role="alert">
							<strong><?= e__('monitors.notifications.delivery_failed_heading') ?></strong>
							<span><?= e__('monitors.notifications.delivery_failed_message') ?></span>
						</div>
					<?php endif; ?>
					<div class="dashboard-monitor-time">
						<span><?= e__('dashboard.monitors.last') ?></span>
						<strong><?= e(format_datetime(isset($monitor['last_confirmed_at']) ? (string)$monitor['last_confirmed_at'] : null)) ?></strong>
					</div>
					<div class="dashboard-monitor-time">
						<span><?= e__('dashboard.monitors.next') ?></span>
						<strong><?= e(format_datetime(isset($monitor['next_check_due_at']) ? (string)$monitor['next_check_due_at'] : null, __('dashboard.monitors.suspended'))) ?></strong>
					</div>
					<form method="post" action="<?= e($base_url) ?>/monitors/<?= $monitorStatus === 'paused' ? 'resume' : 'pause' ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
						<input type="hidden" name="redirect" value="/">
						<button type="submit" class="btn-table-inline"><?= e__($monitorStatus === 'paused' ? 'monitors.resume.submit' : 'monitors.pause.submit') ?></button>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<section class="dashboard-activity-section">
	<div class="section-title-row">
		<div>
			<h2><?= e__('dashboard.activity.heading') ?></h2>
			<p><?= e__('dashboard.activity.latest', ['count' => 10]) ?></p>
		</div>
		<a href="<?= e($base_url) ?>/activity"><?= e__('dashboard.activity.view_all') ?></a>
	</div>
	<?php if ($recentActivity === []): ?>
		<p><?= e__('dashboard.activity.none') ?></p>
	<?php else: ?>
		<ol class="activity-list">
			<?php foreach ($recentActivity as $activity): ?>
				<?php
				$activityKey = $activityTranslationKeys[(string)$activity['event_type']] ?? null;
				$monitorName = !empty($activity['monitor_name'])
					? (string)$activity['monitor_name']
					: __('dashboard.activity.unknown_monitor');
				?>
				<?php if (is_string($activityKey)): ?>
					<li>
						<span><?= e__($activityKey, ['name' => $monitorName]) ?></span>
						<time datetime="<?= e((string)$activity['created_at']) ?>"><?= e(format_datetime((string)$activity['created_at'])) ?></time>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</section>

<?php
$content = ob_get_clean();
$title = e__('dashboard.title');
require __DIR__ . '/../layouts/main.php';
