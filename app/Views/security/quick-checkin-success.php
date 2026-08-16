<?php

/**
 * @file quick-checkin-success.php
 * @brief Confirmation page after global quick check-in.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var int $count */

ob_start();
?>

<h1><?= e__('quick_checkin.success_heading') ?></h1>
<p><?= e__($count === 1 ? 'quick_checkin.success_one' : 'quick_checkin.success_many', ['count' => $count]) ?></p>

<?php
$content = ob_get_clean();
$title = __('quick_checkin.title');
require __DIR__ . '/../layouts/main.php';
