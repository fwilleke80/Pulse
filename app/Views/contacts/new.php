<?php

/**
 * @file new.php
 * @brief Contact creation page view.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var string $base_url */
/** @var array<int, string> $notificationLocales */
/** @var string $notificationLocale */

ob_start();
?>

<h1><?= e__('contacts.add.heading') ?></h1>
<form method="post" action="<?= e($base_url) ?>/contacts/create">
	<?= csrf_field() ?>
	<label for="name"><?= e__('contacts.add.name') ?></label>
	<input type="text" id="name" name="name" required>
	<div class="email-address-grid">
		<?php for ($slot = 1; $slot <= \Pulse\Core\EmailAddressCollection::MAX_ADDRESSES; $slot++): ?>
			<?php $emailField = \Pulse\Core\EmailAddressCollection::EmailField($slot); $checkedField = $slot === 1 ? 'email_checked' : 'email_' . $slot . '_checked'; ?>
			<div class="email-address-card" data-email-address-card>
				<label for="<?= e($emailField) ?>"><?= e__('contacts.add.email') ?></label>
				<input type="email" id="<?= e($emailField) ?>" name="<?= e($emailField) ?>" data-contact-email<?= $slot === 1 ? ' required' : '' ?>>
				<p class="email-suggestion is-hidden" data-email-suggestion data-suggestion-template="<?= e__('contacts.email.suggestion') ?>" role="status"></p>
				<label class="compact-check"><input type="checkbox" name="<?= e($checkedField) ?>" data-email-checked> <?= e__('contacts.email_checked.label') ?></label>
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
	<label for="cell_phone"><?= e__('contacts.add.cell_phone') ?></label>
	<input type="text" id="cell_phone" name="cell_phone">
	<label for="notes"><?= e__('contacts.add.notes') ?></label>
	<textarea id="notes" name="notes" rows="3"></textarea>
	<button type="submit"><?= e__('contacts.add.submit') ?></button>
</form>

<?php
$content = ob_get_clean();
$title = e__('contacts.add.title');
require __DIR__ . '/../layouts/main.php';
