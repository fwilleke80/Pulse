<?php

/**
 * @file index.php
 * @brief Contact list page view.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $contacts */
/** @var string $base_url */

ob_start();
?>

<h1><?= e__('contacts.index.heading') ?></h1>
<p><?= e__('contacts.index.message') ?></p>
<p><a href="<?= e($base_url) ?>/contacts/new" class="button-link"><?= e__('contacts.index.add') ?></a></p>
<?php if ($contacts === []): ?>
	<p><?= e__('contacts.index.no_contacts') ?></p>
<?php else: ?>
	<div class="table-scroll">
	<table class="contacts-table">
		<thead>
			<tr>
				<th><?= e__('contacts.index.table.name') ?></th>
				<th><?= e__('contacts.index.table.email') ?></th>
				<th><?= e__('contacts.index.table.address_status') ?></th>
				<th><?= e__('contacts.index.table.cell_phone') ?></th>
				<th><?= e__('contacts.index.table.notes') ?></th>
				<th class="compact-actions-heading"><?= e__('contacts.index.table.actions') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($contacts as $contact): ?>
				<?php $addresses = \Pulse\Core\EmailAddressCollection::FromRow($contact); $checkedCount = count(array_filter($addresses, static fn (array $address): bool => $address['checked'])); ?>
				<tr>
					<td><a href="<?= e($base_url) ?>/contacts/edit?id=<?= (int)$contact['id'] ?>"><strong><?= e((string)$contact['name']) ?></strong></a></td>
					<td><div class="email-address-list"><?php foreach ($addresses as $address): ?><span class="email-address-list-item"><a href="mailto:<?= e($address['email']) ?>"><?= e($address['email']) ?></a><span class="mini-status mini-status-<?= $address['checked'] ? 'ok' : 'warning' ?>"><?= e__($address['checked'] ? 'contacts.status.checked' : 'contacts.status.not_checked') ?></span></span><?php endforeach; ?></div></td>
					<td>
						<?php if ($checkedCount > 0): ?>
							<span class="status-badge status-checked-in"><?= e__('contacts.status.checked_count', ['checked' => $checkedCount, 'total' => count($addresses)]) ?></span>
						<?php else: ?>
							<span class="status-badge status-overdue"><?= e__('contacts.status.none_checked') ?></span>
						<?php endif; ?>
					</td>
					<td><a href="tel:<?= e((string)($contact['cell_phone'] ?? '')) ?>"><?= e((string)($contact['cell_phone'] ?? '')) ?></a></td>
					<td><?= e(abbrev((string)$contact['notes'], 40)) ?></td>
					<td class="compact-actions-cell">
						<details class="row-action-menu" data-row-action-menu>
							<summary class="row-action-menu-toggle" aria-label="<?= e__('contacts.actions.open', ['name' => (string)$contact['name']]) ?>" title="<?= e__('contacts.index.table.actions') ?>"><span aria-hidden="true">⋮</span></summary>
							<div class="row-action-menu-panel" data-row-action-menu-panel>
								<?php if ((int)($contact['monitor_reference_count'] ?? 0) > 0): ?>
									<button type="button" class="row-action-menu-item" disabled title="<?= e__('contacts.index.delete_in_use_hint') ?>"><?= e__('contacts.index.table.buttons.delete') ?></button>
								<?php else: ?>
									<form method="post" action="<?= e($base_url) ?>/contacts/delete" data-confirm="<?= e__('contacts.index.flash.delete_confirm', ['name' => $contact['name']]) ?>">
										<?= csrf_field() ?>
										<input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
										<button type="submit" class="row-action-menu-item row-action-menu-danger"><?= e__('contacts.index.table.buttons.delete') ?></button>
									</form>
								<?php endif; ?>
							</div>
						</details>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = e__('contacts.index.title');
require __DIR__ . '/../layouts/main.php';
