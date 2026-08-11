<?php

/**
 * @file document-recipients.php
 * @brief Reusable recipient assignment fields for a document form.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $monitorContacts */

$selectedRecipientIds = isset($assignedMonitorContactIds) && is_array($assignedMonitorContactIds)
	? array_map('intval', $assignedMonitorContactIds)
	: [];
?>

<fieldset class="recipient-fieldset">
	<legend><?= e__('monitors.documents.recipients.heading') ?></legend>
	<?php if ($monitorContacts === []): ?>
		<p><?= e__('monitors.documents.recipients.none') ?></p>
	<?php else: ?>
		<div class="recipient-chip-list">
			<?php foreach ($monitorContacts as $recipient): ?>
				<?php $recipientId = (int)$recipient['id']; ?>
				<label class="recipient-chip">
					<input
						type="checkbox"
						name="document_monitor_contact_ids[]"
						value="<?= $recipientId ?>"
						<?= in_array($recipientId, $selectedRecipientIds, true) ? 'checked' : '' ?>
					>
					<span><?= e((string)$recipient['name']) ?></span>
				</label>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</fieldset>

<?php unset($assignedMonitorContactIds, $selectedRecipientIds); ?>
