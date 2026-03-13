<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('login.heading') ?></h1>
<p><?= e__('login.message') ?></p>
<form method="post" action="/login">
	<label for="email"><?= e__('login.email') ?></label>
	<input type="email" id="email" name="email" required>
	<label for="password"><?= e__('login.password') ?></label>
	<input type="password" id="password" name="password" required>
	<button type="submit"><?= e__('login.submit') ?></button>
</form>

<?php
$content = ob_get_clean();
$title = e__('login.title');
require __DIR__ . '/../layouts/main.php';