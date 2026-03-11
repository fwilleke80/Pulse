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

	<p>Welcome<?= is_array($user) ? ', ' . htmlspecialchars((string)$user['display_name'], ENT_QUOTES, 'UTF-8') : '' ?>.</p>

	<p>This is the initial Pulse dashboard.</p>
</div>

<?php
$content = ob_get_clean();
$title = 'Dashboard';

require __DIR__ . '/../layouts/main.php';