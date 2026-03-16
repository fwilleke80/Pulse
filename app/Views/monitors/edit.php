<?php

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $contacts */
/** @var array<int> $assignedContactIds */
/** @var array<int, array<string, mixed>> $monitorContacts */
/** @var array<int, array<string, mixed>> $documents */
/** @var array<string, mixed> $monitor */
/** @var string $base_url */

ob_start();
?>

<h1><?= e__('monitors.edit.heading') ?></h1>

<form method="post" action="<?= e($base_url) ?>/monitors/update">
	<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">

	<label for="name"><?= e__('monitors.edit.name') ?></label>
	<input type="text" id="name" name="name" value="<?= htmlspecialchars((string)$monitor['name'], ENT_QUOTES, 'UTF-8') ?>" required>

	<label for="description"><?= e__('monitors.edit.description') ?></label>
	<input type="text" id="description" name="description" value="<?= htmlspecialchars((string)($monitor['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

	<label for="check_interval_days"><?= e__('monitors.edit.check_interval_days') ?></label>
	<input type="number" id="check_interval_days" name="check_interval_days" min="1" value="<?= (int)$monitor['check_interval_days'] ?>" required>

	<label for="response_window_days"><?= e__('monitors.edit.response_window_days') ?></label>
	<input type="number" id="response_window_days" name="response_window_days" min="1" value="<?= (int)$monitor['response_window_days'] ?>" required>

	<label for="reminder_interval_days"><?= e__('monitors.edit.reminder_interval_days') ?></label>
	<input type="number" id="reminder_interval_days" name="reminder_interval_days" min="1" value="<?= (int)$monitor['reminder_interval_days'] ?>" required>

	<label for="max_reminders"><?= e__('monitors.edit.max_reminders') ?></label>
	<input type="number" id="max_reminders" name="max_reminders" min="0" value="<?= (int)$monitor['max_reminders'] ?>" required>

	<div class="checkbox-row">
		<label>
			<input type="checkbox" name="is_paused" <?= !empty($monitor['is_paused']) ? 'checked' : '' ?>>
			<?= e__('monitors.edit.is_paused') ?>
		</label>
	</div>

	<div class="assignment-box">
		<h2><?= e__('monitors.contacts.heading') ?></h2>
		<p class="form-hint"><?= e__('monitors.contacts.hint') ?></p>

		<?php if ($contacts === []): ?>
			<p><?= e__('monitors.contacts.none') ?></p>
		<?php else: ?>
			<div class="assignment-list">
				<?php foreach ($contacts as $contact): ?>
					<?php $contactId = (int)$contact['id']; ?>
					<label class="assignment-item">
						<input
							type="checkbox"
							name="contact_ids[]"
							value="<?= $contactId ?>"
							<?= in_array($contactId, $assignedContactIds, true) ? 'checked' : '' ?>
						>
						<span>
							<strong><?= htmlspecialchars((string)$contact['name'], ENT_QUOTES, 'UTF-8') ?></strong>
							<?php if (!empty($contact['email'])): ?>
								<br><small><?= htmlspecialchars((string)$contact['email'], ENT_QUOTES, 'UTF-8') ?></small>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<button type="submit"><?= e__('monitors.edit.submit') ?></button>
</form>

<hr>

