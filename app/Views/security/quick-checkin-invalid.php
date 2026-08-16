<?php

/**
 * @file quick-checkin-invalid.php
 * @brief Expired or unavailable quick-check-in link page.
 * @author Frank Willeke
 */

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('quick_checkin.invalid_heading') ?></h1>
<p><?= e__('quick_checkin.invalid') ?></p>

<?php
$content = ob_get_clean();
$title = __('quick_checkin.title');
require __DIR__ . '/../layouts/main.php';
