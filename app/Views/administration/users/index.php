<?php
/** @brief Account table within the shared Administration shell. */
declare(strict_types=1);
$section = 'users';
ob_start();
?>
<div class="editor-heading"><h1><?= e__('accounts.heading') ?></h1><a class="button-link" href="<?= e($base_url) ?>/administration/users/edit"><?= e__('accounts.create') ?></a></div>
<?php require __DIR__ . '/../navigation.php'; ?>
<?php if ($accounts === []): ?><p><?= e__('accounts.empty') ?></p><?php else: ?>
<div class="table-scroll"><table class="user-accounts-table">
<thead><tr><th><?= e__('accounts.name') ?></th><th><?= e__('accounts.email') ?></th><th><?= e__('accounts.role') ?></th><th><?= e__('accounts.status') ?></th><th><?= e__('monitors.index.table.actions') ?></th></tr></thead>
<tbody>
<?php foreach ($accounts as $account): ?>
<?php $editUrl = $base_url . '/administration/users/edit?id=' . (int)$account['id']; ?>
<tr>
<td><?php if (empty($account['is_super_user'])): ?><a href="<?= e($editUrl) ?>"><?= e((string)$account['display_name']) ?></a><?php elseif ((int)$account['id'] === (int)$currentUser['id']): ?><a href="<?= e($base_url) ?>/profile"><?= e((string)$account['display_name']) ?></a><?php else: ?><?= e((string)$account['display_name']) ?><?php endif; ?></td>
<td><?= e((string)$account['email']) ?></td>
<td><?= e__(!empty($account['is_super_user']) ? 'accounts.protected' : ($account['role'] === 'administrator' ? 'accounts.role.administrator' : 'accounts.role.user')) ?></td>
<td><?= e__(!empty($account['is_active']) ? 'accounts.active' : 'accounts.blocked') ?><br><small><?= e__(!empty($account['email_verified_at']) ? 'accounts.verified' : 'accounts.unverified') ?> · <?= e__(!empty($account['approved_at']) ? 'accounts.approved' : 'accounts.pending') ?></small></td>
<td>
<?php if (empty($account['is_super_user'])): ?>
<details class="row-action-menu" data-row-action-menu>
<summary class="row-action-menu-toggle" aria-label="<?= e__('accounts.actions', ['name' => (string)$account['display_name']]) ?>"><span aria-hidden="true">⋯</span></summary>
<div class="row-action-menu-panel" data-row-action-menu-panel>
<a class="row-action-menu-item" href="<?= e($editUrl) ?>"><?= e__('accounts.edit') ?></a>
<form method="post" action="<?= e($base_url) ?>/administration/users/invite"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><button class="row-action-menu-item" type="submit"><?= e__('accounts.send_invitation') ?></button></form>
<?php if (empty($account['approved_at'])): ?>
<form method="post" action="<?= e($base_url) ?>/administration/users/approve"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><button class="row-action-menu-item" type="submit"><?= e__('accounts.approve') ?></button></form>
<?php endif; ?>
<a class="row-action-menu-item" href="<?= e($editUrl) ?>#reset-authenticator"><?= e__('accounts.reset_totp') ?></a>
<?php if ((int)$account['id'] !== (int)$currentUser['id']): ?><a class="row-action-menu-item row-action-menu-danger" href="<?= e($editUrl) ?>#delete-account"><?= e__('accounts.delete') ?></a><?php endif; ?>
</div></details>
<?php else: ?><span title="<?= e__('accounts.protected_hint') ?>">—</span><?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<section class="configuration-block">
<h2><?= e__('accounts.audit') ?></h2>
<ol>
<?php foreach (($accountAudit ?? []) as $entry): ?>
<li><?= e(format_datetime((string)$entry['created_at'])) ?> · <?= e__('accounts.actor') ?> #<?= (int)$entry['user_id'] ?> → #<?= (int)$entry['entity_id'] ?>: <?= e__('accounts.event.' . substr((string)$entry['event_type'], 8)) ?></li>
<?php endforeach; ?>
</ol>
</section>
<?php $content = ob_get_clean(); $title = __('accounts.heading'); require __DIR__ . '/../../layouts/main.php'; ?>
