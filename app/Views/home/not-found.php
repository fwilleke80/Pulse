<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Not Found - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
	<div class="card">
		<h1>404</h1>
		<p>The requested page was not found.</p>
		<p><a href="/">Back to <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></a></p>
	</div>
</body>
</html>