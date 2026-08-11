<?php

/**
 * @file index.php
 * @brief Runtime-oriented monitor overview.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $monitors */
/** @var string $base_url */
/** @var bool $allowForceDue */

$activeMonitorCount = count(array_filter(
	$monitors,
	static fn (array $monitor): bool => empty($monitor['is_paused'])
));

ob_start();
?>

<h1><?= e__('monitors.index.heading') ?></h1>
<p><?= e__('monitors.index.message') ?></p>
<div class="monitor-index-toolbar">
	<a href="<?= e($base_url) ?>/monitors/new" class="button-link"><?= e__('monitors.index.add') ?></a>
	<?php if ($activeMonitorCount > 0): ?>
		<form method="post" action="<?= e($base_url) ?>/monitors/check-in">
			<?= csrf_field() ?>
			<input type="hidden" name="redirect" value="/monitors">
			<button type="submit" class="btn-primary"><?= e__('monitors.check_in.submit') ?></button>
		</form>
	<?php endif; ?>
</div>

<?php if ($activeMonitorCount > 0): ?>
	<p class="form-hint"><?= e__('monitors.index.check_in_hint', ['count' => $activeMonitorCount]) ?></p>
<?php endif; ?>

<?php if ($monitors === []): ?>
	<p><?= e__('monitors.index.no_monitors') ?></p>
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
						<td>
							<span class="status-badge status-<?= e($statusClass) ?>"><?= e__($statusKey) ?></span>
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
							<div class="table-actions">
								<form method="post" action="<?= e($base_url) ?>/monitors/<?= $statusClass === 'paused' ? 'resume' : 'pause' ?>">
									<?= csrf_field() ?>
									<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
									<input type="hidden" name="redirect" value="/monitors">
									<button type="submit" class="btn-table-inline"><?= e__($statusClass === 'paused' ? 'monitors.resume.submit' : 'monitors.pause.submit') ?></button>
								</form>
								<?php if ($allowForceDue && $statusClass === 'checked-in'): ?>
									<form method="post" action="<?= e($base_url) ?>/monitors/force-due">
										<?= csrf_field() ?>
										<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
										<input type="hidden" name="redirect" value="/monitors">
										<button type="submit" class="btn-table-inline"><?= e__('monitors.force_due.submit') ?></button>
									</form>
								<?php endif; ?>
								<form method="post" action="<?= e($base_url) ?>/monitors/delete" data-confirm="<?= e__('monitors.index.delete_confirm') ?>">
									<?= csrf_field() ?>
									<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
									<button type="submit" class="btn-table-inline btn-danger"><?= e__('monitors.index.table.buttons.delete') ?></button>
								</form>
							</div>
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
