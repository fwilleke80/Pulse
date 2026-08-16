<?php
/**
 * @file text-document.php
 * @brief Rendered recipient Markdown document view.
 * @author Frank Willeke
 */
declare(strict_types=1);

/** @var array<string, mixed> $document */
/** @var string $token */
/** @var string $base_url */

ob_start();
?>
<article class="portal-text-document">
	<header class="portal-text-document-header">
		<div>
			<p class="portal-delivery-eyebrow"><?= e__('portal.documents.type.markdown') ?></p>
			<h1><?= e((string)$document['title']) ?></h1>
		</div>
		<a class="button-link" href="<?= e($base_url) ?>/portal/document/download?token=<?= e(rawurlencode($token)) ?>&amp;document=<?= (int)$document['id'] ?>"><?= e__('portal.documents.download_markdown') ?></a>
	</header>
	<div class="portal-text-document-body markdown-content">
		<?= markdown_html((string)($document['text_content'] ?? '')) ?>
	</div>
</article>
<?php
$content = ob_get_clean();
$title = (string)$document['title'];
require __DIR__ . '/../layouts/main.php';
