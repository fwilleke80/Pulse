<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
	<style>
		body
		{
			font-family: Arial, sans-serif;
			margin: 2rem;
			background: #f7f7f7;
			color: #222;
		}

		.card
		{
			max-width: 460px;
			background: #fff;
			padding: 1.5rem;
			border-radius: 10px;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
		}

		label
		{
			display: block;
			margin-top: 1rem;
			font-weight: bold;
		}

		input
		{
			width: 100%;
			padding: 0.7rem;
			margin-top: 0.35rem;
			box-sizing: border-box;
		}

		button
		{
			margin-top: 1rem;
			padding: 0.75rem 1rem;
		}

		.flash
		{
			padding: 0.75rem 1rem;
			border-radius: 6px;
			margin-bottom: 1rem;
		}

		.flash-error
		{
			background: #fce8e8;
			color: #8f1f1f;
		}

		.flash-success
		{
			background: #e7f6ea;
			color: #146c2e;
		}
	</style>
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