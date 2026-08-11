<?php

/**
 * @file edit.php
 * @brief Tabbed monitor configuration editor.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $contacts */
/** @var array<int> $assignedContactIds */
/** @var array<int, array<string, mixed>> $monitorContacts */
/** @var array<int, array<string, mixed>> $documents */
/** @var array<int, array<string, string>> $messageOverrides */
/** @var array<string, mixed> $monitor */
/** @var string $activeTab */
/** @var string $base_url */

$uncheckedContactCount = count(array_filter(
	$monitorContacts,
	static fn (array $contact): bool => empty($contact['email_checked_at'])
));
$currentStatus = monitor_status($monitor);
$messageOverrideCount = count($messageOverrides);
$hasCompleteMessageCoverage = !empty($monitor['default_message_body'])
	|| ($monitorContacts !== [] && $messageOverrideCount === count($monitorContacts));
$tabDefinitions = [
	'schedule' => 'monitors.tabs.schedule',
	'recipients' => 'monitors.tabs.recipients',
	'messages' => 'monitors.tabs.messages',
	'review' => 'monitors.tabs.review',
];

ob_start();
?>

<div class="editor-heading">
	<div>
		<h1><?= e__('monitors.edit.heading') ?></h1>
		<p class="form-hint"><?= e__('monitors.edit.intro') ?></p>
	</div>
	<span class="status-badge status-<?= e($currentStatus) ?>"><?= e__('monitors.status.' . $currentStatus) ?></span>
</div>

<form id="monitor-settings-form" method="post" action="<?= e($base_url) ?>/monitors/update" class="form-carrier">
	<?= csrf_field() ?>
	<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
</form>

