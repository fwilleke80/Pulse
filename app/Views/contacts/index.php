<?php

declare(strict_types=1);

ob_start();
?>

<div class="card">
	<h2>Contacts</h2>

	<p>
		<a href="/contacts/new">Add contact</a>
	</p>

	<?php if ($contacts === []): ?>
		<p>No contacts yet.</p>
	<?php else: ?>
		<table>
			<thead>
				<tr>
					<th>Name</th>
					<th>Email</th>
					<th>Cell phone</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($contacts as $contact): ?>
					<tr>
						<td><?= htmlspecialchars((string)$contact['name'], ENT_QUOTES, 'UTF-8') ?></td>
						<td><?= htmlspecialchars((string)$contact['email'], ENT_QUOTES, 'UTF-8') ?></td>
						<td><?= htmlspecialchars((string)($contact['cell_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
						<td>
							<form method="post" action="/contacts/delete" onsubmit="return confirm('Delete this contact?');">
								<input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
								<button type="submit">Delete</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<?php

$content = ob_get_clean();
$title = 'Contacts';

require __DIR__ . '/../layouts/main.php';