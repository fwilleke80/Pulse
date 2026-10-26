<?php

/** @file result.php @brief Emergency stop outcome with no bearer-token disclosure. */

declare(strict_types=1);

/** @var bool $stopped */
ob_start();
?>
<h1><?= e__('emergency.link.heading') ?></h1>
<p role="status"><?= e__($stopped ? 'emergency.link.stopped' : 'emergency.link.invalid') ?></p>
<?php
$content = ob_get_clean();
$title = e__('emergency.link.heading');
require __DIR__ . '/../layouts/main.php';
