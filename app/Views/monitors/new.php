<?php

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $contacts */
/** @var string $base_url */

ob_start();
?>

<h1><?= e__('monitors.add.heading') ?></h1>

<form method="post" action="<?= e($base_url) ?>/monitors/create">
	<?= csrf_field() ?>
	<label for="name"><?= e__('monitors.add.name') ?></label>
	<input type="text" id="name" name="name" required>

	<label for="description"><?= e__('monitors.add.description') ?></label>
	<input type="text" id="description" name="description">

	<label for="check_interval_days"><?= e__('monitors.add.check_interval_days') ?></label>
	<input type="number" id="check_interval_days" name="check_interval_days" min="1" value="7" required>

	<label for="response_window_days"><?= e__('monitors.add.response_window_days') ?></label>
	<input type="number" id="response_window_days" name="response_window_days" min="1" value="2" required>

	<label for="reminder_interval_days"><?= e__('monitors.add.reminder_interval_days') ?></label>
	<input type="number" id="reminder_interval_days" name="reminder_interval_days" min="1" value="1" required>

	<label for="max_reminders"><?= e__('monitors.add.max_reminders') ?></label>
	<input type="number" id="max_reminders" name="max_reminders" min="0" value="2" required>

	<div class="checkbox-row">
		<label>
			<input type="checkbox" name="is_paused">
			<?= e__('monitors.add.is_paused') ?>
		</label>
	</div>

	<div class="assignment-box">
		<h2><?= e__('monitors.contacts.heading') ?></h2>
		<p class="form-hint"><?= e__('monitors.contacts.hint') ?></p>

		<?php if ($contacts === []): ?>
			<p><?= e__('monitors.contacts.none') ?></p>
		<?php else: ?>
			<div class="assignment-list">
				<?php foreach ($contacts as $contact): ?>
					<label class="assignment-item">
						<input type="checkbox" name="contact_ids[]" value="<?= (int)$contact['id'] ?>">
						<span>
							<strong><?= htmlspecialchars((string)$contact['name'], ENT_QUOTES, 'UTF-8') ?></strong>
							<?php if (!empty($contact['email'])): ?>
								<br><small><?= htmlspecialchars((string)$contact['email'], ENT_QUOTES, 'UTF-8') ?></small>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<button type="submit"><?= e__('monitors.add.submit') ?></button>
</form>

<?php
$content = ob_get_clean();
$title = e__('monitors.add.title');
require __DIR__ . '/../layouts/main.php';
