<?php

declare(strict_types=1);

ob_start();
?>

<div class="card">

	<p>Please log in.</p>

	<?php if (is_array($flash)): ?>
		<div class="flash flash-<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
			<?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
		</div>
	<?php endif; ?>

	<form method="post" action="/login">

		<label for="email">Email</label>
		<input type="email" id="email" name="email" required>

		<label for="password">Password</label>
		<input type="password" id="password" name="password" required>

		<button type="submit">Log in</button>

	</form>

</div>

<?php

$content = ob_get_clean();
$title = 'Login';

require __DIR__ . '/../layouts/main.php';