<?php
/** @brief Shared monitor and recipient document table; caller supplies scope and actions. */
declare(strict_types=1);
usort($tableDocuments, static fn (array $a, array $b): int => strnatcasecmp(mb_strtolower((string)$a['title']), mb_strtolower((string)$b['title'])) ?: ((int)$a['id'] <=> (int)$b['id']));
?>
<div class="table-scroll"><table class="document-list-table">
<thead><tr><th><?= e__('monitors.documents.upload.title') ?></th><th><?= e__('recipients.documents.type') ?></th><th><?= e__('recipients.documents.size') ?></th><th><?= e__('monitors.index.table.actions') ?></th></tr></thead>
<tbody>
<?php foreach ($tableDocuments as $document): ?>
<?php $documentUrl = $base_url . '/monitors/documents/edit?monitor_id=' . $tableMonitorId . '&document_id=' . (int)$document['id'] . ($tableRecipientId > 0 ? '&recipient_id=' . $tableRecipientId : ''); ?>
<tr><td><a href="<?= e($documentUrl) ?>"><?= e((string)$document['title']) ?></a></td>
<td><?= e__('monitors.documents.type.' . (string)$document['storage_type']) ?></td>
<td><?= e(format_file_size((string)$document['storage_type'] === 'text' ? strlen((string)($document['text_content'] ?? '')) : (int)($document['file_size_bytes'] ?? 0))) ?></td>
<td><details class="row-action-menu" data-row-action-menu><summary class="row-action-menu-toggle" aria-label="<?= e__('monitors.index.table.actions') ?>"><span aria-hidden="true">⋯</span></summary><div class="row-action-menu-panel" data-row-action-menu-panel>
<a class="row-action-menu-item" href="<?= e($documentUrl) ?>"><?= e__('monitors.documents.list.edit') ?></a>
<?php if ((new \Pulse\Services\DocumentPreviewService())->IsViewable($document)): ?>
<a class="row-action-menu-item" target="_blank" rel="noopener" href="<?= e($base_url) ?>/monitors/documents/preview?monitor_id=<?= $tableMonitorId ?>&amp;document_id=<?= (int)$document['id'] ?>"><?= e__('recipients.documents.preview') ?></a>
<?php endif; ?>
<?php if ((string)$document['storage_type'] === 'file'): ?>
<a class="row-action-menu-item" href="<?= e($base_url) ?>/monitors/documents/download?monitor_id=<?= $tableMonitorId ?>&amp;document_id=<?= (int)$document['id'] ?>"><?= e__('monitors.documents.download.submit') ?></a>
<?php endif; ?>
<?php if (!$tableArchived && $tableRecipientId > 0): ?>
<form method="post" action="<?= e($base_url) ?>/monitors/recipients/documents" data-document-assignment>
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $tableRecipientId ?>"><input type="hidden" name="document_action" value="unassign"><input type="hidden" name="document_ids[]" value="<?= (int)$document['id'] ?>">
<button class="row-action-menu-item" type="submit"><?= e__('recipients.documents.unassign') ?></button></form>
<?php endif; ?>
<?php if (!$tableArchived && $tableRecipientId === 0): ?>
<form method="post" action="<?= e($base_url) ?>/monitors/documents/delete" data-confirm="<?= e__('monitors.documents.flash.delete_confirm') ?>" class="document-delete-form">
<?= csrf_field() ?><input type="hidden" name="monitor_id" value="<?= $tableMonitorId ?>"><input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
<button type="submit" class="row-action-menu-item btn-danger"><?= e__('monitors.documents.delete.submit') ?></button></form>
<?php endif; ?>
</div></details></td></tr>
<?php endforeach; ?>
</tbody></table></div>
