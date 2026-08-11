<?php

/**
 * @file error.php
 * @brief Generic user-safe request error page.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var string $heading */
/** @var string $message */

ob_start();
?>

<h1><?= e($heading) ?></h1>
<p><?= e($message) ?></p>
<p><a href="/login"><?= e__('nav.login') ?></a></p>

<?php
$content = ob_get_clean();
$title = $heading;
require __DIR__ . '/../layouts/main.php';