<div class="monitor-editor" data-monitor-tabs data-active-tab="<?= e($activeTab) ?>">
	<div class="monitor-tabs" role="tablist" aria-label="<?= e__('monitors.tabs.label') ?>">
		<?php $tabNumber = 0; ?>
		<?php foreach ($tabDefinitions as $tabName => $translationKey): ?>
			<?php $tabNumber++; ?>
			<?php $isActiveTab = $activeTab === $tabName; ?>
			<a
				href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>&amp;tab=<?= e($tabName) ?>"
				class="monitor-tab-link<?= $isActiveTab ? ' is-active' : '' ?>"
				role="tab"
				data-tab-target="<?= e($tabName) ?>"
				aria-controls="monitor-tab-<?= e($tabName) ?>"
				aria-selected="<?= $isActiveTab ? 'true' : 'false' ?>"
				tabindex="<?= $isActiveTab ? '0' : '-1' ?>"
			>
				<span class="tab-number"><?= $tabNumber ?></span>
				<span class="tab-label"><?= e__($translationKey) ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<section id="monitor-tab-schedule" class="monitor-tab-panel<?= $activeTab === 'schedule' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="schedule"<?= $activeTab === 'schedule' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.schedule') ?></h2>
			<p><?= e__('monitors.schedule.hint') ?></p>
		</div>

		<label for="name"><?= e__('monitors.edit.name') ?></label>
		<input type="text" id="name" name="name" form="monitor-settings-form" value="<?= e((string)$monitor['name']) ?>" required>

		<label for="description"><?= e__('monitors.edit.description') ?></label>
		<textarea id="description" name="description" form="monitor-settings-form" rows="3"><?= e((string)($monitor['description'] ?? '')) ?></textarea>

		<div class="field-grid field-grid-four">
			<label>
				<?= e__('monitors.edit.check_interval_days') ?>
				<input type="number" id="check_interval_days" name="check_interval_days" form="monitor-settings-form" min="1" max="3650" value="<?= (int)$monitor['check_interval_days'] ?>" required>
			</label>
			<label>
				<?= e__('monitors.edit.response_window_days') ?>
				<input type="number" id="response_window_days" name="response_window_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['response_window_days'] ?>" required>
			</label>
			<label>
				<?= e__('monitors.edit.reminder_interval_days') ?>
				<input type="number" id="reminder_interval_days" name="reminder_interval_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['reminder_interval_days'] ?>" required>
			</label>
			<label>
				<?= e__('monitors.edit.max_reminders') ?>
				<input type="number" id="max_reminders" name="max_reminders" form="monitor-settings-form" min="0" max="100" value="<?= (int)$monitor['max_reminders'] ?>" required>
			</label>
		</div>
	</section>

	<section id="monitor-tab-recipients" class="monitor-tab-panel<?= $activeTab === 'recipients' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="recipients"<?= $activeTab === 'recipients' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.recipients') ?></h2>
			<p><?= e__('monitors.contacts.hint') ?></p>
		</div>

		<div class="privacy-note">
			<strong><?= e__('monitors.contacts.silent.heading') ?></strong>
			<?= e__('monitors.contacts.silent.message') ?>
		</div>

		<?php if ($contacts === []): ?>
			<p><?= e__('monitors.contacts.none') ?></p>
		<?php else: ?>
			<div class="assignment-list assignment-grid">
				<?php foreach ($contacts as $contact): ?>
					<?php $contactId = (int)$contact['id']; ?>
					<div class="assignment-item">
						<input
							type="checkbox"
							id="monitor_contact_<?= $contactId ?>"
							name="contact_ids[]"
							form="monitor-settings-form"
							value="<?= $contactId ?>"
							<?= in_array($contactId, $assignedContactIds, true) ? 'checked' : '' ?>
						>
						<span class="assignment-details">
							<label for="monitor_contact_<?= $contactId ?>" class="assignment-contact-label"><strong><?= e((string)$contact['name']) ?></strong></label>
							<small><?= e((string)$contact['email']) ?></small>
							<?php if (!empty($contact['email_checked_at'])): ?>
								<span class="mini-status mini-status-ok"><?= e__('contacts.status.checked') ?></span>
							<?php else: ?>
								<span class="mini-status mini-status-warning"><?= e__('contacts.status.not_checked') ?></span>
								<a
									href="<?= e($base_url) ?>/contacts/edit?id=<?= $contactId ?>&amp;return_monitor_id=<?= (int)$monitor['id'] ?>"
									class="contact-check-link"
								><?= e__('monitors.contacts.check_address') ?></a>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<section id="monitor-tab-messages" class="monitor-tab-panel<?= $activeTab === 'messages' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="messages"<?= $activeTab === 'messages' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.messages') ?></h2>
			<p><?= e__('monitors.messages.hint') ?></p>
		</div>

		<div class="unencrypted-warning">
			<strong><?= e__('monitors.storage.warning.heading') ?></strong>
			<?= e__('monitors.storage.warning.message') ?>
		</div>

		<div class="configuration-block">
			<h3><?= e__('monitors.messages.default.heading') ?></h3>
			<p class="form-hint"><?= e__('monitors.messages.default.hint') ?></p>
			<form method="post" action="<?= e($base_url) ?>/monitors/messages/update">
				<?= csrf_field() ?>
				<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">

				<label for="default_message_subject"><?= e__('monitors.messages.subject') ?></label>
				<input type="text" id="default_message_subject" name="default_message_subject" value="<?= e((string)($monitor['default_message_subject'] ?? '')) ?>">

				<label for="default_message_body"><?= e__('monitors.messages.body') ?></label>
				<textarea id="default_message_body" name="default_message_body" rows="8"><?= e((string)($monitor['default_message_body'] ?? '')) ?></textarea>

				<h3 class="subsection-heading"><?= e__('monitors.messages.overrides.heading') ?></h3>
				<p class="form-hint"><?= e__('monitors.messages.overrides.hint') ?></p>

				<?php if ($monitorContacts === []): ?>
					<p><?= e__('monitors.messages.overrides.none') ?></p>
				<?php else: ?>
					<div class="message-override-list">
						<?php foreach ($monitorContacts as $monitorContact): ?>
							<?php
							$monitorContactId = (int)$monitorContact['id'];
							$override = $messageOverrides[$monitorContactId] ?? null;
							?>
							<div class="message-override-card" data-message-override>
								<div class="message-override-heading">
									<div>
										<strong><?= e((string)$monitorContact['name']) ?></strong><br>
										<small><?= e((string)$monitorContact['email']) ?></small>
									</div>
									<label class="compact-check">
										<input type="checkbox" name="message_override_<?= $monitorContactId ?>" data-message-override-toggle <?= is_array($override) ? 'checked' : '' ?>>
										<?= e__('monitors.messages.overrides.enable') ?>
									</label>
								</div>
								<div data-message-fields>
									<label for="message_subject_<?= $monitorContactId ?>"><?= e__('monitors.messages.subject') ?></label>
									<input type="text" id="message_subject_<?= $monitorContactId ?>" name="message_subject_<?= $monitorContactId ?>" value="<?= e((string)($override['subject'] ?? '')) ?>">
									<label for="message_body_<?= $monitorContactId ?>"><?= e__('monitors.messages.body') ?></label>
									<textarea id="message_body_<?= $monitorContactId ?>" name="message_body_<?= $monitorContactId ?>" rows="6"><?= e((string)($override['body_text'] ?? '')) ?></textarea>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<button type="submit" class="btn-primary"><?= e__('monitors.messages.submit') ?></button>
			</form>
		</div>

		<div class="configuration-block">
			<h3><?= e__('monitors.documents.heading') ?></h3>
			<p class="form-hint"><?= e__('monitors.documents.hint') ?></p>

			<div class="document-create-grid">
				<div class="monitor-document-card">
					<h4><?= e__('monitors.documents.text.create.heading') ?></h4>
					<form method="post" action="<?= e($base_url) ?>/monitors/documents/text/create">
						<?= csrf_field() ?>
						<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
						<label for="text_document_title"><?= e__('monitors.documents.upload.title') ?></label>
						<input type="text" id="text_document_title" name="title" required>
						<label for="text_document_content"><?= e__('monitors.documents.text.content') ?></label>
						<textarea id="text_document_content" name="text_content" rows="7" required></textarea>
						<?php require __DIR__ . '/partials/document-recipients.php'; ?>
						<button type="submit"><?= e__('monitors.documents.text.create.submit') ?></button>
					</form>
				</div>

				<div class="monitor-document-card">
					<h4><?= e__('monitors.documents.upload.heading') ?></h4>
					<form method="post" action="<?= e($base_url) ?>/monitors/documents/upload" enctype="multipart/form-data">
						<?= csrf_field() ?>
						<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
						<label for="document_title"><?= e__('monitors.documents.upload.title') ?></label>
						<input type="text" id="document_title" name="title">
						<label for="document_file"><?= e__('monitors.documents.upload.file') ?></label>
						<input type="file" id="document_file" name="document_file" required>
						<?php require __DIR__ . '/partials/document-recipients.php'; ?>
						<button type="submit"><?= e__('monitors.documents.upload.submit') ?></button>
					</form>
				</div>
			</div>

			<?php if ($documents === []): ?>
				<p><?= e__('monitors.documents.none') ?></p>
			<?php else: ?>
				<div class="monitor-document-list">
					<?php foreach ($documents as $document): ?>
						<div class="monitor-document-card">
							<div class="document-card-heading">
								<div>
									<span class="document-type-badge"><?= e__('monitors.documents.type.' . (string)$document['storage_type']) ?></span>
									<h4><?= e((string)$document['title']) ?></h4>
								</div>
								<?php if ((string)$document['storage_type'] === 'file'): ?>
									<a href="<?= e($base_url) ?>/monitors/documents/download?monitor_id=<?= (int)$monitor['id'] ?>&amp;document_id=<?= (int)$document['id'] ?>" class="button-link"><?= e__('monitors.documents.download.submit') ?></a>
								<?php endif; ?>
							</div>

							<?php if ((string)$document['storage_type'] === 'text'): ?>
								<form method="post" action="<?= e($base_url) ?>/monitors/documents/text/update">
									<?= csrf_field() ?>
									<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
									<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
									<label for="text_title_<?= (int)$document['id'] ?>"><?= e__('monitors.documents.upload.title') ?></label>
									<input type="text" id="text_title_<?= (int)$document['id'] ?>" name="title" value="<?= e((string)$document['title']) ?>" required>
									<label for="text_content_<?= (int)$document['id'] ?>"><?= e__('monitors.documents.text.content') ?></label>
									<textarea id="text_content_<?= (int)$document['id'] ?>" name="text_content" rows="8" required><?= e((string)($document['text_content'] ?? '')) ?></textarea>
									<?php $assignedMonitorContactIds = $document['assigned_monitor_contact_ids']; ?>
									<?php require __DIR__ . '/partials/document-recipients.php'; ?>
									<button type="submit"><?= e__('monitors.documents.text.update.submit') ?></button>
								</form>
							<?php else: ?>
								<div class="document-metadata">
									<span><?= e((string)($document['original_filename'] ?? '')) ?></span>
									<span><?= e((string)($document['mime_type'] ?? '')) ?></span>
									<span><?= number_format((int)($document['file_size_bytes'] ?? 0)) ?> bytes</span>
								</div>
								<form method="post" action="<?= e($base_url) ?>/monitors/documents/recipients">
									<?= csrf_field() ?>
									<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
									<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
									<?php $assignedMonitorContactIds = $document['assigned_monitor_contact_ids']; ?>
									<?php require __DIR__ . '/partials/document-recipients.php'; ?>
									<button type="submit"><?= e__('monitors.documents.recipients.submit') ?></button>
								</form>
							<?php endif; ?>

							<form method="post" action="<?= e($base_url) ?>/monitors/documents/delete" data-confirm="<?= e__('monitors.documents.flash.delete_confirm') ?>" class="document-delete-form">
								<?= csrf_field() ?>
								<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
								<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
								<button type="submit" class="btn-danger"><?= e__('monitors.documents.delete.submit') ?></button>
							</form>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section id="monitor-tab-review" class="monitor-tab-panel<?= $activeTab === 'review' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="review"<?= $activeTab === 'review' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.review') ?></h2>
			<p><?= e__('monitors.review.hint') ?></p>
		</div>

		<div class="review-grid">
			<div class="review-stat"><strong><?= count($monitorContacts) ?></strong><span><?= e__('monitors.review.recipients') ?></span></div>
			<div class="review-stat"><strong><?= $messageOverrideCount ?></strong><span><?= e__('monitors.review.overrides') ?></span></div>
			<div class="review-stat"><strong><?= count($documents) ?></strong><span><?= e__('monitors.review.documents') ?></span></div>
			<div class="review-stat"><strong><?= e(format_datetime((string)($monitor['next_check_due_at'] ?? ''))) ?></strong><span><?= e__('monitors.review.next_due') ?></span></div>
		</div>

		<div class="review-warnings">
			<?php if ($monitorContacts === []): ?>
				<div class="review-warning"><?= e__('monitors.review.warning.no_recipients') ?></div>
			<?php endif; ?>
			<?php if ($uncheckedContactCount > 0 && empty($monitor['is_paused'])): ?>
				<div class="review-warning"><?= e__('monitors.review.warning.unchecked', ['count' => $uncheckedContactCount]) ?></div>
			<?php endif; ?>
			<?php if (!$hasCompleteMessageCoverage): ?>
				<div class="review-warning"><?= e__('monitors.review.warning.no_message') ?></div>
			<?php endif; ?>
			<?php if ($monitorContacts !== [] && $uncheckedContactCount === 0 && $hasCompleteMessageCoverage): ?>
				<div class="review-ready"><?= e__('monitors.review.ready') ?></div>
			<?php endif; ?>
		</div>

		<div class="activation-card">
			<div>
				<h3><?= e__('monitors.activation.heading') ?></h3>
				<p><?= e__('monitors.activation.hint') ?></p>
			</div>
			<label class="activation-toggle">
				<input type="checkbox" name="is_paused" form="monitor-settings-form" <?= !empty($monitor['is_paused']) ? 'checked' : '' ?>>
				<span><?= e__('monitors.edit.is_paused') ?></span>
			</label>
		</div>
	</section>
</div>

<div class="editor-save-bar">
	<span><?= e__('monitors.edit.save_hint') ?></span>
	<div class="editor-save-actions">
		<a href="<?= e($base_url) ?>/monitors" class="button-link editor-cancel-button"><?= e__('monitors.edit.cancel') ?></a>
		<button type="submit" form="monitor-settings-form" class="btn-primary"><?= e__('monitors.edit.submit') ?></button>
	</div>
</div>

<?php
$content = ob_get_clean();
$title = e__('monitors.edit.title');
require __DIR__ . '/../layouts/main.php';
