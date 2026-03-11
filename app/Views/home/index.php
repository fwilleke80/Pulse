<?php

declare(strict_types=1);

ob_start();
?>
<div class="card">
	<h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
	<p>Bootstrap completed successfully.</p>
	<p>
		Database status:
		<span class="<?= $databaseOk ? 'status-ok' : 'status-fail' ?>">
			<?= $databaseOk ? 'connected' : 'not connected' ?>
		</span>
	</p>
	<p>PHP version: <?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?></p>
	<p><a href="/login">Login</a> | <a href="/health">Health check</a></p>
</div>

<?php
$content = ob_get_clean();
$title = 'Home';

require __DIR__ . '/../layouts/main.php';