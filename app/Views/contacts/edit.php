<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('contacts.edit.heading') ?></h1>
<form method="post" action="<?= e($base_url) ?>/contacts/update">
	<input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">

	<label for="name"><?= e__('contacts.edit.name') ?></label>
	<input
		type="text"
		id="name"
		name="name"
		value="<?= htmlspecialchars((string)$contact['name'], ENT_QUOTES, 'UTF-8') ?>"
		required>

	<label for="email"><?= e__('contacts.edit.email') ?></label>
	<input
		type="email"
		id="email"
		name="email"
		value="<?= htmlspecialchars((string)$contact['email'], ENT_QUOTES, 'UTF-8') ?>"
		required>

	<label for="cell_phone"><?= e__('contacts.edit.cell_phone') ?></label>
	<input
		type="text"
		id="cell_phone"
		name="cell_phone"
		value="<?= htmlspecialchars((string)($contact['cell_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

	<label for="notes"><?= e__('contacts.edit.notes') ?></label>
	<input
		type="text"
		id="notes"
		name="notes"
		value="<?= htmlspecialchars((string)($contact['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

	<button type="submit"><?= e__('contacts.edit.submit') ?></button>
</form>

<p><a href="<?= e($base_url) ?>/contacts"><?= e__('contacts.edit.back') ?></a></p>

<?php
$content = ob_get_clean();
$title = e__('contacts.edit.title');
require __DIR__ . '/../layouts/main.php';