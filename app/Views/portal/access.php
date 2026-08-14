<?php
/**
 * @file access.php
 * @brief Calm authenticated recipient portal with a visual immutable document snapshot.
 * @author Frank Willeke
 */
declare(strict_types=1);

/** @var array<string, mixed> $delivery */
/** @var array<int, array<string, mixed>> $documents */
/** @var string $token */
/** @var string $base_url */
/** @var int $availableDocumentCount */
/** @var int $totalDownloadBytes */

$portalIntro = trim((string)($delivery['portal_intro_text'] ?? ''));

if ($portalIntro === '')
{
	$portalIntro = __('portal.access.intro');
}

$formatSize = static function (int $bytes): string
{
	$bytes = max(0, $bytes);

	if ($bytes < 1024)
	{
		return $bytes . ' B';
	}

	if ($bytes < 1024 * 1024)
	{
		return number_format($bytes / 1024, 1) . ' KB';
	}

	if ($bytes < 1024 * 1024 * 1024)
	{
		return number_format($bytes / (1024 * 1024), 1) . ' MB';
	}

	return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';
};

ob_start();
?>
<header class="portal-delivery-hero">
	<p class="portal-delivery-eyebrow"><?= e__('portal.access.eyebrow') ?></p>
	<h1><?= e__('portal.access.heading_owner', ['owner' => (string)$delivery['owner_name']]) ?></h1>
	<p class="portal-delivery-intro"><?= nl2br(e($portalIntro)) ?></p>
	<?php if (!empty($delivery['portal_expires_at'])): ?>
		<p class="portal-availability-note"><?= e__('portal.access.available_until', ['date' => format_datetime((string)$delivery['portal_expires_at'])]) ?></p>
	<?php endif; ?>
</header>

<?php if (trim((string)($delivery['portal_message_text'] ?? '')) !== ''): ?>
	<section class="portal-message-card" aria-labelledby="portal-message-heading">
		<h2 id="portal-message-heading"><?= e__('portal.access.message.heading_owner', ['owner' => (string)$delivery['owner_name']]) ?></h2>
		<div class="portal-message-body"><?= nl2br(e((string)$delivery['portal_message_text'])) ?></div>
	</section>
<?php endif; ?>

<section class="portal-documents-section" aria-labelledby="portal-documents-heading">
	<div class="portal-documents-heading-row">
		<div>
			<h2 id="portal-documents-heading"><?= e__('portal.documents.heading') ?></h2>
			<p><?= e__('portal.documents.intro_warm', ['count' => count($documents)]) ?></p>
		</div>
		<?php if ($availableDocumentCount > 0): ?>
			<a class="btn-primary portal-download-all" href="<?= e($base_url) ?>/portal/documents/download-all?token=<?= e(rawurlencode($token)) ?>">
				<span><?= e__('portal.documents.download_all') ?></span>
				<small><?= e__('portal.documents.download_all_summary', [
					'count' => $availableDocumentCount,
					'size' => $formatSize($totalDownloadBytes),
				]) ?></small>
			</a>
		<?php endif; ?>
	</div>

	<?php if ($documents === []): ?>
		<div class="configuration-block portal-empty-documents">
			<p><?= e__('portal.documents.none') ?></p>
		</div>
	<?php else: ?>
		<div class="portal-document-grid">
			<?php foreach ($documents as $document): ?>
				<?php
				$viewUrl = $base_url . '/portal/document/view?token=' . rawurlencode($token) . '&document=' . (int)$document['id'];
				$downloadUrl = $base_url . '/portal/document/download?token=' . rawurlencode($token) . '&document=' . (int)$document['id'];
				?>
				<article class="portal-document-card">
					<?php if (!empty($document['image_preview'])): ?>
						<a class="portal-document-preview portal-document-preview-image" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e__('portal.documents.view_named', ['name' => (string)$document['title']]) ?>">
							<img src="<?= e($viewUrl) ?>" alt="<?= e((string)$document['title']) ?>" loading="lazy" decoding="async">
						</a>
					<?php else: ?>
						<div class="portal-document-preview portal-document-preview-type" aria-hidden="true">
							<span><?= e((string)$document['type_label']) ?></span>
						</div>
					<?php endif; ?>

					<div class="portal-document-content">
						<h3><?= e((string)$document['title']) ?></h3>
						<?php if (trim((string)($document['description'] ?? '')) !== ''): ?>
							<p class="portal-document-description"><?= nl2br(e((string)$document['description'])) ?></p>
						<?php endif; ?>
						<div class="portal-document-meta">
							<span><?= e((string)$document['type_label']) ?></span>
							<span aria-hidden="true">·</span>
							<span><?= e($formatSize((int)$document['size_bytes'])) ?></span>
						</div>
					</div>

					<div class="portal-document-actions">
						<?php if (!empty($document['view_available'])): ?>
							<a class="button-link" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e__('portal.documents.view') ?></a>
						<?php endif; ?>
						<?php if (!empty($document['download_available'])): ?>
							<a class="button-link" href="<?= e($downloadUrl) ?>"><?= e__('portal.documents.download') ?></a>
						<?php else: ?>
							<span class="table-warning table-warning-critical"><?= e__('portal.documents.unavailable') ?></span>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ($availableDocumentCount > 0 && $totalDownloadBytes >= 536870912): ?>
		<p class="form-hint portal-large-download-note"><?= e__('portal.documents.large_download_note') ?></p>
	<?php endif; ?>
</section>

<p class="form-hint portal-security-note"><?= e__('portal.documents.security_note') ?></p>

<?php if (empty($delivery['portal_expires_at'])): ?>
	<section class="portal-close-access" aria-labelledby="portal-close-access-heading">
		<h2 id="portal-close-access-heading"><?= e__('portal.close.link_heading') ?></h2>
		<p><?= e__('portal.close.link_hint') ?></p>
		<a class="danger-link" href="<?= e($base_url) ?>/portal/close?token=<?= e(rawurlencode($token)) ?>"><?= e__('portal.close.link') ?></a>
	</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = e__('portal.access.title');
require __DIR__ . '/../layouts/main.php';
