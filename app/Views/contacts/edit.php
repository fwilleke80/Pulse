<?php

/**
 * @file edit.php
 * @brief Contact edit page view.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $contact */
/** @var string $base_url */
/** @var int $returnMonitorId */
/** @var array<int, string> $notificationLocales */
/** @var string $notificationLocale */

ob_start();
?>

<h1><?= e__('contacts.edit.heading') ?></h1>
<form method="post" action="<?= e($base_url) ?>/contacts/update">
	<?= csrf_field() ?>
	<input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
	<?php if ($returnMonitorId > 0): ?>
		<input type="hidden" name="return_monitor_id" value="<?= $returnMonitorId ?>">
	<?php endif; ?>

	<label for="name"><?= e__('contacts.edit.name') ?></label>
	<input
		type="text"
		id="name"
		name="name"
		value="<?= htmlspecialchars((string)$contact['name'], ENT_QUOTES, 'UTF-8') ?>"
		required>

	<label for="email"><?= e__('contacts.edit.email') ?></label>
	<input
		type="email"
		id="email"
		name="email"
		data-contact-email
		data-original-email="<?= htmlspecialchars((string)$contact['email'], ENT_QUOTES, 'UTF-8') ?>"
		value="<?= htmlspecialchars((string)$contact['email'], ENT_QUOTES, 'UTF-8') ?>"
		required>
	<p class="email-suggestion is-hidden" data-email-suggestion data-suggestion-template="<?= e__('contacts.email.suggestion') ?>" role="status"></p>
	<label for="notification_locale"><?= e__('contacts.notification_language') ?></label>
	<select id="notification_locale" name="notification_locale" required>
		<?php foreach ($notificationLocales as $localeOption): ?>
			<option value="<?= e($localeOption) ?>" <?= $localeOption === $notificationLocale ? 'selected' : '' ?>>
				<?= e__('notification.language.' . $localeOption) ?>
			</option>
		<?php endforeach; ?>
	</select>
	<small><?= e__('contacts.notification_language_hint') ?></small>
	<div class="checkbox-row contact-email-check">
		<label>
			<input
				type="checkbox"
				name="email_checked"
				data-email-checked
				<?= !empty($contact['email_checked_at']) ? 'checked' : '' ?>
				required>
			<?= e__('contacts.email_checked.label') ?>
		</label>
		<small><?= e__('contacts.email_checked.hint') ?></small>
	</div>

	<label for="cell_phone"><?= e__('contacts.edit.cell_phone') ?></label>
	<input
		type="text"
		id="cell_phone"
		name="cell_phone"
		value="<?= htmlspecialchars((string)($contact['cell_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

	<label for="notes"><?= e__('contacts.edit.notes') ?></label>
	<textarea
		id="notes"
		name="notes"
		rows="3"><?= htmlspecialchars((string)($contact['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

	<button type="submit"><?= e__('contacts.edit.submit') ?></button>
</form>

<?php if ($returnMonitorId > 0): ?>
	<p><a href="<?= e($base_url) ?>/monitors/edit?id=<?= $returnMonitorId ?>&amp;tab=recipients"><?= e__('contacts.edit.back_to_monitor') ?></a></p>
<?php else: ?>
	<p><a href="<?= e($base_url) ?>/contacts"><?= e__('contacts.edit.back') ?></a></p>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = e__('contacts.edit.title');
require __DIR__ . '/../layouts/main.php';
