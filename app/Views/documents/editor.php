<?php

/**
 * @file editor.php
 * @brief Dedicated monitor document editor.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $monitor */
/** @var array<string, mixed>|null $document */
/** @var string $base_url */
/** @var bool|null $previewAvailable */
/** @var string|null $previewKind */

ob_start();
$isNew = $document === null;
$isArchived = !empty($monitor['is_archived']);
$storageType = $isNew ? 'text' : (string)$document['storage_type'];
$monitorId = (int)$monitor['id'];
$documentId = $isNew ? 0 : (int)$document['id'];
$isText = $storageType === 'text';
$previewAvailable = !$isNew && !$isText && !empty($previewAvailable);
$previewKind = (string)($previewKind ?? 'download');
$previewUrl = $previewAvailable
	? $base_url . '/monitors/documents/preview?monitor_id=' . $monitorId . '&document_id=' . $documentId
	: '';
$isFramedPreview = in_array($previewKind, ['pdf', 'markdown', 'text', 'csv', 'json'], true);
$action = $isNew
	? $base_url . '/monitors/documents/text/create'
	: $base_url . '/monitors/documents/' . ($isText ? 'text' : 'file') . '/update';
$title = $isNew ? e__('monitors.documents.editor.new_title') : e__('monitors.documents.editor.edit_title');
?>

<p class="editor-breadcrumb">
	<a href="<?= e($base_url) ?>/monitors/edit?id=<?= $monitorId ?>&amp;tab=documents"><?= e((string)$monitor['name']) ?> · <?= e__('monitors.tabs.documents') ?></a>
</p>

<div class="editor-heading document-editor-heading">
	<div>
		<h1><?= e($title) ?></h1>
		<p class="form-hint"><?= e__($isNew ? 'monitors.documents.editor.new_hint' : 'monitors.documents.editor.edit_hint') ?></p>
	</div>
	<?php if (!$isNew): ?>
		<span class="document-type-badge"><?= e__('monitors.documents.type.' . $storageType) ?></span>
	<?php endif; ?>
</div>

<?php if ($isArchived): ?>
	<div class="dashboard-system-warning archived-readonly-notice" role="status">
		<div><strong><?= e__('monitors.archived.readonly.heading') ?></strong><p><?= e__('monitors.archived.readonly.message') ?></p></div>
	</div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="document-editor-form">
	<?= csrf_field() ?>
	<input type="hidden" name="monitor_id" value="<?= $monitorId ?>">
	<?php if (!$isNew): ?>
		<input type="hidden" name="document_id" value="<?= $documentId ?>">
	<?php endif; ?>

	<fieldset<?= $isArchived ? ' disabled' : '' ?>>
		<label for="document_editor_title"><?= e__('monitors.documents.upload.title') ?></label>
		<input type="text" id="document_editor_title" name="title" value="<?= e($isNew ? '' : (string)$document['title']) ?>" required>

		<?php if ($isText): ?>
			<label for="document_editor_content"><?= e__('monitors.documents.text.content') ?></label>
			<?= markdown_editor($base_url, 'document_editor_content', 'text_content', $isNew ? '' : (string)($document['text_content'] ?? ''), 18, 'web', ['required' => true]) ?>
		<?php else: ?>
			<div class="document-editor-file-meta">
				<div><span><?= e__('monitors.documents.editor.filename') ?></span><strong><?= e((string)($document['original_filename'] ?? '')) ?></strong></div>
				<div><span><?= e__('monitors.documents.editor.mime_type') ?></span><strong><?= e((string)($document['mime_type'] ?? '')) ?></strong></div>
				<div><span><?= e__('monitors.documents.editor.file_size') ?></span><strong><?= number_format((int)($document['file_size_bytes'] ?? 0)) ?> <?= e__('monitors.documents.editor.bytes') ?></strong></div>
			</div>

			<?php if ($previewAvailable): ?>
				<section class="document-editor-preview" aria-labelledby="document-editor-preview-heading">
					<h2 id="document-editor-preview-heading"><?= e__('monitors.documents.editor.preview') ?></h2>
					<div class="document-editor-preview-surface document-editor-preview-<?= e($previewKind) ?>">
						<?php if ($previewKind === 'image'): ?>
							<img src="<?= e($previewUrl) ?>" alt="<?= e((string)$document['title']) ?>" loading="lazy" decoding="async">
						<?php elseif ($previewKind === 'audio'): ?>
							<audio controls preload="metadata" src="<?= e($previewUrl) ?>"><?= e__('portal.documents.media_unsupported') ?></audio>
						<?php elseif ($previewKind === 'video'): ?>
							<video controls preload="metadata" playsinline src="<?= e($previewUrl) ?>"><?= e__('portal.documents.media_unsupported') ?></video>
						<?php elseif ($isFramedPreview): ?>
							<iframe
								class="document-editor-preview-frame"
								src="<?= e($previewUrl) ?>"
								title="<?= e__('portal.documents.preview_named', ['name' => (string)$document['title']]) ?>"
								loading="lazy"
								referrerpolicy="no-referrer"<?= $previewKind === 'pdf' ? '' : ' sandbox="allow-same-origin"' ?>
							></iframe>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<label for="document_editor_description"><?= e__('monitors.documents.description') ?></label>
			<textarea id="document_editor_description" name="description" rows="5"><?= e((string)($document['description'] ?? '')) ?></textarea>
			<p class="form-hint"><?= e__('monitors.documents.description_hint') ?></p>
		<?php endif; ?>
	</fieldset>

	<div class="document-editor-actions">
		<?php if (!$isArchived): ?>
			<button type="submit"><?= e__($isNew ? 'monitors.documents.text.create.submit' : ($isText ? 'monitors.documents.text.update.submit' : 'monitors.documents.file.update.submit')) ?></button>
		<?php endif; ?>
		<a href="<?= e($base_url) ?>/monitors/edit?id=<?= $monitorId ?>&amp;tab=documents" class="button-link"><?= e__('monitors.documents.editor.back') ?></a>
		<?php if (!$isNew && !$isText): ?>
			<a href="<?= e($base_url) ?>/monitors/documents/download?monitor_id=<?= $monitorId ?>&amp;document_id=<?= $documentId ?>" class="button-link"><?= e__('monitors.documents.download.submit') ?></a>
		<?php endif; ?>
	</div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
