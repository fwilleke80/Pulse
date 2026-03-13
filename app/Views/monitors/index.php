<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('monitors.index.heading') ?></h1>
<p><a href="<?= e($base_url) ?>/monitors/new"><?= e__('monitors.index.add') ?></a></p>

<?php if ($monitors === []): ?>
	<p><?= e__('monitors.index.no_monitors') ?></p>
<?php else: ?>
	<table>
		<thead>
			<tr>
				<th><?= e__('monitors.index.table.name') ?></th>
				<th><?= e__('monitors.index.table.description') ?></th>
				<th><?= e__('monitors.index.table.check_interval_days') ?></th>
				<th><?= e__('monitors.index.table.response_window_days') ?></th>
				<th><?= e__('monitors.index.table.reminder_interval_days') ?></th>
				<th><?= e__('monitors.index.table.max_reminders') ?></th>
				<th><?= e__('monitors.index.table.is_paused') ?></th>
				<th><?= e__('monitors.index.table.is_test_mode') ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($monitors as $monitor): ?>
				<tr>
					<td><?= htmlspecialchars((string)$monitor['name'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars((string)($monitor['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= (int)$monitor['check_interval_days'] ?></td>
					<td><?= (int)$monitor['response_window_days'] ?></td>
					<td><?= (int)$monitor['reminder_interval_days'] ?></td>
					<td><?= (int)$monitor['max_reminders'] ?></td>
					<td><?= !empty($monitor['is_paused']) ? e__('common.yes') : e__('common.no') ?></td>
					<td><?= !empty($monitor['is_test_mode']) ? e__('common.yes') : e__('common.no') ?></td>
					<td>
						<div class="table-actions">
							<form method="get" action="<?= e($base_url) ?>/monitors/edit">
								<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
								<button type="submit" class="btn-table-inline"><?= e__('monitors.index.table.buttons.edit') ?></button>
							</form>
							<form method="post" action="<?= e($base_url) ?>/monitors/delete" onsubmit="return confirm('Delete this monitor?');">
								<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
								<button type="submit" class="btn-table-inline"><?= e__('monitors.index.table.buttons.delete') ?></button>
							</form>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = e__('monitors.index.title');
require __DIR__ . '/../layouts/main.php';