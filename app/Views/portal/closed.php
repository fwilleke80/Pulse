<?php
/**
 * @file closed.php
 * @brief Confirmation shown after a recipient permanently closes portal access.
 * @author Frank Willeke
 */
declare(strict_types=1);

ob_start();
?>
<section class="portal-closed-message">
	<p class="portal-delivery-eyebrow"><?= e__('portal.closed.eyebrow') ?></p>
	<h1><?= e__('portal.closed.heading') ?></h1>
	<p><?= e__('portal.closed.message') ?></p>
</section>
<?php
$content = ob_get_clean();
$title = e__('portal.closed.title');
require __DIR__ . '/../layouts/main.php';
