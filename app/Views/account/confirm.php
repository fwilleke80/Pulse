<?php declare(strict_types=1); ob_start(); ?>
<h1><?= e__($invitation ? 'accounts.invitation_heading' : 'accounts.verification_heading') ?></h1>
<?php if (!$valid): ?>
<p role="alert"><?= e__('accounts.invalid_token') ?></p>
<?php else: ?>
<p><?= e__($invitation ? 'accounts.invitation_hint' : 'accounts.verification_hint') ?></p>
<form method="post" action="<?= e($base_url) ?>/<?= $invitation ? 'account/set-password' : 'register/confirm' ?>" class="stack">
<?= csrf_field() ?>
<input type="hidden" name="token" value="<?= e($token) ?>">
<?php if ($invitation): ?>
<label for="password"><?= e__('accounts.password') ?></label><input type="password" id="password" name="password" minlength="<?= (int)$passwordMinimumLength ?>" maxlength="72" autocomplete="new-password" required>
<label for="password_confirm"><?= e__('accounts.password_confirm') ?></label><input type="password" id="password_confirm" name="password_confirm" maxlength="72" autocomplete="new-password" required>
<?php endif; ?>
<button type="submit"><?= e__($invitation ? 'accounts.accept' : 'accounts.verify') ?></button>
</form>
<?php endif; ?>
<p><a href="<?= e($base_url) ?>/login"><?= e__('accounts.login') ?></a></p>
<?php $content = ob_get_clean(); $title = __($invitation ? 'accounts.invitation_heading' : 'accounts.verification_heading'); require __DIR__ . '/../layouts/main.php'; ?>
