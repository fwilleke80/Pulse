<?php

/**
 * @file login.php
 * @brief Login page view.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var string $base_url */
/** @var bool $quickCheckInPending */

ob_start();
?>

<h1><?= e__('login.heading') ?></h1>
<p><?= e__('login.message') ?></p>
<?php if (!empty($quickCheckInPending)): ?>
	<div class="notification-status-card" role="status">
		<strong><?= e__('quick_checkin.password_fallback.heading') ?></strong>
		<span><?= e__('quick_checkin.password_fallback.hint') ?></span>
	</div>
<?php endif; ?>
<form method="post" action="<?= e($base_url) ?>/login" class="stack" data-passkey-login-form data-passkey-options-url="<?= e($base_url) ?>/login/passkey/options" data-passkey-verify-url="<?= e($base_url) ?>/login/passkey/verify" data-passkey-unavailable="<?= e__('security.passkeys.browser_unavailable') ?>" data-passkey-cancelled="<?= e__('security.passkeys.cancelled') ?>">
	<?= csrf_field() ?>
	<label for="email"><?= e__('login.email') ?></label>
	<input type="email" id="email" name="email" autocomplete="username" required>
	<label for="password"><?= e__('login.password') ?></label>
	<input type="password" id="password" name="password" autocomplete="current-password" required>
	<button type="submit"><?= e__('login.submit') ?></button>
	<div class="auth-separator"><span><?= e__('login.or_passkey') ?></span></div>
	<div class="passkey-primary-action">
		<button type="button" class="btn-primary" data-passkey-login><?= e__('security.passkeys.login_button') ?></button>
		<small><?= e__('security.passkeys.login_hint') ?></small>
		<div class="security-method-status" data-passkey-status hidden></div>
	</div>
</form>

<?php
$content = ob_get_clean();
$title = e__('login.title');
require __DIR__ . '/../layouts/main.php';
