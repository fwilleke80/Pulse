<?php declare(strict_types=1); ob_start(); ?>
<h1><?= e__('accounts.register_heading') ?></h1>
<p><?= e__('accounts.register_hint') ?></p>
<form method="post" action="<?= e($base_url) ?>/register" class="stack">
<?= csrf_field() ?>
<label for="display_name"><?= e__('accounts.name') ?></label><input id="display_name" name="display_name" maxlength="255" autocomplete="name" required>
<label for="email"><?= e__('accounts.email') ?></label><input type="email" id="email" name="email" maxlength="255" autocomplete="email" required>
<label for="password"><?= e__('accounts.password') ?></label><input type="password" id="password" name="password" minlength="<?= (int)$passwordMinimumLength ?>" maxlength="72" autocomplete="new-password" required>
<label for="password_confirm"><?= e__('accounts.password_confirm') ?></label><input type="password" id="password_confirm" name="password_confirm" maxlength="72" autocomplete="new-password" required>
<button type="submit"><?= e__('accounts.register') ?></button>
</form>
<p><a href="<?= e($base_url) ?>/login"><?= e__('accounts.login') ?></a></p>
<?php $content = ob_get_clean(); $title = __('accounts.register_heading'); require __DIR__ . '/../layouts/main.php'; ?>