<section class="monitor-documents-section">
	<h2><?= e__('monitors.documents.heading') ?></h2>
	<p class="form-hint"><?= e__('monitors.documents.hint') ?></p>

	<div class="monitor-document-card">
		<h3><?= e__('monitors.documents.upload.heading') ?></h3>
		<form method="post" action="<?= e($base_url) ?>/monitors/documents/upload" enctype="multipart/form-data" class="document-upload-form">
			<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">

			<label for="document_title"><?= e__('monitors.documents.upload.title') ?></label>
			<input type="text" id="document_title" name="title">

			<label for="document_file"><?= e__('monitors.documents.upload.file') ?></label>
			<input type="file" id="document_file" name="document_file" required>

			<div class="assignment-box">
				<h3><?= e__('monitors.documents.recipients.heading') ?></h3>

				<?php if ($monitorContacts === []): ?>
					<p><?= e__('monitors.documents.recipients.none') ?></p>
				<?php else: ?>
					<div class="assignment-list">
						<?php foreach ($monitorContacts as $monitorContact): ?>
							<label class="assignment-item">
								<input
									type="checkbox"
									name="document_monitor_contact_ids[]"
									value="<?= (int)$monitorContact['id'] ?>"
								>
								<span>
									<strong><?= htmlspecialchars((string)$monitorContact['name'], ENT_QUOTES, 'UTF-8') ?></strong>
									<?php if (!empty($monitorContact['email'])): ?>
										<br><small><?= htmlspecialchars((string)$monitorContact['email'], ENT_QUOTES, 'UTF-8') ?></small>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<button type="submit"><?= e__('monitors.documents.upload.submit') ?></button>
		</form>
	</div>

	<?php if ($documents === []): ?>
		<p><?= e__('monitors.documents.none') ?></p>
	<?php else: ?>
		<div class="monitor-document-list">
			<?php foreach ($documents as $document): ?>
				<div class="monitor-document-card">
					<h3><?= htmlspecialchars((string)$document['title'], ENT_QUOTES, 'UTF-8') ?></h3>

					<p>
						<strong><?= e__('monitors.documents.table.original_filename') ?>:</strong>
						<?= htmlspecialchars((string)($document['original_filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
					</p>

					<?php if (!empty($document['mime_type'])): ?>
						<p>
							<strong><?= e__('monitors.documents.table.mime_type') ?>:</strong>
							<?= htmlspecialchars((string)$document['mime_type'], ENT_QUOTES, 'UTF-8') ?>
						</p>
					<?php endif; ?>

					<?php if (!empty($document['file_size_bytes'])): ?>
						<p>
							<strong><?= e__('monitors.documents.table.file_size') ?>:</strong>
							<?= (int)$document['file_size_bytes'] ?> bytes
						</p>
					<?php endif; ?>

					<form method="post" action="<?= e($base_url) ?>/monitors/documents/recipients">
						<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
						<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">

						<div class="assignment-box">
							<h4><?= e__('monitors.documents.recipients.heading') ?></h4>

							<?php if ($monitorContacts === []): ?>
								<p><?= e__('monitors.documents.recipients.none') ?></p>
							<?php else: ?>
								<div class="assignment-list">
									<?php foreach ($monitorContacts as $monitorContact): ?>
										<?php $monitorContactId = (int)$monitorContact['id']; ?>
										<label class="assignment-item">
											<input
												type="checkbox"
												name="document_monitor_contact_ids[]"
												value="<?= $monitorContactId ?>"
												<?= in_array($monitorContactId, $document['assigned_monitor_contact_ids'], true) ? 'checked' : '' ?>
											>
											<span>
												<strong><?= htmlspecialchars((string)$monitorContact['name'], ENT_QUOTES, 'UTF-8') ?></strong>
												<?php if (!empty($monitorContact['email'])): ?>
													<br><small><?= htmlspecialchars((string)$monitorContact['email'], ENT_QUOTES, 'UTF-8') ?></small>
												<?php endif; ?>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

						<div class="table-actions">
							<button type="submit" class="btn-table-inline"><?= e__('monitors.documents.recipients.submit') ?></button>
						</div>
					</form>

					<div class="table-actions">
						<form method="get" action="<?= e($base_url) ?>/monitors/documents/download">
							<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
							<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
							<button type="submit">
								<?= e__('monitors.documents.download.submit') ?>
							</button>
						</form>
						<form method="post" action="<?= e($base_url) ?>/monitors/documents/delete" onsubmit="return confirm('<?= e__('monitors.documents.flash.delete_confirm') ?>');">
							<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
							<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
							<button type="submit"><?= e__('monitors.documents.delete.submit') ?></button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<p><a href="<?= e($base_url) ?>/monitors"><?= e__('monitors.edit.back') ?></a></p>

<?php
$content = ob_get_clean();
$title = e__('monitors.edit.title');
require __DIR__ . '/../layouts/main.php';