<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('contacts.index.heading') ?></h1>
<p><a href="/contacts/new"><?= e__('contacts.index.add') ?></a></p>
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
					<td><?= htmlspecialchars((string)$contact['name'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars((string)$contact['email'], ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars((string)($contact['cell_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
					<td><?= htmlspecialchars((string)($contact['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
					<td>
						<form method="post" action="/contacts/delete" onsubmit="return confirm('Delete this contact?');">
							<input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
							<button type="submit" class="btn-table-inline"><?= e__('contacts.index.table.buttons.delete') ?></button>
						</form>
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