<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('monitors.edit.heading') ?></h1>

<form method="post" action="<?= e($base_url) ?>/monitors/update">
	<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">

	<label for="name"><?= e__('monitors.edit.name') ?></label>
	<input
		type="text"
		id="name"
		name="name"
		value="<?= htmlspecialchars((string)$monitor['name'], ENT_QUOTES, 'UTF-8') ?>"
		required
	>

	<label for="description"><?= e__('monitors.edit.description') ?></label>
	<input
		type="text"
		id="description"
		name="description"
		value="<?= htmlspecialchars((string)($monitor['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
	>

	<label for="check_interval_days"><?= e__('monitors.edit.check_interval_days') ?></label>
	<input
		type="number"
		id="check_interval_days"
		name="check_interval_days"
		min="1"
		value="<?= (int)$monitor['check_interval_days'] ?>"
		required
	>

	<label for="response_window_days"><?= e__('monitors.edit.response_window_days') ?></label>
	<input
		type="number"
		id="response_window_days"
		name="response_window_days"
		min="1"
		value="<?= (int)$monitor['response_window_days'] ?>"
		required
	>

	<label for="reminder_interval_days"><?= e__('monitors.edit.reminder_interval_days') ?></label>
	<input
		type="number"
		id="reminder_interval_days"
		name="reminder_interval_days"
		min="1"
		value="<?= (int)$monitor['reminder_interval_days'] ?>"
		required
	>

	<label for="max_reminders"><?= e__('monitors.edit.max_reminders') ?></label>
	<input
		type="number"
		id="max_reminders"
		name="max_reminders"
		min="0"
		value="<?= (int)$monitor['max_reminders'] ?>"
		required
	>

	<div class="checkbox-row">
		<label>
			<input type="checkbox" name="is_paused" <?= !empty($monitor['is_paused']) ? 'checked' : '' ?>>
			<?= e__('monitors.edit.is_paused') ?>
		</label>
	</div>

	<div class="checkbox-row">
		<label>
			<input type="checkbox" name="is_test_mode" <?= !empty($monitor['is_test_mode']) ? 'checked' : '' ?>>
			<?= e__('monitors.edit.is_test_mode') ?>
		</label>
	</div>

	<button type="submit"><?= e__('monitors.edit.submit') ?></button>
</form>

<p><a href="<?= e($base_url) ?>/monitors"><?= e__('monitors.edit.back') ?></a></p>

<?php
$content = ob_get_clean();
$title = e__('monitors.edit.title');
require __DIR__ . '/../layouts/main.php';