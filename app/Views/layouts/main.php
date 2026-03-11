<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?= htmlspecialchars($title ?? $appName, ENT_QUOTES, 'UTF-8') ?></title>

	<link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<header>
	<h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
</header>

<main>
	<?= $content ?>
</main>

<footer>
	<div class="card">
		<p><a href="/imprint">Imprint</a></p>
		<p>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
	</div>
</footer>

</body>
</html>