<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
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
		<img src="<?= e($base_url) ?>/assets/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="app-logo">
	</a>
</header>

<?php if (!empty($isAuthenticated)): ?>
<nav class="main-nav">
	<a href="<?= e($base_url) ?>/"><?= e__('nav.dashboard') ?></a>
	<a href="<?= e($base_url) ?>/contacts"><?= e__('nav.contacts') ?></a>
	<a href="<?= e($base_url) ?>/monitors"><?= e__('nav.monitors') ?></a>
	<a href="<?= e($base_url) ?>/profile"><?= e__('nav.profile') ?></a>
	<a href="<?= e($base_url) ?>/logout"><?= e__('nav.logout') ?></a>
</nav>
<?php endif; ?>

<main>
	<div class="card">
		<?php if (is_array($flash)): ?>
			<div class="flash flash-<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
				<?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
			</div>
		<?php endif; ?>
		<?= $content ?>
	</div>
</main>

<footer>
	<nav class="footer-nav">
		<span>
			<?= e__('footer.language') ?> [ <a href="<?= e($base_url) ?>/language/set?locale=en&redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/') ?>"><?= e__('footer.language.en') ?></a> | <a href="<?= e($base_url) ?>/language/set?locale=de&redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/') ?>"><?= e__('footer.language.de') ?></a> ]
		</span>
		<a href="<?= e($base_url) ?>/about"><?= e__('footer.about') ?></a>
		<a href="<?= e($base_url) ?>/imprint"><?= e__('footer.imprint') ?></a>
	</nav>
	<p><?= htmlspecialchars($appName) ?> v<?= e((string)$appVersion) ?><br/>&copy; <?= date('Y') ?> <a href="https://frankwilleke.de" target="_blank">frankwilleke.de</a>. <?= e__('footer.allrightsreserved') ?></p>
</footer>

</body>
</html>