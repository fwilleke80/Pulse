<?php

/**
 * @file result.php
 * @brief Safety-contact response result.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var string $result */

ob_start();
?>

<div class="safety-response-page">
	<h1><?= e__('safety.result.heading') ?></h1>
	<p><?= e__('safety.result.' . $result) ?></p>
	<div class="privacy-note"><?= e__('safety.result.no_release') ?></div>
</div>

<?php
$content = ob_get_clean();
$title = e__('safety.result.title');
require __DIR__ . '/../layouts/main.php';
