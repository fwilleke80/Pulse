<?php

/**
 * @file quick-checkin.php
 * @brief Authentication page for a reminder-mail quick check-in.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var bool $hasPasskeys */
/** @var bool $locationRequested */
/** @var string $base_url */
/** @var string $locationReverseGeocodeUrl */
/** @var string $locale */

ob_start();
?>

<h1><?= e__('quick_checkin.heading') ?></h1>
<p><?= e__('quick_checkin.hint') ?></p>

<form class="stack" data-quick-checkin-form data-passkey-options-url="<?= e($base_url) ?>/quick-check-in/passkey/options" data-passkey-verify-url="<?= e($base_url) ?>/quick-check-in/passkey/verify" data-passkey-unavailable="<?= e__('security.passkeys.browser_unavailable') ?>" data-passkey-cancelled="<?= e__('security.passkeys.cancelled') ?>"<?= !empty($locationRequested) ? ' ' . check_in_location_attributes($locationReverseGeocodeUrl, $locale) : '' ?>>
	<?= csrf_field() ?>
	<?php if (!empty($locationRequested)): ?><?php require __DIR__ . '/../partials/check-in-location.php'; ?><?php endif; ?>
	<?php if ($hasPasskeys): ?>
		<button type="button" class="btn-primary btn-check-in" data-quick-passkey><?= e__('quick_checkin.passkey_button') ?></button>
		<small><?= e__('quick_checkin.passkey_hint') ?></small>
	<?php else: ?>
		<div class="notification-disabled-warning" role="status">
			<strong><?= e__('quick_checkin.no_passkey.heading') ?></strong>
			<span><?= e__('quick_checkin.no_passkey.message') ?></span>
		</div>
	<?php endif; ?>
	<div class="security-method-status" data-passkey-status hidden></div>
</form>

<div class="auth-separator"><span><?= e__('quick_checkin.or_password') ?></span></div>
<p><a class="button btn-secondary" href="<?= e($base_url) ?>/login?quick=1"><?= e__('quick_checkin.password_button') ?></a></p>

<?php
$content = ob_get_clean();
$title = __('quick_checkin.title');
require __DIR__ . '/../layouts/main.php';
