<?php

declare(strict_types=1);

ob_start();
?>

<h1><?= e__('dashboard.heading') ?></h1>
<p><?= e__('dashboard.message.1', ['name' => $user['display_name']]) ?></p>
<p><?= e__('dashboard.message.2') ?></p>
<div class="dashboard-stats">

	<a href="<?= e($base_url) ?>/contacts" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.contacts') ?></div>
		<div class="dashboard-stat-value"><?= (int)$contactCount ?></div>
	</a>

	<a href="#" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.monitors') ?></div>
		<div class="dashboard-stat-value">0</div>
	</a>

	<a href="#" class="dashboard-stat">
		<div class="dashboard-stat-title"><?= e__('dashboard.stats.documents') ?></div>
		<div class="dashboard-stat-value">0</div>
	</a>

</div>

<?php
$content = ob_get_clean();
$title = e__('dashboard.title');
require __DIR__ . '/../layouts/main.php';