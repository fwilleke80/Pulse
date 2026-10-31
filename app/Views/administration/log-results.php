<?php
/**
 * @file log-results.php
 * @brief Read-only, escaped application-log rows with bounded pagination.
 */

declare(strict_types=1);

/** @var array{entries:array<int,array{timestamp:string,level:string,message:string}>,has_more:bool,available:bool}|null $logResult */
/** @var string $logLevel */
/** @var string $logSearch */
/** @var int $logPage */
/** @var string $base_url */
$query = http_build_query(['level' => $logLevel, 'search' => $logSearch]);
$basePath = $base_url . '/administration/maintenance/logs?' . $query;
?>
<?php if ($logResult === null || !$logResult['available']): ?>
	<p class="form-hint"><?= e__('administration.logs.unavailable') ?></p>
<?php elseif ($logResult['entries'] === []): ?>
	<p class="form-hint"><?= e__('administration.logs.empty') ?></p>
<?php else: ?>
	<ol class="administration-log-list">
		<?php foreach ($logResult['entries'] as $entry): ?>
			<li class="administration-log-entry" data-level="<?= e($entry['level']) ?>">
				<div class="administration-log-entry-header">
					<strong><?= e($entry['level']) ?></strong>
					<time datetime="<?= e($entry['timestamp']) ?>"><?= e($entry['timestamp']) ?></time>
				</div>
				<pre><?= e($entry['message']) ?></pre>
			</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>
<?php if ($logPage > 1 || ($logResult['has_more'] ?? false)): ?>
	<nav class="administration-log-pagination" aria-label="<?= e__('administration.logs.pages') ?>">
		<?php if ($logPage > 1): ?>
			<a href="<?= e($basePath . '&page=' . ($logPage - 1)) ?>"><?= e__('administration.logs.previous') ?></a>
		<?php else: ?><span></span><?php endif; ?>
		<?php if ($logResult['has_more'] ?? false): ?>
			<a href="<?= e($basePath . '&page=' . ($logPage + 1)) ?>"><?= e__('administration.logs.next') ?></a>
		<?php endif; ?>
	</nav>
<?php endif; ?>
