<?php

/**
 * @file index.php
 * @brief Runtime-oriented monitor overview.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $monitors */
/** @var string $base_url */
/** @var bool $debugEnabled */
/** @var bool $mailEnabled */
/** @var bool $showArchived */

$activeMonitorCount = count(array_filter(
	$monitors,
	static fn (array $monitor): bool => !in_array(monitor_status($monitor), ['paused', 'escalated', 'archived'], true)
));

ob_start();
?>

<h1><?= e__($showArchived ? 'monitors.index.archived.heading' : 'monitors.index.heading') ?></h1>
<p><?= e__($showArchived ? 'monitors.index.archived.message' : 'monitors.index.message') ?></p>
<div class="monitor-index-toolbar">
	<?php if (!$showArchived): ?>
		<a href="<?= e($base_url) ?>/monitors/new" class="button-link"><?= e__('monitors.index.add') ?></a>
	<?php endif; ?>
	<a href="<?= e($base_url) ?>/monitors<?= $showArchived ? '' : '?view=archived' ?>" class="button-link monitor-view-toggle"><?= e__($showArchived ? 'monitors.index.view.active' : 'monitors.index.view.archived') ?></a>
	<?php if (!$showArchived && $activeMonitorCount > 0): ?>
		<form method="post" action="<?= e($base_url) ?>/monitors/check-in">
			<?= csrf_field() ?>
			<input type="hidden" name="redirect" value="/monitors">
			<button type="submit" class="btn-primary"><?= e__('monitors.check_in.submit') ?></button>
		</form>
	<?php endif; ?>
</div>

<?php if (!$showArchived && $activeMonitorCount > 0): ?>
	<p class="form-hint"><?= e__('monitors.index.check_in_hint', ['count' => $activeMonitorCount]) ?></p>
<?php endif; ?>

<?php if ($monitors === []): ?>
	<p><?= e__($showArchived ? 'monitors.index.no_archived' : 'monitors.index.no_monitors') ?></p>
<?php else: ?>
	<div class="table-scroll">
		<table class="monitor-table">
			<thead>
				<tr>
					<th><?= e__('monitors.index.table.name') ?></th>
					<th><?= e__('monitors.index.table.description') ?></th>
					<th><?= e__('monitors.index.table.status') ?></th>
					<th><?= e__('monitors.index.table.last_confirmed') ?></th>
					<th><?= e__('monitors.index.table.next_due') ?></th>
					<th><?= e__('monitors.index.table.actions') ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($monitors as $monitor): ?>
					<?php
					$statusClass = monitor_status($monitor);
					$statusKey = 'monitors.status.' . $statusClass;
					$uncheckedContactCount = (int)($monitor['unchecked_contact_count'] ?? 0);
					$failedNotificationCount = (int)($monitor['failed_notification_count'] ?? 0);
					$releaseBlocked = (string)($monitor['latest_release_status'] ?? '') === 'blocked';
					$recipientConfigurationIssueCount = (int)($monitor['recipient_configuration_issue_count'] ?? 0);
					$cycleEscalationPolicy = (string)($monitor['latest_escalation_policy'] ?? $monitor['escalation_policy'] ?? 'direct');
					?>
					<tr class="monitor-row monitor-row-<?= e($statusClass) ?>">
						<td class="monitor-name">
							<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>">
								<strong><?= e(abbrev((string)$monitor['name'], 60)) ?></strong>
							</a>
						</td>
						<td class="monitor-description">
							<?= !empty($monitor['description']) ? e(abbrev((string)$monitor['description'], 120)) : '<span aria-hidden="true">—</span>' ?>
						</td>
						<td class="monitor-status-cell">
							<span class="status-badge status-<?= e($statusClass) ?>"><?= e__($statusKey) ?></span>
							<?php if ($failedNotificationCount > 0): ?>
								<small class="table-warning table-warning-critical"><?= e__('monitors.notifications.delivery_failed_short') ?></small>
							<?php endif; ?>
							<?php if ($releaseBlocked): ?>
								<small class="table-warning table-warning-critical"><?= e__('monitors.notifications.release_blocked_short') ?></small>
							<?php endif; ?>
							<?php if ($recipientConfigurationIssueCount > 0): ?>
								<small class="table-warning table-warning-critical"><?= e__('monitors.index.configuration_warning', ['count' => $recipientConfigurationIssueCount]) ?></small>
							<?php endif; ?>
							<?php if ($uncheckedContactCount > 0 && empty($monitor['is_paused'])): ?>
								<small class="table-warning"><?= e__(
									$uncheckedContactCount === 1
										? 'monitors.index.unchecked_contacts.one'
										: 'monitors.index.unchecked_contacts.many',
									['count' => $uncheckedContactCount]
								) ?></small>
							<?php endif; ?>
						</td>
						<td class="monitor-datetime"><?= e(format_datetime(isset($monitor['last_confirmed_at']) ? (string)$monitor['last_confirmed_at'] : null)) ?></td>
						<td class="monitor-datetime"><?= e(format_datetime(isset($monitor['next_check_due_at']) ? (string)$monitor['next_check_due_at'] : null)) ?></td>
						<td class="monitor-actions-cell">
							<?php
							$actionStatus = $statusClass;
							$actionRedirect = $showArchived ? '/monitors?view=archived' : '/monitors';
							$actionAllowDelete = (int)($monitor['release_history_count'] ?? 0) === 0;
							require __DIR__ . '/partials/actions.php';
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = e__('monitors.index.title');
require __DIR__ . '/../layouts/main.php';
