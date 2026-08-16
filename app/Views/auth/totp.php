<?php

/**
 * @file totp.php
 * @brief Password-login second-factor challenge.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var string $base_url */
/** @var bool $quickCheckInPending */
/** @var bool $locationRequested */
/** @var string $locationReverseGeocodeUrl */
/** @var string $locale */

ob_start();
?>

<h1><?= e__('login.totp.heading') ?></h1>
<p><?= e__('login.totp.message') ?></p>
<?php if (!empty($quickCheckInPending)): ?>
	<div class="notification-status-card" role="status">
		<strong><?= e__('quick_checkin.password_fallback.heading') ?></strong>
		<span><?= e__('login.totp.quick_checkin_hint') ?></span>
	</div>
<?php endif; ?>

<div class="stack" data-passkey-login-scope>
	<form method="post" action="<?= e($base_url) ?>/login/totp" class="stack totp-login-form" data-password-login-form>
		<?= csrf_field() ?>
		<label for="totp_code"><?= e__('login.totp.code') ?></label>
		<input type="text" id="totp_code" name="code" autocomplete="one-time-code" maxlength="64" required autofocus>
		<small><?= e__('login.totp.code_hint') ?></small>
		<button type="submit" class="btn-primary"><?= e__('login.totp.submit') ?></button>
	</form>

	<form method="post" action="<?= e($base_url) ?>/login/totp/cancel" class="compact-form">
		<?= csrf_field() ?>
		<button type="submit"><?= e__('login.totp.cancel') ?></button>
	</form>

	<div class="auth-separator"><span><?= e__('login.totp.or_passkey') ?></span></div>
	<div class="passkey-primary-action" data-passkey-login-form data-passkey-options-url="<?= e($base_url) ?>/login/passkey/options" data-passkey-verify-url="<?= e($base_url) ?>/login/passkey/verify" data-passkey-unavailable="<?= e__('security.passkeys.browser_unavailable') ?>" data-passkey-cancelled="<?= e__('security.passkeys.cancelled') ?>"<?= !empty($locationRequested) ? ' ' . check_in_location_attributes($locationReverseGeocodeUrl, $locale) : '' ?>>
		<?= csrf_field() ?>
		<?php if (!empty($locationRequested)): ?><?php require __DIR__ . '/../partials/check-in-location.php'; ?><?php endif; ?>
		<button type="button" data-passkey-login><?= e__('security.passkeys.login_button') ?></button>
		<small><?= e__('security.totp.passkey_complete_hint') ?></small>
		<div class="security-method-status" data-passkey-status hidden></div>
	</div>
</div>

<?php
$content = ob_get_clean();
$title = __('login.totp.title');
require __DIR__ . '/../layouts/main.php';
