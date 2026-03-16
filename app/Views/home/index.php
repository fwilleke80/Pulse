<?php

/**
 * @file index.php
 * @brief Home page view.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var bool $databaseOk */
/** @var string $phpVersion */
/** @var string $base_url */

ob_start();
?>

<h1><?= e__('home.heading') ?></h1>
<p><?= e__('home.message') ?></p>
<p>
	Database status:
	<span class="<?= $databaseOk ? 'status-ok' : 'status-fail' ?>">
		<?= $databaseOk ? 'connected' : 'not connected' ?>
	</span>
</p>
<p>PHP version: <?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?></p>
<p><a href="<?= e($base_url) ?>/login"><?= e__('login.title') ?></a> | <a href="<?= e($base_url) ?>/health">Health check</a></p>

<?php
$content = ob_get_clean();
$title = e__('home.title');
require __DIR__ . '/../layouts/main.php';