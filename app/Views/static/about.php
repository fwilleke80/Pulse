<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('about.heading') ?></h1>
<p><?= e__('about.message.1') ?></p>
<p><?= e__('about.message.2') ?></p>

<?php
$content = ob_get_clean();
$title = e__('about.title');
require __DIR__ . '/../layouts/main.php';