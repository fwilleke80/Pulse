<?php declare(strict_types=1); $editing = is_array($account); $section = 'users'; ob_start(); ?>
<h1><?= e__($editing ? 'accounts.edit' : 'accounts.create') ?></h1>
<?php require __DIR__ . '/../navigation.php'; ?>
<p><?= e__('accounts.password_hint') ?></p>
<form method="post" action="<?= e($base_url) ?>/administration/users/<?= $editing ? 'edit' : 'create' ?>" class="stack">
<?= csrf_field() ?>
<?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><?php endif; ?>
<label for="display_name"><?= e__('accounts.name') ?></label><input id="display_name" name="display_name" value="<?= e((string)($account['display_name'] ?? '')) ?>" maxlength="255" required>
<label for="email"><?= e__('accounts.email') ?></label><input type="email" id="email" name="email" value="<?= e((string)($account['email'] ?? '')) ?>" maxlength="255" required>
<label for="password"><?= e__('accounts.temporary_password') ?></label><input type="password" id="password" name="password" minlength="<?= (int)$passwordMinimumLength ?>" maxlength="72" autocomplete="new-password">
<label for="role"><?= e__('accounts.role') ?></label>
<select id="role" name="role">
<option value="user"<?= ($account['role'] ?? 'user') === 'user' ? ' selected' : '' ?>><?= e__('accounts.role.user') ?></option>
<option value="administrator"<?= ($account['role'] ?? '') === 'administrator' ? ' selected' : '' ?>><?= e__('accounts.role.administrator') ?></option>
</select>
<p class="form-hint"><?= e__('accounts.require_password_change') ?></p>
<label><input type="checkbox" name="is_active" value="1"<?= !$editing || !empty($account['is_active']) ? ' checked' : '' ?>> <?= e__('accounts.active') ?></label>
<p class="form-hint"><?= e__('accounts.block_hint') ?></p>
<label><input type="checkbox" name="totp_required" value="1"<?= !empty($account['totp_required']) ? ' checked' : '' ?>> <?= e__('accounts.require_totp') ?></label>
<button type="submit"><?= e__('accounts.save') ?></button>
</form>
<?php if ($editing): ?>
<hr>
<form id="reset-authenticator" method="post" action="<?= e($base_url) ?>/administration/users/totp-reset" data-confirm="<?= e__('accounts.reset_confirm') ?>">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
<label><input type="checkbox" name="totp_required" value="1" checked> <?= e__('accounts.require_totp') ?></label>
<button type="submit"><?= e__('accounts.reset_totp') ?></button>
</form>
<hr>
<p><?= e__('accounts.invite_hint') ?></p>
<form method="post" action="<?= e($base_url) ?>/administration/users/invite">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
<button type="submit"><?= e__('accounts.send_invitation') ?></button>
</form>
<?php if (empty($account['approved_at'])): ?>
<form method="post" action="<?= e($base_url) ?>/administration/users/approve">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
<button type="submit"><?= e__('accounts.approve') ?></button>
</form>
<?php endif; ?>
<?php if ((int)$account['id'] !== (int)$currentUser['id']): ?>
<hr>
<form id="delete-account" method="post" action="<?= e($base_url) ?>/administration/users/delete" class="stack">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
<label><input type="checkbox" name="confirm_delete" value="1" required> <?= e__('accounts.delete_confirm') ?></label>
<button type="submit" class="btn-danger"><?= e__('accounts.delete') ?></button>
</form>
<?php endif; ?>
<?php endif; ?>
<p><a href="<?= e($base_url) ?>/administration/users"><?= e__('accounts.back') ?></a></p>
<?php $content = ob_get_clean(); $title = __($editing ? 'accounts.edit' : 'accounts.create'); require __DIR__ . '/../../layouts/main.php'; ?>
