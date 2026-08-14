<?php
/**
 * @file access.php
 * @brief Authenticated recipient portal with the immutable released-document snapshot.
 * @author Frank Willeke
 */
declare(strict_types=1);

/** @var array<string, mixed> $delivery */
/** @var array<int, array<string, mixed>> $documents */
/** @var string $token */
/** @var string $base_url */

$availableDocumentCount = count(array_filter(
	$documents,
	static fn (array $document): bool => !empty($document['download_available'])
));
$formatSize = static function (?int $bytes): string
{
	$bytes = max(0, (int)$bytes);

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
<div class="portal-delivery-heading">
	<div>
		<h1><?= e__('portal.access.heading') ?></h1>
		<p><?= e__('portal.access.intro', ['owner' => (string)$delivery['owner_name']]) ?></p>
	</div>
	<?php if (!empty($delivery['portal_expires_at'])): ?>
		<span class="portal-expiry-badge"><?= e__('portal.access.available_until', ['date' => format_datetime((string)$delivery['portal_expires_at'])]) ?></span>
	<?php else: ?>
		<span class="portal-expiry-badge"><?= e__('portal.access.no_expiry') ?></span>
	<?php endif; ?>
</div>

<div class="configuration-block">
	<dl class="recipient-summary-list">
		<div><dt><?= e__('portal.access.from') ?></dt><dd><?= e((string)$delivery['owner_name']) ?></dd></div>
		<div><dt><?= e__('portal.access.monitor') ?></dt><dd><?= e((string)$delivery['monitor_name']) ?></dd></div>
	</dl>
</div>

<?php if (trim((string)($delivery['message_body'] ?? '')) !== ''): ?>
	<section class="portal-message-card" aria-labelledby="portal-message-heading">
		<h2 id="portal-message-heading"><?= e__('portal.access.message.heading') ?></h2>
		<?php if (trim((string)($delivery['message_subject'] ?? '')) !== ''): ?>
			<strong class="portal-message-subject"><?= e((string)$delivery['message_subject']) ?></strong>
		<?php endif; ?>
		<div class="portal-message-body"><?= nl2br(e((string)$delivery['message_body'])) ?></div>
	</section>
<?php endif; ?>

<section class="portal-documents-section" aria-labelledby="portal-documents-heading">
	<div class="section-heading portal-documents-heading-row">
		<div>
			<h2 id="portal-documents-heading"><?= e__('portal.documents.heading') ?></h2>
			<p><?= e__('portal.documents.intro', ['count' => count($documents)]) ?></p>
		</div>
		<?php if ($availableDocumentCount > 0): ?>
			<a class="button-link" href="<?= e($base_url) ?>/portal/documents/download-all?token=<?= e(rawurlencode($token)) ?>"><?= e__('portal.documents.download_all') ?></a>
		<?php endif; ?>
	</div>

	<?php if ($documents === []): ?>
		<div class="configuration-block portal-empty-documents">
			<p><?= e__('portal.documents.none') ?></p>
		</div>
	<?php else: ?>
		<div class="portal-document-list">
			<?php foreach ($documents as $document): ?>
				<article class="portal-document-card">
					<div class="portal-document-main">
						<div class="portal-document-title-row">
							<h3><?= e((string)$document['title']) ?></h3>
							<span class="mini-status"><?= e__((string)$document['storage_type'] === 'text' ? 'portal.documents.type.text' : 'portal.documents.type.file') ?></span>
						</div>
						<?php if (trim((string)($document['description'] ?? '')) !== ''): ?>
							<p class="portal-document-description"><?= nl2br(e((string)$document['description'])) ?></p>
						<?php endif; ?>
						<div class="portal-document-meta">
							<?php if ((string)$document['storage_type'] === 'file'): ?>
								<span><?= e($formatSize(isset($document['file_size_bytes']) ? (int)$document['file_size_bytes'] : null)) ?></span>
								<?php if (!empty($document['original_filename'])): ?>
									<span><?= e__('portal.documents.original_file', ['name' => (string)$document['original_filename']]) ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</div>

						<?php if ((string)$document['storage_type'] === 'text' && trim((string)($document['text_content'] ?? '')) !== ''): ?>
							<details class="portal-text-document">
								<summary><?= e__('portal.documents.read_text') ?></summary>
								<div class="portal-text-content"><?= nl2br(e((string)$document['text_content'])) ?></div>
							</details>
						<?php endif; ?>
					</div>
					<div class="portal-document-actions">
						<?php if (!empty($document['download_available'])): ?>
							<a class="button-link" href="<?= e($base_url) ?>/portal/document/download?token=<?= e(rawurlencode($token)) ?>&amp;document=<?= (int)$document['id'] ?>"><?= e__('portal.documents.download') ?></a>
						<?php else: ?>
							<span class="table-warning table-warning-critical"><?= e__('portal.documents.unavailable') ?></span>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<p class="form-hint portal-security-note"><?= e__('portal.documents.security_note') ?></p>
<?php
$content = ob_get_clean();
$title = e__('portal.access.title');
require __DIR__ . '/../layouts/main.php';
