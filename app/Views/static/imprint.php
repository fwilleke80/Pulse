<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('imprint.heading') ?></h1>
<h2><?= e__('imprint.about.heading') ?></h2>
<p><?= e__('imprint.about.message.1') ?></p>
<p><?= e__('imprint.about.message.2') ?></p>
<h2><?= e__('imprint.contact.heading') ?></h2>
<p><?= __('imprint.contact.message') ?></p>

<?php
$content = ob_get_clean();
$title = e__('imprint.title');

require __DIR__ . '/../layouts/main.php';