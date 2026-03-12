<?php

declare(strict_types=1);

ob_start();
?>
<div class="card">
	<?php if (is_array($flash)): ?>
		<div class="flash flash-<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
			<?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
		</div>
	<?php endif; ?>

	<h1><?= e__('dashboard.heading') ?></h1>

	<p><?= e__('dashboard.message.1') ?><?= is_array($user) ? ', ' . htmlspecialchars((string)$user['display_name'], ENT_QUOTES, 'UTF-8') : '' ?>.</p>

	<p><?= e__('dashboard.message.2') ?></p>
</div>

<?php
$content = ob_get_clean();
$title = e__('dashboard.title');

require __DIR__ . '/../layouts/main.php';