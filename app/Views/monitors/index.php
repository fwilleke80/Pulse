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

ob_start();
?>

<h1><?= e__('monitors.index.heading') ?></h1>
<p><?= e__('monitors.index.message') ?></p>
<p><a href="<?= e($base_url) ?>/monitors/new"><?= e__('monitors.index.add') ?></a></p>

<?php if ($monitors === []): ?>
	<p><?= e__('monitors.index.no_monitors') ?></p>
<?php else: ?>
	<div class="table-scroll">
		<table>
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
					$isPaused = !empty($monitor['is_paused']);
					$isDue = is_monitor_due($monitor);
					$statusKey = $isPaused ? 'monitors.status.paused' : ($isDue ? 'monitors.status.due' : 'monitors.status.scheduled');
					$statusClass = $isPaused ? 'paused' : ($isDue ? 'due' : 'scheduled');
					?>
					<tr class="monitor-row monitor-row-<?= e($statusClass) ?>">
						<td>
							<strong><?= e(abbrev((string)$monitor['name'], 60)) ?></strong>
							<?php if (!empty($monitor['description'])): ?>
								<br><small><?= e(abbrev((string)$monitor['description'], 90)) ?></small>
							<?php endif; ?>
						</td>
						<td><span class="status-badge status-<?= e($statusClass) ?>"><?= e__($statusKey) ?></span></td>
						<td><?= e(format_datetime(isset($monitor['last_confirmed_at']) ? (string)$monitor['last_confirmed_at'] : null)) ?></td>
						<td><?= e(format_datetime(isset($monitor['next_check_due_at']) ? (string)$monitor['next_check_due_at'] : null)) ?></td>
						<td>
							<div class="table-actions">
								<?php if ($isDue): ?>
									<form method="post" action="<?= e($base_url) ?>/monitors/check-in">
										<?= csrf_field() ?>
										<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
										<input type="hidden" name="redirect" value="/monitors">
										<button type="submit" class="btn-table-inline btn-primary"><?= e__('monitors.check_in.submit') ?></button>
									</form>
								<?php endif; ?>
								<form method="get" action="<?= e($base_url) ?>/monitors/edit">
									<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
									<button type="submit" class="btn-table-inline"><?= e__('monitors.index.table.buttons.edit') ?></button>
								</form>
								<?php if ($allowForceDue): ?>
									<form method="post" action="<?= e($base_url) ?>/monitors/force-due">
										<?= csrf_field() ?>
										<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
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
