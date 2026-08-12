<?php

/**
 * @file edit.php
 * @brief Tabbed monitor configuration editor.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $contacts */
/** @var array<int> $assignedContactIds */
/** @var array<int> $safetyContactIds */
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
	'escalation' => 'monitors.tabs.escalation',
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
	<input type="hidden" name="active_tab" value="<?= e($activeTab) ?>" data-active-tab-input>
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

			<?php if ($monitorContacts === []): ?>
				<p><?= e__('monitors.recipients.none_assigned') ?></p>
			<?php else: ?>
				<div class="recipient-overview-list">
					<?php foreach ($monitorContacts as $monitorContact): ?>
						<?php $override = $messageOverrides[(int)$monitorContact['id']] ?? null; ?>
						<article class="recipient-overview-card">
							<div class="recipient-overview-identity">
								<strong><?= e((string)$monitorContact['name']) ?></strong>
								<small><?= e((string)$monitorContact['email']) ?></small>
							</div>
							<div class="recipient-overview-meta">
								<span><strong><?= e__('recipients.overview.language') ?>:</strong> <?= e(notification_language_name(isset($monitorContact['notification_locale']) ? (string)$monitorContact['notification_locale'] : null)) ?></span>
								<span><strong><?= e__('recipients.overview.message') ?>:</strong> <?= e__(is_array($override) ? 'recipients.overview.personal' : 'recipients.overview.default') ?></span>
								<span><?= e__('recipients.overview.documents', ['count' => (int)$monitorContact['document_count']]) ?></span>
								<?php if (!empty($monitorContact['latest_delivery_status'])): ?>
									<span class="mini-status mini-status-<?= e((string)$monitorContact['latest_delivery_status']) ?>"><?= e__('recipients.delivery.status.' . (string)$monitorContact['latest_delivery_status']) ?></span>
								<?php endif; ?>
							</div>
							<a class="button-link" href="<?= e($base_url) ?>/monitors/recipients/edit?id=<?= (int)$monitorContact['id'] ?>"><?= e__('recipients.overview.edit') ?></a>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<p class="form-hint recipient-safety-hint">
				<?= e__('recipients.overview.safety_hint') ?>
				<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>&amp;tab=escalation"><?= e__('recipients.overview.safety_action') ?></a>
			</p>

			<div class="configuration-block recipient-add-block">
				<h3><?= e__('recipients.add.heading') ?></h3>
				<?php
				$availableContacts = array_values(array_filter(
					$contacts,
					static fn (array $contact): bool => !in_array((int)$contact['id'], $assignedContactIds, true)
				));
				?>
				<?php if ($availableContacts === []): ?>
					<p><?= e__('recipients.add.none') ?></p>
				<?php else: ?>
					<form method="post" action="<?= e($base_url) ?>/monitors/recipients/add">
						<?= csrf_field() ?>
						<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
						<label for="add_recipient_contact"><?= e__('recipients.add.contact') ?></label>
						<select id="add_recipient_contact" name="contact_id" required>
							<option value=""><?= e__('recipients.add.choose') ?></option>
							<?php foreach ($availableContacts as $contact): ?>
								<option value="<?= (int)$contact['id'] ?>"><?= e((string)$contact['name']) ?> — <?= e((string)$contact['email']) ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit"><?= e__('recipients.add.submit') ?></button>
					</form>
				<?php endif; ?>
			</div>
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

				<p class="form-hint"><?= e__('monitors.messages.recipient_pages_hint') ?></p>

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

		<section id="monitor-tab-escalation" class="monitor-tab-panel<?= $activeTab === 'escalation' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="escalation"<?= $activeTab === 'escalation' ? '' : ' hidden' ?>>
			<div class="section-heading">
				<h2><?= e__('monitors.tabs.escalation') ?></h2>
				<p><?= e__('monitors.escalation.hint') ?></p>
			</div>

			<div class="escalation-policy-grid">
				<label class="policy-option">
					<input type="radio" name="escalation_policy" form="monitor-settings-form" value="direct" <?= (string)$monitor['escalation_policy'] === 'direct' ? 'checked' : '' ?>>
					<span class="policy-option-content"><strong><?= e__('monitors.escalation.direct.heading') ?></strong><small><?= e__('monitors.escalation.direct.hint') ?></small></span>
				</label>
				<label class="policy-option">
					<input type="radio" name="escalation_policy" form="monitor-settings-form" value="safety_contact" <?= (string)$monitor['escalation_policy'] === 'safety_contact' ? 'checked' : '' ?>>
					<span class="policy-option-content"><strong><?= e__('monitors.escalation.safety.heading') ?></strong><small><?= e__('monitors.escalation.safety.hint') ?></small></span>
				</label>
			</div>

			<div class="privacy-note">
				<strong><?= e__('monitors.escalation.authority.heading') ?></strong>
				<?= e__('monitors.escalation.authority.message') ?>
			</div>

			<h3><?= e__('monitors.escalation.contacts.heading') ?></h3>
			<p class="form-hint"><?= e__('monitors.escalation.contacts.hint') ?></p>
			<?php if ($contacts === []): ?>
				<p><?= e__('monitors.contacts.none') ?></p>
			<?php else: ?>
				<div class="assignment-list assignment-grid">
					<?php foreach ($contacts as $contact): ?>
						<label class="assignment-item">
							<input type="checkbox" name="safety_contact_ids[]" form="monitor-settings-form" value="<?= (int)$contact['id'] ?>" <?= in_array((int)$contact['id'], $safetyContactIds, true) ? 'checked' : '' ?>>
							<span>
								<strong><?= e((string)$contact['name']) ?></strong><br>
								<small><?= e((string)$contact['email']) ?></small>
								<span class="mini-status mini-status-<?= !empty($contact['email_checked_at']) ? 'ok' : 'warning' ?>"><?= e__(!empty($contact['email_checked_at']) ? 'contacts.status.checked' : 'contacts.status.not_checked') ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="field-grid field-grid-four">
				<label>
					<?= e__('monitors.escalation.response_window') ?>
					<input type="number" name="safety_response_window_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['safety_response_window_days'] ?>" required>
				</label>
				<label>
					<?= e__('monitors.escalation.reminder_interval') ?>
					<input type="number" name="safety_reminder_interval_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['safety_reminder_interval_days'] ?>" required>
				</label>
				<label>
					<?= e__('monitors.escalation.max_reminders') ?>
					<input type="number" name="safety_max_reminders" form="monitor-settings-form" min="0" max="100" value="<?= (int)$monitor['safety_max_reminders'] ?>" required>
				</label>
				<label>
					<?= e__('monitors.escalation.required_confirmations') ?>
					<input type="number" name="safety_required_confirmations" form="monitor-settings-form" min="1" max="100" value="<?= (int)$monitor['safety_required_confirmations'] ?>" required>
				</label>
			</div>

			<label for="safety_confirmation_days"><?= e__('monitors.escalation.confirmation_days') ?></label>
			<input type="number" id="safety_confirmation_days" name="safety_confirmation_days" form="monitor-settings-form" min="0" max="3650" value="<?= (int)($monitor['safety_confirmation_days'] ?? 0) ?>">
			<p class="form-hint"><?= e__('monitors.escalation.confirmation_days_hint') ?></p>

			<div class="configuration-block">
				<h3><?= e__('monitors.escalation.messages.heading') ?></h3>
				<p class="form-hint"><?= e__('monitors.escalation.messages.hint') ?></p>
				<p class="form-hint"><code>{app}</code> <code>{name}</code> <code>{owner}</code> <code>{monitor}</code> <code>{url}</code> · <?= e__('monitors.escalation.messages.reminder_placeholders') ?> <code>{number}</code> <code>{total}</code></p>

					<h4><?= e__('monitors.escalation.messages.invitation.heading') ?></h4>
					<label for="safety_invitation_subject"><?= e__('monitors.messages.subject') ?></label>
					<input type="text" id="safety_invitation_subject" name="safety_invitation_subject" form="monitor-settings-form" value="<?= e((string)($monitor['safety_invitation_subject'] ?? '')) ?>">
					<label for="safety_invitation_body"><?= e__('monitors.messages.body') ?></label>
					<textarea id="safety_invitation_body" name="safety_invitation_body" form="monitor-settings-form" rows="8"><?= e((string)($monitor['safety_invitation_body'] ?? '')) ?></textarea>

					<h4><?= e__('monitors.escalation.messages.reminder.heading') ?></h4>
					<label for="safety_reminder_subject"><?= e__('monitors.messages.subject') ?></label>
					<input type="text" id="safety_reminder_subject" name="safety_reminder_subject" form="monitor-settings-form" value="<?= e((string)($monitor['safety_reminder_subject'] ?? '')) ?>">
					<label for="safety_reminder_body"><?= e__('monitors.messages.body') ?></label>
					<textarea id="safety_reminder_body" name="safety_reminder_body" form="monitor-settings-form" rows="8"><?= e((string)($monitor['safety_reminder_body'] ?? '')) ?></textarea>

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
				<div class="review-stat"><strong><?= e__('monitors.escalation.policy.' . (string)$monitor['escalation_policy']) ?></strong><span><?= e__('monitors.review.escalation') ?></span></div>
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
				<?php if ((string)$monitor['escalation_policy'] === 'safety_contact' && $safetyContactIds === []): ?>
					<div class="review-warning"><?= e__('monitors.review.warning.no_safety_contacts') ?></div>
				<?php endif; ?>
			<?php if ($monitorContacts !== [] && $uncheckedContactCount === 0 && $hasCompleteMessageCoverage): ?>
				<div class="review-ready"><?= e__('monitors.review.ready') ?></div>
			<?php endif; ?>
		</div>

		<div class="activation-card">
			<div>
				<h3><?= e__('monitors.activation.heading') ?></h3>
				<p><?= e__($currentStatus === 'paused' ? 'monitors.activation.paused_hint' : 'monitors.activation.active_hint') ?></p>
			</div>
			<form method="post" action="<?= e($base_url) ?>/monitors/<?= $currentStatus === 'paused' ? 'resume' : 'pause' ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
				<input type="hidden" name="redirect" value="/monitors/edit?id=<?= (int)$monitor['id'] ?>&amp;tab=review">
				<button type="submit" class="<?= $currentStatus === 'paused' ? 'btn-primary' : '' ?>"><?= e__($currentStatus === 'paused' ? 'monitors.resume.submit' : 'monitors.pause.submit') ?></button>
			</form>
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
