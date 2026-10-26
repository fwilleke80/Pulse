<?php

/** @file confirm.php @brief Non-destructive emergency link landing page. */

declare(strict_types=1);

/** @var string $token */
/** @var array<string, mixed>|null $warning */
ob_start();
?>
<section class="global-check-in-card emergency-card">
	<h1><?= e__('emergency.link.heading') ?></h1>
	<?php if (is_array($warning)): ?>
		<p><?= e__('emergency.link.description', ['monitor' => (string)$warning['monitor_name']]) ?></p>
		<form method="post" action="<?= e($base_url) ?>/emergency-stop/confirm">
			<?= csrf_field() ?>
			<input type="hidden" name="token" value="<?= e($token) ?>">
			<button class="btn-danger btn-emergency-stop" type="submit"><?= e__('emergency.stop.submit') ?></button>
		</form>
	<?php else: ?>
		<p><?= e__('emergency.link.invalid') ?></p>
	<?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$title = e__('emergency.link.heading');
require __DIR__ . '/../layouts/main.php';
