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
<p><a href="<?= e($base_url) ?>/contacts/new"><?= e__('contacts.index.add') ?></a></p>
<?php if ($contacts === []): ?>
	<p><?= e__('contacts.index.no_contacts') ?></p>
<?php else: ?>
	<table>
		<thead>
			<tr>
				<th><?= e__('contacts.index.table.name') ?></th>
				<th><?= e__('contacts.index.table.email') ?></th>
				<th><?= e__('contacts.index.table.cell_phone') ?></th>
				<th><?= e__('contacts.index.table.notes') ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($contacts as $contact): ?>
				<tr>
					<td><?= e((string)$contact['name']) ?></td>
					<td><a href="mailto:<?= e((string)$contact['email']) ?>"><?= e((string)$contact['email']) ?></a></td>
					<td><a href="tel:<?= e((string)($contact['cell_phone'] ?? '')) ?>"><?= e((string)($contact['cell_phone'] ?? '')) ?></a></td>
					<td><?= e(abbrev((string)$contact['notes'], 40)) ?></td>
					<td>
						<div class="table-actions">
							<form method="get" action="<?= e($base_url) ?>/contacts/edit">
								<input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
								<button type="submit" class="btn-table-inline"><?= e__('contacts.index.table.buttons.edit') ?></button>
							</form>
							<form method="post" action="<?= e($base_url) ?>/contacts/delete" onsubmit="return confirm('<?= e__('contacts.index.flash.delete_confirm', ['name' => $contact['name']]) ?>');">
								<input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
								<button type="submit" class="btn-table-inline"><?= e__('contacts.index.table.buttons.delete') ?></button>
							</form>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = e__('contacts.index.title');
require __DIR__ . '/../layouts/main.php';