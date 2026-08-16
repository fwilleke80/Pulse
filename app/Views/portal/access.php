<?php
/**
 * @file access.php
 * @brief Calm authenticated recipient portal with a visual immutable document snapshot.
 * @author Frank Willeke
 */
declare(strict_types=1);

/** @var array<string, mixed> $delivery */
/** @var array<int, array<string, mixed>> $documents */
/** @var array<int, array<string, mixed>> $locations */
/** @var string $token */
/** @var string $base_url */
/** @var int $availableDocumentCount */
/** @var int $totalDownloadBytes */
/** @var string $openStreetMapUrl */
/** @var bool|null $previewMode */
/** @var string|null $previewRecipientName */

$previewMode = !empty($previewMode);
$previewRecipientName = (string)($previewRecipientName ?? '');

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
<?php if ($previewMode): ?>
	<section class="portal-preview-banner" role="status">
		<div>
			<p class="portal-delivery-eyebrow"><?= e__('recipients.portal_preview.eyebrow') ?></p>
			<strong><?= e__('recipients.portal_preview.preview_heading') ?></strong>
			<p><?= e__('recipients.portal_preview.message', ['name' => $previewRecipientName]) ?></p>
		</div>
		<button type="button" class="button-link" data-window-close><?= e__('recipients.portal_preview.close') ?></button>
	</section>
<?php endif; ?>

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
		<div class="portal-message-body markdown-content"><?= markdown_html((string)$delivery['portal_message_text']) ?></div>
	</section>
<?php endif; ?>

<section class="portal-documents-section" aria-labelledby="portal-documents-heading">
	<div class="portal-documents-heading-row">
		<div>
			<h2 id="portal-documents-heading"><?= e__('portal.documents.heading') ?></h2>
			<p><?= e__('portal.documents.intro_warm', ['count' => count($documents)]) ?></p>
		</div>
		<?php if ($availableDocumentCount > 0 && $previewMode): ?>
			<span class="btn-primary portal-download-all is-disabled" aria-disabled="true">
				<span><?= e__('portal.documents.download_all') ?></span>
				<small><?= e__('portal.documents.download_all_summary', [
					'count' => $availableDocumentCount,
					'size' => $formatSize($totalDownloadBytes),
				]) ?></small>
			</span>
		<?php elseif ($availableDocumentCount > 0): ?>
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
				$viewUrl = $previewMode ? '' : $base_url . '/portal/document/view?token=' . rawurlencode($token) . '&document=' . (int)$document['id'];
				$downloadUrl = $previewMode ? '' : $base_url . '/portal/document/download?token=' . rawurlencode($token) . '&document=' . (int)$document['id'];
				$previewUrl = $previewMode && !empty($document['preview_asset_url'])
					? $base_url . (string)$document['preview_asset_url']
					: $viewUrl;
				$previewKind = (string)($document['preview_kind'] ?? 'download');
				$previewId = 'portal-document-preview-' . (int)$document['id'];
				$isFramedPreview = in_array($previewKind, ['pdf', 'markdown', 'text', 'csv', 'json'], true);
				$isExpandableVisual = in_array($previewKind, ['image', 'video'], true);
				$showPreviewToggle = !empty($document['view_available']) && ($isFramedPreview || $isExpandableVisual);
				?>
				<article class="portal-document-card" data-document-card data-preview-kind="<?= e($previewKind) ?>">
					<?php if ((string)($document['storage_type'] ?? '') === 'text'): ?>
						<div class="portal-document-preview portal-document-preview-text markdown-content" aria-label="<?= e__('portal.documents.preview_named', ['name' => (string)$document['title']]) ?>">
							<div><?= markdown_html((string)($document['text_content'] ?? '')) ?></div>
						</div>
					<?php elseif ($previewKind === 'image' && !empty($document['view_available'])): ?>
						<div id="<?= e($previewId) ?>" class="portal-document-preview portal-document-preview-image">
							<img src="<?= e($previewUrl) ?>" alt="<?= e((string)$document['title']) ?>" loading="lazy" decoding="async">
						</div>
					<?php elseif ($previewKind === 'audio' && !empty($document['view_available'])): ?>
						<div class="portal-document-preview portal-document-preview-audio">
							<span class="portal-media-type" aria-hidden="true"><?= e((string)$document['type_label']) ?></span>
							<audio controls preload="metadata" src="<?= e($previewUrl) ?>"><?= e__('portal.documents.media_unsupported') ?></audio>
						</div>
					<?php elseif ($previewKind === 'video' && !empty($document['view_available'])): ?>
						<div id="<?= e($previewId) ?>" class="portal-document-preview portal-document-preview-video">
							<video controls preload="metadata" playsinline src="<?= e($previewUrl) ?>"><?= e__('portal.documents.media_unsupported') ?></video>
						</div>
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
						<?php if ($showPreviewToggle): ?>
							<button
								type="button"
								class="button-link"
								data-document-preview-toggle
								data-preview-mode="<?= e($isFramedPreview ? 'frame' : 'visual') ?>"
								data-show-label="<?= e__($isFramedPreview ? 'portal.documents.show_preview' : 'portal.documents.enlarge') ?>"
								data-hide-label="<?= e__($isFramedPreview ? 'portal.documents.hide_preview' : 'portal.documents.reduce') ?>"
								aria-expanded="false"
								aria-controls="<?= e($previewId) ?>"
							><?= e__($isFramedPreview ? 'portal.documents.show_preview' : 'portal.documents.enlarge') ?></button>
							<?php if ($isFramedPreview): ?>
								<noscript><a class="button-link" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e__('portal.documents.view') ?></a></noscript>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ($previewMode && !empty($document['download_available'])): ?>
							<span class="button-link is-disabled" aria-disabled="true"><?= e__('portal.documents.download') ?></span>
						<?php elseif (!empty($document['download_available'])): ?>
							<a class="button-link" href="<?= e($downloadUrl) ?>"><?= e__('portal.documents.download') ?></a>
						<?php else: ?>
							<span class="table-warning table-warning-critical"><?= e__('portal.documents.unavailable') ?></span>
						<?php endif; ?>
					</div>

					<?php if ($isFramedPreview && !empty($document['view_available'])): ?>
						<div id="<?= e($previewId) ?>" class="portal-document-inline-panel" data-document-preview-panel hidden>
							<p class="portal-document-preview-loading" data-document-preview-loading><?= e__('portal.documents.preview_loading') ?></p>
							<iframe
								class="portal-document-frame"
								data-document-preview-frame
								data-preview-src="<?= e($previewUrl) ?>"
								title="<?= e__('portal.documents.preview_named', ['name' => (string)$document['title']]) ?>"
								loading="lazy"
								referrerpolicy="no-referrer"<?= $previewKind === 'pdf' ? '' : ' sandbox="allow-same-origin"' ?>
							></iframe>
						</div>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ($availableDocumentCount > 0 && $totalDownloadBytes >= 536870912): ?>
		<p class="form-hint portal-large-download-note"><?= e__('portal.documents.large_download_note') ?></p>
	<?php endif; ?>
