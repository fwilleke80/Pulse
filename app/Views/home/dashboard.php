<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="/assets/style.css">
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