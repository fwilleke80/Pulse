<?php

/**
 * @file document-preview.php
 * @brief Minimal same-origin frame for safe text, Markdown, CSV, and JSON previews.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $document */
/** @var array<string, mixed> $preview */
/** @var string $base_url */
/** @var string $appVersion */
/** @var string $locale */

$kind = (string)($preview['kind'] ?? 'text');
$content = (string)($preview['content'] ?? '');
$rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
$assetVersion = trim((string)$appVersion) !== '' ? (string)$appVersion : 'unversioned';
?><!DOCTYPE html>
<html lang="<?= e($locale) ?>" class="portal-preview-document">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= e((string)($document['title'] ?? __('portal.documents.view'))) ?></title>
	<link rel="stylesheet" href="<?= e($base_url) ?>/assets/style.css?v=<?= e(rawurlencode($assetVersion)) ?>">
</head>
<body class="portal-document-frame-body">
	<main class="portal-document-frame-content">
		<?php if ($kind === 'markdown'): ?>
			<article class="markdown-content"><?= markdown_html($content) ?></article>
		<?php elseif ($kind === 'csv'): ?>
			<?php if ($rows === []): ?>
				<p class="portal-document-frame-empty"><?= e__('portal.documents.preview_empty') ?></p>
			<?php else: ?>
				<div class="portal-csv-preview-scroll">
					<table class="portal-csv-preview">
						<tbody>
							<?php foreach ($rows as $rowIndex => $row): ?>
								<tr>
									<?php foreach ((array)$row as $cell): ?>
										<?php if ($rowIndex === 0): ?>
											<th scope="col"><?= nl2br(e((string)$cell)) ?></th>
										<?php else: ?>
											<td><?= nl2br(e((string)$cell)) ?></td>
										<?php endif; ?>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php else: ?>
			<pre class="portal-plain-preview"><code><?= e($content) ?></code></pre>
		<?php endif; ?>

		<?php if (!empty($preview['truncated'])): ?>
			<p class="portal-document-preview-truncated"><?= e__('portal.documents.preview_truncated') ?></p>
		<?php endif; ?>
	</main>
</body>
</html>
