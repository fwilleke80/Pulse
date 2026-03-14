<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('imprint.heading') ?></h1>
<p><?= __('imprint.contact.message') ?></p>

<?php
$content = ob_get_clean();
$title = e__('imprint.title');
require __DIR__ . '/../layouts/main.php';