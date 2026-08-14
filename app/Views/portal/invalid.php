<?php
/**
 * @file invalid.php
 * @brief Generic unavailable-recipient-portal page.
 * @author Frank Willeke
 */
declare(strict_types=1);

ob_start();
?>
<h1><?= e__('portal.invalid.heading') ?></h1>
<p><?= e__('portal.invalid.message') ?></p>
<?php
$content = ob_get_clean();
$title = e__('portal.invalid.title');
require __DIR__ . '/../layouts/main.php';
