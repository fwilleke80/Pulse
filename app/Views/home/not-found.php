<?php

/**
 * @file not-found.php
 * @brief 404 Not Found page view.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var string $base_url */

ob_start();
?>
<h1><?= e__('404.heading') ?></h1>
<p><?= e__('404.message') ?></p>
<p><a href="<?= e($base_url) ?>/"><?= e__('nav.backto') . ' ' . e__('home.heading') ?></a></p>

<?php
$content = ob_get_clean();
$title = e__('404.title');
require __DIR__ . '/../layouts/main.php';