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
		<p>Bootstrap completed successfully.</p>
		<p>
			Database status:
			<span class="<?= $databaseOk ? 'status-ok' : 'status-fail' ?>">
				<?= $databaseOk ? 'connected' : 'not connected' ?>
			</span>
		</p>
		<p>PHP version: <?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?></p>
		<p><a href="/login">Login</a> | <a href="/health">Health check</a></p>
	</div>
</body>
</html>