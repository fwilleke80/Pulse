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
/** @var bool $debugEnabled */
/** @var string $base_url */

$activeMonitors = array_values(array_filter(
	$monitors,
	static fn (array $monitor): bool => empty($monitor['is_paused'])
));
$pausedMonitors = array_values(array_filter(
	$monitors,
	static fn (array $monitor): bool => !empty($monitor['is_paused'])
));
$attentionCount = count(array_filter(
	$activeMonitors,
	static fn (array $monitor): bool => in_array(monitor_status($monitor), ['awaiting', 'safety-pending', 'overdue', 'escalated'], true)
));
$activityTranslationKeys = [
	'monitor.checked_in' => 'dashboard.activity.checked_in',
	'monitor.awaiting' => 'dashboard.activity.awaiting',
	'monitor.safety_requested' => 'dashboard.activity.safety_requested',
	'monitor.safety_expired' => 'dashboard.activity.safety_expired',
	'monitor.safety_confirmed' => 'dashboard.activity.safety_confirmed',
	'monitor.overdue' => 'dashboard.activity.overdue',
	'monitor.escalated' => 'dashboard.activity.escalated',
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
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.active') ?></div>
		<div class="dashboard-stat-value"><?= count($activeMonitors) ?></div>
	</a>
	<a href="<?= e($base_url) ?>/monitors" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.paused') ?></div>
		<div class="dashboard-stat-value"><?= count($pausedMonitors) ?></div>
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
		<div class="table-scroll">
			<table class="monitor-table dashboard-monitor-table">
				<thead>
					<tr>
						<th><?= e__('monitors.index.table.name') ?></th>
						<th><?= e__('monitors.index.table.status') ?></th>
						<th><?= e__('monitors.index.table.last_confirmed') ?></th>
						<th><?= e__('monitors.index.table.next_due') ?></th>
						<th><?= e__('monitors.index.table.actions') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($monitors as $monitor): ?>
						<?php
						$monitorStatus = monitor_status($monitor);
						$failedNotificationCount = (int)($monitor['failed_notification_count'] ?? 0);
						$releaseBlocked = (string)($monitor['latest_release_status'] ?? '') === 'blocked';
						$recipientConfigurationIssueCount = (int)($monitor['recipient_configuration_issue_count'] ?? 0);
						$uncheckedContactCount = (int)($monitor['unchecked_contact_count'] ?? 0);
						?>
						<tr class="monitor-row monitor-row-<?= e($monitorStatus) ?>">
							<td class="monitor-name">
								<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>"><strong><?= e(abbrev((string)$monitor['name'], 60)) ?></strong></a>
							</td>
							<td class="monitor-status-cell">
								<span class="status-badge status-<?= e($monitorStatus) ?>"><?= e__('monitors.status.' . $monitorStatus) ?></span>
								<?php if ($failedNotificationCount > 0): ?><small class="table-warning table-warning-critical"><?= e__('monitors.notifications.delivery_failed_short') ?></small><?php endif; ?>
								<?php if ($releaseBlocked): ?><small class="table-warning table-warning-critical"><?= e__('monitors.notifications.release_blocked_short') ?></small><?php endif; ?>
								<?php if ($recipientConfigurationIssueCount > 0): ?><small class="table-warning table-warning-critical"><?= e__('monitors.index.configuration_warning', ['count' => $recipientConfigurationIssueCount]) ?></small><?php endif; ?>
								<?php if ($uncheckedContactCount > 0 && empty($monitor['is_paused'])): ?>
									<small class="table-warning"><?= e__($uncheckedContactCount === 1 ? 'monitors.index.unchecked_contacts.one' : 'monitors.index.unchecked_contacts.many', ['count' => $uncheckedContactCount]) ?></small>
								<?php endif; ?>
							</td>
							<td class="monitor-datetime"><?= e(format_datetime(isset($monitor['last_confirmed_at']) ? (string)$monitor['last_confirmed_at'] : null)) ?></td>
							<td class="monitor-datetime"><?= e(format_datetime(isset($monitor['next_check_due_at']) ? (string)$monitor['next_check_due_at'] : null, __('dashboard.monitors.suspended'))) ?></td>
							<td class="monitor-actions-cell">
								<?php
								$actionStatus = $monitorStatus;
								$actionRedirect = '/';
								$actionAllowDelete = false;
								require __DIR__ . '/../monitors/partials/actions.php';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
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
