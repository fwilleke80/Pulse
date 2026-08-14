<?php

/**
 * @file document-unavailable.php
 * @brief Styled generic error for an unavailable recipient document.
 * @author Frank Willeke
 */

declare(strict_types=1);

ob_start();
?>
<h1><?= e__('portal.document_unavailable.heading') ?></h1>
<p><?= e__('portal.document_unavailable.message') ?></p>
<?php
$content = ob_get_clean();
$title = e__('portal.document_unavailable.title');
require __DIR__ . '/../layouts/main.php';
