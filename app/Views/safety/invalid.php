<?php

/**
 * @file invalid.php
 * @brief Invalid or expired safety-contact link.
 * @author Frank Willeke
 */

declare(strict_types=1);

ob_start();
?>

<div class="safety-response-page">
	<h1><?= e__('safety.invalid.heading') ?></h1>
	<p><?= e__('safety.invalid.message') ?></p>
</div>

<?php
$content = ob_get_clean();
$title = e__('safety.invalid.title');
require __DIR__ . '/../layouts/main.php';
