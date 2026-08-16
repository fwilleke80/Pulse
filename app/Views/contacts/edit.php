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
$addresses = \Pulse\Core\EmailAddressCollection::FromRow($contact);
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

	<div class="email-address-grid">
		<?php for ($slot = 1; $slot <= \Pulse\Core\EmailAddressCollection::MAX_ADDRESSES; $slot++): ?>
			<?php
			$emailField = \Pulse\Core\EmailAddressCollection::EmailField($slot);
			$checkedField = $slot === 1 ? 'email_checked' : 'email_' . $slot . '_checked';
			$address = $addresses[$slot - 1] ?? ['email' => '', 'checked' => false];
			?>
			<div class="email-address-card" data-email-address-card>
				<label for="<?= e($emailField) ?>"><?= e__('contacts.edit.email') ?></label>
				<input type="email" id="<?= e($emailField) ?>" name="<?= e($emailField) ?>" data-contact-email data-original-email="<?= e((string)$address['email']) ?>" value="<?= e((string)$address['email']) ?>"<?= $slot === 1 ? ' required' : '' ?>>
				<p class="email-suggestion is-hidden" data-email-suggestion data-suggestion-template="<?= e__('contacts.email.suggestion') ?>" role="status"></p>
				<label class="compact-check"><input type="checkbox" name="<?= e($checkedField) ?>" data-email-checked<?= !empty($address['checked']) ? ' checked' : '' ?>> <?= e__('contacts.email_checked.label') ?></label>
			</div>
		<?php endfor; ?>
	</div>
	<small><?= e__('contacts.email_checked.hint') ?></small>
	<label for="notification_locale"><?= e__('contacts.notification_language') ?></label>
	<select id="notification_locale" name="notification_locale" required>
		<?php foreach ($notificationLocales as $localeOption): ?>
			<option value="<?= e($localeOption) ?>" <?= $localeOption === $notificationLocale ? 'selected' : '' ?>>
				<?= e(notification_language_name($localeOption)) ?>
			</option>
		<?php endforeach; ?>
	</select>
	<small><?= e__('contacts.notification_language_hint') ?></small>

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
