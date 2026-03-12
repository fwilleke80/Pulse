<?php

declare(strict_types=1);

ob_start();
?>

<div class="card">

	<h1><?= e__('login.heading') ?></h1>

	<p><?= e__('login.message') ?></p>

	<?php if (is_array($flash)): ?>
		<div class="flash flash-<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
			<?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
		</div>
	<?php endif; ?>

	<form method="post" action="/login">

		<label for="email"><?= e__('login.email') ?></label>
		<input type="email" id="email" name="email" required>

		<label for="password"><?= e__('login.password') ?></label>
		<input type="password" id="password" name="password" required>

		<button type="submit"><?= e__('login.submit') ?></button>

	</form>

</div>

<?php

$content = ob_get_clean();
$title = e__('login.title');

require __DIR__ . '/../layouts/main.php';