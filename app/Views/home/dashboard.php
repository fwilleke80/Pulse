<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
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
			max-width: 760px;
			background: #fff;
			padding: 1.5rem;
			border-radius: 10px;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
		}

		.flash
		{
			padding: 0.75rem 1rem;
			border-radius: 6px;
			margin-bottom: 1rem;
		}

		.flash-success
		{
			background: #e7f6ea;
			color: #146c2e;
		}

		.flash-error
		{
			background: #fce8e8;
			color: #8f1f1f;
		}
	</style>
</head>
<body>
	<div class="card">
		<h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>

		<?php if (is_array($flash)): ?>
			<div class="flash flash-<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
				<?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
			</div>
		<?php endif; ?>

		<p>Welcome<?= is_array($user) ? ', ' . htmlspecialchars((string)$user['display_name'], ENT_QUOTES, 'UTF-8') : '' ?>.</p>
		<p>This is the initial Pulse dashboard.</p>
		<p><a href="/logout">Log out</a></p>
	</div>
</body>
</html>