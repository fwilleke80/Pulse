<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars((isset($title) ? ($title . " :: ") : "") . $appName, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="/assets/style.css">
	<link rel="icon" href="/favicon.png">
</head>
<body>

<header class="main-header">
	<a href="/" class="app-title">
		<img src="/assets/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="app-logo">
	</a>
</header>

<?php if (!empty($isAuthenticated)): ?>
<nav class="main-nav">
	<a href="/"><?= e__('nav.dashboard') ?></a>
	<a href="/contacts"><?= e__('nav.contacts') ?></a>
	<a href="/periods"><?= e__('nav.periods') ?></a>
	<a href="/profile"><?= e__('nav.profile') ?></a>
	<a href="/logout"><?= e__('nav.logout') ?></a>
</nav>
<?php endif; ?>

<main>
	<?= $content ?>
</main>

<footer>
	<nav class="footer-nav">
		<a href="/imprint"><?= e__('footer.imprint') ?></a>
		<span>
			<?= e__('footer.language') ?> [ <a href="/language/set?locale=en"><?= e__('footer.language.en') ?></a> | <a href="/language/set?locale=de"><?= e__('footer.language.de') ?></a> ]
		</span>
	</nav>
	<p><?= htmlspecialchars($appName) ?> v<?= htmlspecialchars($appVersion) ?> &copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>. <?= e__('footer.allrightsreserved') ?></p>
</footer>

</body>
</html>