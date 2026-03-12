<?php

declare(strict_types=1);
ob_start();
?>
<h1><?= e__('404.heading') ?></h1>
<p><?= e__('404.message') ?></p>
<p><a href="/"><?= e__('nav.backto') . ' ' . e__('home.heading') ?></a></p>

<?php
$content = ob_get_clean();
$title = e__('404.title');

require __DIR__ . '/../layouts/main.php';