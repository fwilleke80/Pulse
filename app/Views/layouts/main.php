<?php
declare(strict_types=1);

/** @var string $appName */
/** @var string $appVersion */
/** @var string $locale */
/** @var string $base_url */
/** @var string $currentTarget */
/** @var bool $isAuthenticated */
/** @var array<string, mixed>|null $currentUser */
/** @var array<string, string>|null $flash */
/** @var string $content */
/** @var string|null $title */

$assetVersion = trim((string)$appVersion) !== '' ? (string)$appVersion : 'unversioned';
$versionLabel = trim((string)$appVersion) !== ''
	? 'v' . (string)$appVersion
	: __('footer.version_unavailable');

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars((isset($title) ? ($title . " :: ") : "") . $appName, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="<?= e($base_url) ?>/assets/style.css?v=<?= e(rawurlencode($assetVersion)) ?>">
	<link rel="icon" href="<?= e($base_url) ?>/favicon.png">
	<script src="<?= e($base_url) ?>/assets/app.js?v=<?= e(rawurlencode($assetVersion)) ?>" defer></script>
</head>
<body>

<header class="main-header">
	<a href="/" class="app-title">
		<img src="<?= e($base_url) ?>/assets/logo.png" alt="<?= htmlspecialchars($appName) ?>" class="app-logo">
	</a>
</header>

<?php if (!empty($isAuthenticated)): ?>
<nav class="main-nav" aria-label="<?= e__('nav.primary') ?>">
	<a href="<?= e($base_url) ?>/"><?= e__('nav.dashboard') ?></a>
	<a href="<?= e($base_url) ?>/monitors"><?= e__('nav.monitors') ?></a>
	<a href="<?= e($base_url) ?>/contacts"><?= e__('nav.contacts') ?></a>
	<a href="<?= e($base_url) ?>/profile"><?= e__('nav.profile') ?></a>
	<form method="post" action="<?= e($base_url) ?>/logout" class="nav-form">
		<?= csrf_field() ?>
		<button type="submit" class="nav-link-button"><?= e__('nav.logout') ?></button>
	</form>
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
	<nav class="footer-nav" aria-label="<?= e__('footer.links') ?>">
		<div class="language-switcher">
			<?= e__('footer.language') ?> [
			<form method="post" action="<?= e($base_url) ?>/language/set">
				<?= csrf_field() ?>
				<input type="hidden" name="locale" value="en">
				<input type="hidden" name="redirect" value="<?= e($currentTarget) ?>">
				<button type="submit" class="link-button"><?= e__('footer.language.en') ?></button>
			</form>
			|
			<form method="post" action="<?= e($base_url) ?>/language/set">
				<?= csrf_field() ?>
				<input type="hidden" name="locale" value="de">
				<input type="hidden" name="redirect" value="<?= e($currentTarget) ?>">
				<button type="submit" class="link-button"><?= e__('footer.language.de') ?></button>
			</form>
			]
		</div>
		<a href="<?= e($base_url) ?>/about"><?= e__('footer.about') ?></a>
		<a href="<?= e($base_url) ?>/imprint"><?= e__('footer.imprint') ?></a>
	</nav>
	<p><?= e($appName) ?> <?= e($versionLabel) ?><br>&copy; <?= date('Y') ?> <a href="https://frankwilleke.de" target="_blank" rel="noopener noreferrer">frankwilleke.de</a>. <?= e__('footer.allrightsreserved') ?></p>
</footer>

</body>
</html>
