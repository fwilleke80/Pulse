<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('contacts.add.heading') ?></h1>
<form method="post" action="<?= e($base_url) ?>/contacts/create">
	<label for="name"><?= e__('contacts.add.name') ?></label>
	<input type="text" id="name" name="name" required>
	<label for="email"><?= e__('contacts.add.email') ?></label>
	<input type="email" id="email" name="email" required>
	<label for="cell_phone"><?= e__('contacts.add.cell_phone') ?></label>
	<input type="text" id="cell_phone" name="cell_phone">
	<label for="notes"><?= e__('contacts.add.notes') ?></label>
	<input type="text" id="notes" name="notes">
	<button type="submit"><?= e__('contacts.add.submit') ?></button>
</form>

<?php
$content = ob_get_clean();
$title = e__('contacts.add.title');
require __DIR__ . '/../layouts/main.php';