</section>

<p class="form-hint portal-security-note"><?= e__($previewMode ? 'recipients.portal_preview.actions_disabled' : 'portal.documents.security_note') ?></p>

<?php if ($locations !== []): ?>
	<section class="portal-location-section" aria-labelledby="portal-location-heading">
		<div class="portal-documents-heading-row">
			<div>
				<h2 id="portal-location-heading"><?= e__('portal.locations.heading') ?></h2>
				<p><?= e__(
					$previewMode
						? (count($locations) === 1 ? 'recipients.portal_preview.locations.one' : 'recipients.portal_preview.locations.many')
						: (count($locations) === 1 ? 'portal.locations.intro.one' : 'portal.locations.intro.many'),
					['count' => count($locations)]
				) ?></p>
			</div>
		</div>

		<div class="table-scroll">
			<table class="monitor-table portal-location-table">
				<thead>
					<tr>
						<th><?= e__('portal.locations.column.location') ?></th>
						<th><?= e__('portal.locations.column.accuracy') ?></th>
						<th><?= e__('portal.locations.column.timestamp') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($locations as $location): ?>
						<?php
						$latitude = (float)$location['latitude'];
						$longitude = (float)$location['longitude'];
						$label = trim((string)($location['address_label'] ?? ''));
						$label = $label !== '' ? $label : __('portal.locations.coordinates', [
							'latitude' => number_format($latitude, 5, '.', ''),
							'longitude' => number_format($longitude, 5, '.', ''),
						]);
						?>
						<tr>
							<td class="portal-location-name"><a href="<?= e(openstreetmap_location_url($openStreetMapUrl, $latitude, $longitude)) ?>" target="_blank" rel="noopener noreferrer"><?= e($label) ?></a></td>
							<td class="portal-location-accuracy"><?= e__('portal.locations.accuracy_value', ['meters' => (int)ceil((float)$location['accuracy_meters'])]) ?></td>
							<td class="portal-location-timestamp"><time datetime="<?= e((string)$location['checked_in_at']) ?>"><?= e(format_datetime((string)$location['checked_in_at'])) ?></time></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="portal-location-map-actions">
			<button
				type="button"
				class="button-link"
				data-map-toggle
				data-map-show-label="<?= e__('portal.locations.show_map') ?>"
				data-map-hide-label="<?= e__('portal.locations.hide_map') ?>"
				aria-expanded="false"
				aria-controls="portal-location-map-panel"
				hidden
			><?= e__('portal.locations.show_map') ?></button>
		</div>
		<p class="form-hint"><?= e__('portal.locations.external_notice') ?></p>
		<div id="portal-location-map-panel" class="portal-location-map-panel" data-map-panel hidden>
			<?php require __DIR__ . '/location-map.php'; ?>
		</div>
		<noscript>
			<p class="flash flash-error"><?= e__('portal.location_map.javascript_required') ?></p>
		</noscript>
	</section>
<?php endif; ?>

<?php if (!$previewMode && empty($delivery['portal_expires_at'])): ?>
	<section class="portal-close-access" aria-labelledby="portal-close-access-heading">
		<h2 id="portal-close-access-heading"><?= e__('portal.close.link_heading') ?></h2>
		<p><?= e__('portal.close.link_hint') ?></p>
		<form method="get" action="<?= e($base_url) ?>/portal/close">
			<input type="hidden" name="token" value="<?= e($token) ?>">
			<button type="submit" class="btn-danger"><?= e__('portal.close.link') ?></button>
		</form>
	</section>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = e__($previewMode ? 'recipients.portal_preview.title' : 'portal.access.title');
require __DIR__ . '/../layouts/main.php';
