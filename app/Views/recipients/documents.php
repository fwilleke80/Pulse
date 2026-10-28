<?php
/** @brief Assigned document table and searchable assignment picker. */
declare(strict_types=1);
$assignedDocuments = array_values(array_filter($documents, static fn (array $item): bool => in_array((int)$item['id'], $assignedDocumentIds, true)));
$unassignedDocuments = array_values(array_filter($documents, static fn (array $item): bool => !in_array((int)$item['id'], $assignedDocumentIds, true)));
$sortDocuments = static fn (array $a, array $b): int => strnatcasecmp(mb_strtolower((string)$a['title']), mb_strtolower((string)$b['title'])) ?: ((int)$a['id'] <=> (int)$b['id']);
usort($assignedDocuments, $sortDocuments);
usort($unassignedDocuments, $sortDocuments);
?>
<div data-recipient-document-manager data-refresh-url="<?= e($base_url) ?>/monitors/recipients/edit?id=<?= (int)$recipient['id'] ?>&amp;section=documents" data-error="<?= e__('recipients.documents.failed') ?>">
<div class="section-heading"><h2><?= e__('recipients.documents.heading') ?></h2>
<?php if (!$isArchivedMonitor): ?><button type="button" data-assign-open><?= e__('recipients.documents.assign') ?></button><?php endif; ?></div>
<p class="form-hint"><?= e__('recipients.documents.saved_immediately') ?></p>
<div data-assignment-status role="status" aria-live="polite"></div>
<div data-assigned-documents>
<?php if ($assignedDocuments === []): ?><p><?= e__('recipients.documents.none_assigned') ?></p><?php else: ?>
<?php
$tableDocuments = $assignedDocuments;
$tableMonitorId = (int)$recipient['monitor_id'];
$tableRecipientId = (int)$recipient['id'];
$tableArchived = $isArchivedMonitor;
require __DIR__ . '/../partials/document-table.php';
?>
<?php endif; ?>
</div>
<?php if (!$isArchivedMonitor): ?>
<dialog data-assign-dialog aria-labelledby="assign-documents-heading" class="assign-documents-dialog">
<form method="post" action="<?= e($base_url) ?>/monitors/recipients/documents" data-document-assignment>
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$recipient['id'] ?>"><input type="hidden" name="document_action" value="assign">
<h2 id="assign-documents-heading"><?= e__('recipients.documents.assign') ?></h2>
<label><?= e__('recipients.documents.search') ?><input type="search" data-assign-search></label>
<div data-unassigned-documents>
<?php if ($unassignedDocuments === []): ?><p><?= e__('recipients.documents.all_assigned') ?></p><?php endif; ?>
<?php foreach ($unassignedDocuments as $document): ?>
<label class="assign-document-choice" data-assign-choice><input type="checkbox" name="document_ids[]" value="<?= (int)$document['id'] ?>"><span><?= e((string)$document['title']) ?></span><small><?= e__('monitors.documents.type.' . (string)$document['storage_type']) ?></small></label>
<?php endforeach; ?>
</div>
<p data-assign-no-results hidden><?= e__('recipients.documents.no_results') ?></p>
<div class="table-actions"><button type="submit" data-assign-submit disabled><?= e__('recipients.documents.assign_selected') ?></button><button type="button" data-assign-close><?= e__('monitors.edit.cancel') ?></button></div>
</form></dialog>
<noscript><p><?= e__('recipients.documents.javascript') ?></p></noscript>
<?php endif; ?>
</div>
