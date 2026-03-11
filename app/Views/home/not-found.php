<?php

declare(strict_types=1);
ob_start();
?>
<div class="card">
	<h1>404</h1>
	<p>The requested page was not found.</p>
	<p><a href="/">Back to <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></a></p>
</div>

<?php
$content = ob_get_clean();
$title = 'Not found';

require __DIR__ . '/../layouts/main.php';