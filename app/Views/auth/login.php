<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
	<div class="card">
		<h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
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
</body>
</html>