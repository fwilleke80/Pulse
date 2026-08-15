<?php

/**
 * @file edit.php
 * @brief Structured monitor editor with task-focused top-level and secondary tabs.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<int, array<string, mixed>> $contacts */
/** @var array<int> $assignedContactIds */
/** @var array<int> $safetyContactIds */
/** @var array<int, array<string, mixed>> $monitorContacts */
/** @var array<int, array{source: string, locale: string, issues: array<int, string>}> $recipientConfigurationIssues */
/** @var int $defaultRecipientTemplateIssueCount */
/** @var array<int, array<string, mixed>> $documents */
/** @var array<int, array<string, string>> $messageOverrides */
/** @var array<string, array<string, array{subject: string, body_text: string}>> $mailTemplates */
/** @var array<string, array<string, array{subject: string, body_text: string}>> $mailDefaults */
/** @var array<string, array{message_text: string, intro_text: string}> $portalTemplates */
/** @var array<string, array{message_text: string, intro_text: string}> $portalDefaults */
/** @var array<int, string> $availableLocales */
/** @var string $locale */
/** @var array<string, mixed> $monitor */
/** @var string $activeTab */
/** @var string $activeMessageSection */
/** @var string $base_url */

$uncheckedContactCount = count(array_filter(
	$monitorContacts,
	static fn (array $contact): bool => empty($contact['email_checked_at'])
));
$currentStatus = monitor_status($monitor);
$isArchived = !empty($monitor['is_archived']);
$messageOverrideCount = count($messageOverrides);
$portalExpiryDays = isset($monitor['recipient_portal_expiry_days']) ? (int)$monitor['recipient_portal_expiry_days'] : null;
$portalExpiryMode = $portalExpiryDays === null
	? 'none'
	: (in_array($portalExpiryDays, [30, 90, 365], true) ? (string)$portalExpiryDays : 'custom');
$recipientConfigurationWarningCount = count($recipientConfigurationIssues);
$recipientMessageWarningCount = max(0, (int)$defaultRecipientTemplateIssueCount);

foreach ($recipientConfigurationIssues as $configurationIssue)
{
	foreach ((array)($configurationIssue['issues'] ?? []) as $issueCode)
	{
		if ($issueCode !== 'unchecked_recipient')
		{
			$recipientMessageWarningCount++;
			break;
		}
	}
}

$hasCompleteMessageCoverage = $recipientMessageWarningCount === 0;
$tabDefinitions = [
	'details' => 'monitors.tabs.details',
	'schedule' => 'monitors.tabs.schedule',
	'documents' => 'monitors.tabs.documents',
	'recipients' => 'monitors.tabs.recipients',
	'escalation' => 'monitors.tabs.escalation',
	'messages' => 'monitors.tabs.messages_content',
	'review' => 'monitors.tabs.review',
];
$messageSections = [
	'recipient' => 'monitors.messages.sections.recipient',
	'safety' => 'monitors.messages.sections.safety',
	'portal' => 'monitors.messages.sections.portal',
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

<?php if ($isArchived): ?>
	<div class="dashboard-system-warning archived-readonly-notice" role="status">
		<div><strong><?= e__('monitors.archived.readonly.heading') ?></strong><p><?= e__('monitors.archived.readonly.message') ?></p></div>
	</div>
<?php endif; ?>

<form id="monitor-settings-form" method="post" action="<?= e($base_url) ?>/monitors/update" class="form-carrier">
	<?= csrf_field() ?>
	<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
	<input type="hidden" name="active_tab" value="<?= e($activeTab) ?>" data-active-tab-input>
</form>

<div class="monitor-editor" data-monitor-tabs data-active-tab="<?= e($activeTab) ?>">
	<div class="monitor-tabs" role="tablist" aria-label="<?= e__('monitors.tabs.label') ?>">
		<?php $tabNumber = 0; ?>
		<?php foreach ($tabDefinitions as $tabName => $translationKey): ?>
			<?php
			$tabNumber++;
			$isActiveTab = $activeTab === $tabName;
			$tabHasWarning = ($tabName === 'recipients' && $recipientConfigurationWarningCount > 0)
				|| ($tabName === 'messages' && $recipientMessageWarningCount > 0)
				|| ($tabName === 'review' && $recipientConfigurationWarningCount > 0);
			?>
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
				<?php if ($tabHasWarning): ?>
					<span class="tab-warning-indicator" title="<?= e__('monitors.tabs.configuration_warning') ?>" aria-label="<?= e__('monitors.tabs.configuration_warning') ?>">!</span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>

	<fieldset class="monitor-readonly-fieldset"<?= $isArchived ? ' disabled' : '' ?>>
	<section id="monitor-tab-details" class="monitor-tab-panel<?= $activeTab === 'details' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="details"<?= $activeTab === 'details' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.details') ?></h2>
			<p><?= e__('monitors.details.hint') ?></p>
		</div>

		<label for="name"><?= e__('monitors.edit.name') ?></label>
		<input type="text" id="name" name="name" form="monitor-settings-form" value="<?= e((string)$monitor['name']) ?>" required>

		<label for="description"><?= e__('monitors.edit.description') ?></label>
		<textarea id="description" name="description" form="monitor-settings-form" rows="5"><?= e((string)($monitor['description'] ?? '')) ?></textarea>
	</section>

	<section id="monitor-tab-schedule" class="monitor-tab-panel<?= $activeTab === 'schedule' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="schedule"<?= $activeTab === 'schedule' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.schedule') ?></h2>
			<p><?= e__('monitors.schedule.hint') ?></p>
		</div>

		<div class="field-grid field-grid-four">
			<label>
				<?= e__('monitors.edit.check_interval_days') ?>
				<input type="number" name="check_interval_days" form="monitor-settings-form" min="1" max="3650" value="<?= (int)$monitor['check_interval_days'] ?>" required>
			</label>
			<label>
				<?= e__('monitors.edit.response_window_days') ?>
				<input type="number" name="response_window_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['response_window_days'] ?>" required>
			</label>
			<label>
				<?= e__('monitors.edit.reminder_interval_days') ?>
				<input type="number" name="reminder_interval_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['reminder_interval_days'] ?>" required>
			</label>
			<label>
				<?= e__('monitors.edit.max_reminders') ?>
				<input type="number" name="max_reminders" form="monitor-settings-form" min="0" max="100" value="<?= (int)$monitor['max_reminders'] ?>" required>
			</label>
		</div>
	</section>

	<section id="monitor-tab-documents" class="monitor-tab-panel<?= $activeTab === 'documents' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="documents"<?= $activeTab === 'documents' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.documents') ?></h2>
			<p><?= e__('monitors.documents.library_hint') ?></p>
		</div>

		<div class="privacy-note">
			<strong><?= e__('monitors.documents.assignment_heading') ?></strong>
			<?= e__('monitors.documents.assignment_hint') ?>
		</div>

		<div class="document-create-grid">
			<div class="monitor-document-card">
				<h3><?= e__('monitors.documents.text.create.heading') ?></h3>
				<form method="post" action="<?= e($base_url) ?>/monitors/documents/text/create">
					<?= csrf_field() ?>
					<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
					<label for="text_document_title"><?= e__('monitors.documents.upload.title') ?></label>
					<input type="text" id="text_document_title" name="title" required>
					<label for="text_document_content"><?= e__('monitors.documents.text.content') ?></label>
					<textarea id="text_document_content" name="text_content" rows="7" required></textarea>
					<button type="submit"><?= e__('monitors.documents.text.create.submit') ?></button>
				</form>
			</div>

			<div class="monitor-document-card">
				<h3><?= e__('monitors.documents.upload.heading') ?></h3>
				<form method="post" action="<?= e($base_url) ?>/monitors/documents/upload" enctype="multipart/form-data">
					<?= csrf_field() ?>
					<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
					<label for="document_title"><?= e__('monitors.documents.upload.title') ?></label>
					<input type="text" id="document_title" name="title">
					<label for="document_description"><?= e__('monitors.documents.description') ?></label>
					<textarea id="document_description" name="description" rows="3"></textarea>
					<p class="form-hint"><?= e__('monitors.documents.description_hint') ?></p>
					<label for="document_file"><?= e__('monitors.documents.upload.file') ?></label>
					<input type="file" id="document_file" name="document_file" required>
					<button type="submit"><?= e__('monitors.documents.upload.submit') ?></button>
				</form>
			</div>
		</div>

		<?php if ($documents === []): ?>
			<p><?= e__('monitors.documents.none') ?></p>
		<?php else: ?>
			<div class="monitor-document-list">
				<?php foreach ($documents as $document): ?>
					<article class="monitor-document-card">
						<div class="document-card-heading">
							<div>
								<span class="document-type-badge"><?= e__('monitors.documents.type.' . (string)$document['storage_type']) ?></span>
								<h3><?= e((string)$document['title']) ?></h3>
							</div>
							<?php if ((string)$document['storage_type'] === 'file'): ?>
								<a href="<?= e($base_url) ?>/monitors/documents/download?monitor_id=<?= (int)$monitor['id'] ?>&amp;document_id=<?= (int)$document['id'] ?>" class="button-link"><?= e__('monitors.documents.download.submit') ?></a>
							<?php endif; ?>
						</div>

						<?php if ((string)$document['storage_type'] === 'text'): ?>
							<form id="document-update-<?= (int)$document['id'] ?>" method="post" action="<?= e($base_url) ?>/monitors/documents/text/update">
								<?= csrf_field() ?>
								<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
								<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
								<label for="text_title_<?= (int)$document['id'] ?>"><?= e__('monitors.documents.upload.title') ?></label>
								<input type="text" id="text_title_<?= (int)$document['id'] ?>" name="title" value="<?= e((string)$document['title']) ?>" required>
								<label for="text_content_<?= (int)$document['id'] ?>"><?= e__('monitors.documents.text.content') ?></label>
								<textarea id="text_content_<?= (int)$document['id'] ?>" name="text_content" rows="8" required><?= e((string)($document['text_content'] ?? '')) ?></textarea>
							</form>
						<?php else: ?>
							<div class="document-metadata">
								<span><?= e((string)($document['original_filename'] ?? '')) ?></span>
								<span><?= e((string)($document['mime_type'] ?? '')) ?></span>
								<span><?= number_format((int)($document['file_size_bytes'] ?? 0)) ?> bytes</span>
							</div>
							<form id="document-update-<?= (int)$document['id'] ?>" method="post" action="<?= e($base_url) ?>/monitors/documents/file/update">
								<?= csrf_field() ?>
								<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
								<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
								<label for="file_title_<?= (int)$document['id'] ?>"><?= e__('monitors.documents.upload.title') ?></label>
								<input type="text" id="file_title_<?= (int)$document['id'] ?>" name="title" value="<?= e((string)$document['title']) ?>" required>
								<label for="file_description_<?= (int)$document['id'] ?>"><?= e__('monitors.documents.description') ?></label>
								<textarea id="file_description_<?= (int)$document['id'] ?>" name="description" rows="3"><?= e((string)($document['description'] ?? '')) ?></textarea>
								<p class="form-hint"><?= e__('monitors.documents.description_hint') ?></p>
							</form>
						<?php endif; ?>

						<div class="document-card-actions">
							<button type="submit" form="document-update-<?= (int)$document['id'] ?>"><?= e__('monitors.documents.' . ((string)$document['storage_type'] === 'text' ? 'text' : 'file') . '.update.submit') ?></button>
							<form method="post" action="<?= e($base_url) ?>/monitors/documents/delete" data-confirm="<?= e__('monitors.documents.flash.delete_confirm') ?>" class="document-delete-form">
								<?= csrf_field() ?>
								<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
								<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">
								<button type="submit" class="btn-danger"><?= e__('monitors.documents.delete.submit') ?></button>
							</form>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
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
					<?php
					$override = $messageOverrides[(int)$monitorContact['id']] ?? null;
					$configurationIssue = $recipientConfigurationIssues[(int)$monitorContact['id']] ?? null;
					?>
					<article class="recipient-overview-card">
						<div class="recipient-overview-identity">
							<strong><a href="<?= e($base_url) ?>/monitors/recipients/edit?id=<?= (int)$monitorContact['id'] ?>"><?= e((string)$monitorContact['name']) ?></a></strong>
							<small><?= e((string)$monitorContact['email']) ?></small>
						</div>
						<div class="recipient-overview-meta">
							<span><strong><?= e__('recipients.overview.language') ?>:</strong> <?= e(notification_language_name(isset($monitorContact['notification_locale']) ? (string)$monitorContact['notification_locale'] : null)) ?></span>
							<span><strong><?= e__('recipients.overview.message') ?>:</strong> <?= e__(is_array($override) ? 'recipients.overview.personal' : 'recipients.overview.default') ?></span>
							<span><?= e__('recipients.overview.documents', ['count' => (int)$monitorContact['document_count']]) ?></span>
						</div>
						<?php if (is_array($configurationIssue)): ?>
							<div class="recipient-overview-warning" role="alert">
								<?php foreach ((array)$configurationIssue['issues'] as $issueCode): ?>
									<?php if ($issueCode === 'recipient_portal_url_missing'): ?>
										<span><?= e__((string)$configurationIssue['source'] === 'personal' ? 'recipients.overview.issue.url_missing.personal' : 'recipients.overview.issue.url_missing.default', ['language' => notification_language_name((string)$configurationIssue['locale'])]) ?></span>
									<?php elseif ($issueCode === 'unchecked_recipient'): ?>
										<span><?= e__('recipients.overview.issue.unchecked') ?></span>
									<?php elseif ($issueCode === 'incomplete_message'): ?>
										<span><?= e__('recipients.overview.issue.incomplete') ?></span>
									<?php elseif ($issueCode === 'recipient_portal_url_in_subject'): ?>
										<span><?= e__('recipients.overview.issue.url_in_subject') ?></span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
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

		<div data-safety-configuration>
			<div class="configuration-block">
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
									<small><?= e((string)$contact['email']) ?> · <?= e(notification_language_name((string)$contact['notification_locale'])) ?></small>
									<span class="mini-status mini-status-<?= !empty($contact['email_checked_at']) ? 'ok' : 'warning' ?>"><?= e__(!empty($contact['email_checked_at']) ? 'contacts.status.checked' : 'contacts.status.not_checked') ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="configuration-block">
				<h3><?= e__('monitors.escalation.timing.heading') ?></h3>
				<div class="field-grid field-grid-four">
					<label><?= e__('monitors.escalation.response_window') ?><input type="number" name="safety_response_window_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['safety_response_window_days'] ?>" required></label>
					<label><?= e__('monitors.escalation.reminder_interval') ?><input type="number" name="safety_reminder_interval_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['safety_reminder_interval_days'] ?>" required></label>
					<label><?= e__('monitors.escalation.max_reminders') ?><input type="number" name="safety_max_reminders" form="monitor-settings-form" min="0" max="100" value="<?= (int)$monitor['safety_max_reminders'] ?>" required></label>
					<label><?= e__('monitors.escalation.required_confirmations') ?><input type="number" name="safety_required_confirmations" form="monitor-settings-form" min="1" max="100" value="<?= (int)$monitor['safety_required_confirmations'] ?>" required></label>
				</div>
				<label for="safety_confirmation_days"><?= e__('monitors.escalation.confirmation_days') ?></label>
				<input type="number" id="safety_confirmation_days" name="safety_confirmation_days" form="monitor-settings-form" min="0" max="3650" value="<?= (int)($monitor['safety_confirmation_days'] ?? 0) ?>">
				<p class="form-hint"><?= e__('monitors.escalation.confirmation_days_hint') ?></p>
			</div>
		</div>
	</section>

	<section id="monitor-tab-messages" class="monitor-tab-panel<?= $activeTab === 'messages' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="messages"<?= $activeTab === 'messages' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.messages_content') ?></h2>
			<p><?= e__('monitors.messages_content.hint') ?></p>
		</div>

		<div class="unencrypted-warning">
			<strong><?= e__('monitors.storage.warning.heading') ?></strong>
			<?= e__('monitors.storage.warning.message') ?>
		</div>

		<form method="post" action="<?= e($base_url) ?>/monitors/messages/update">
			<?= csrf_field() ?>
			<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
			<input type="hidden" name="message_section" value="<?= e($activeMessageSection) ?>" data-active-subtab-input>

			<div class="editor-subtabs" data-editor-subtabs data-active-subtab="<?= e($activeMessageSection) ?>" data-query-key="section">
				<div class="monitor-tabs editor-subtab-list" role="tablist" aria-label="<?= e__('monitors.messages.sections.label') ?>">
					<?php foreach ($messageSections as $sectionName => $translationKey): ?>
						<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>&amp;tab=messages&amp;section=<?= e($sectionName) ?>" class="monitor-tab-link editor-subtab-link<?= $activeMessageSection === $sectionName ? ' is-active' : '' ?>" role="tab" data-subtab-target="<?= e($sectionName) ?>" aria-selected="<?= $activeMessageSection === $sectionName ? 'true' : 'false' ?>"><?= e__($translationKey) ?></a>
					<?php endforeach; ?>
				</div>

				<section class="editor-subtab-panel" data-subtab-panel="recipient"<?= $activeMessageSection === 'recipient' ? '' : ' hidden' ?>>
					<h3><?= e__('monitors.messages.default.heading') ?></h3>
					<p class="form-hint"><?= e__('monitors.messages.default.hint') ?></p>
					<p class="form-hint"><?= e__('mail.templates.recipient_language_hint') ?></p>
					<div class="language-template-editor" data-language-tabs data-active-language="<?= e(in_array($locale, $availableLocales, true) ? $locale : ($availableLocales[0] ?? 'en')) ?>">
						<div class="language-template-tabs" role="tablist" aria-label="<?= e__('mail.templates.languages') ?>">
							<?php foreach ($availableLocales as $templateLocale): ?>
								<button type="button" class="language-template-tab" role="tab" data-language-target="<?= e($templateLocale) ?>"><?= e(notification_language_name($templateLocale)) ?></button>
							<?php endforeach; ?>
						</div>
						<?php foreach ($availableLocales as $templateLocale): ?>
							<?php
							$templateFieldLocale = language_field_suffix($templateLocale);
							$recipientTemplate = $mailTemplates['recipient_default'][$templateLocale] ?? ['subject' => '', 'body_text' => ''];
							$recipientDefault = $mailDefaults['recipient_default'][$templateLocale] ?? ['subject' => '', 'body_text' => ''];
							$recipientTemplateBody = trim((string)$recipientTemplate['body_text']);
							$recipientTemplateUrlMissing = $recipientTemplateBody !== '' && !str_contains($recipientTemplateBody, '{url}');
							$recipientTemplateUsers = array_values(array_map(
								static fn (array $contact): string => (string)$contact['name'],
								array_filter($monitorContacts, static fn (array $contact): bool => (string)($contact['notification_locale'] ?? '') === $templateLocale && !isset($messageOverrides[(int)$contact['id']]))
							));
							?>
							<div class="language-template-panel" data-language-panel="<?= e($templateLocale) ?>" data-recipient-template-validation data-empty-valid="true">
								<label for="recipient_default_subject_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.subject') ?></label>
								<input type="text" id="recipient_default_subject_<?= e($templateFieldLocale) ?>" name="recipient_default_subject_<?= e($templateFieldLocale) ?>" value="<?= e((string)$recipientTemplate['subject']) ?>">
								<label for="recipient_default_body_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.body') ?></label>
								<textarea id="recipient_default_body_<?= e($templateFieldLocale) ?>" name="recipient_default_body_<?= e($templateFieldLocale) ?>" rows="9" data-recipient-template-body><?= e((string)$recipientTemplate['body_text']) ?></textarea>
								<div class="template-validation-warning" role="alert" data-recipient-url-warning<?= $recipientTemplateUrlMissing ? '' : ' hidden' ?>>
									<strong><?= e__('mail.validation.portal_url_missing.heading') ?></strong>
									<?= e__('monitors.messages.portal_url_missing_warning', ['language' => notification_language_name($templateLocale)]) ?>
									<?php if ($recipientTemplateUsers !== []): ?><small><?= e__('monitors.messages.portal_url_missing_recipients', ['recipients' => implode(', ', $recipientTemplateUsers)]) ?></small><?php endif; ?>
								</div>
								<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{app}</code> — <?= e__('mail.placeholders.app') ?>; <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{url}</code> — <?= e__('mail.placeholders.recipient_url') ?>.</p>
								<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
								<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$recipientDefault['subject']) ?></div><pre><?= e((string)$recipientDefault['body_text']) ?></pre></div></details>
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="editor-subtab-panel" data-subtab-panel="safety"<?= $activeMessageSection === 'safety' ? '' : ' hidden' ?>>
					<h3><?= e__('monitors.escalation.messages.heading') ?></h3>
					<p class="form-hint"><?= e__('monitors.messages.safety_moved_hint') ?></p>
					<p class="form-hint"><?= e__('mail.templates.safety_language_hint') ?></p>
					<div class="language-template-editor" data-language-tabs data-active-language="<?= e(in_array($locale, $availableLocales, true) ? $locale : ($availableLocales[0] ?? 'en')) ?>">
						<div class="language-template-tabs" role="tablist" aria-label="<?= e__('mail.templates.languages') ?>">
							<?php foreach ($availableLocales as $templateLocale): ?><button type="button" class="language-template-tab" role="tab" data-language-target="<?= e($templateLocale) ?>"><?= e(notification_language_name($templateLocale)) ?></button><?php endforeach; ?>
						</div>
						<?php foreach ($availableLocales as $templateLocale): ?>
							<?php
							$templateFieldLocale = language_field_suffix($templateLocale);
							$invitationTemplate = $mailTemplates['safety_invitation'][$templateLocale] ?? ['subject' => '', 'body_text' => ''];
							$reminderTemplate = $mailTemplates['safety_reminder'][$templateLocale] ?? ['subject' => '', 'body_text' => ''];
							$invitationDefault = $mailDefaults['safety_invitation'][$templateLocale] ?? ['subject' => '', 'body_text' => ''];
							$reminderDefault = $mailDefaults['safety_reminder'][$templateLocale] ?? ['subject' => '', 'body_text' => ''];
							?>
							<div class="language-template-panel" data-language-panel="<?= e($templateLocale) ?>">
								<details class="mail-template-kind" open>
									<summary><?= e__('monitors.escalation.messages.invitation.heading') ?></summary>
									<div class="mail-template-kind-body">
										<label for="safety_invitation_subject_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.subject') ?></label><input type="text" id="safety_invitation_subject_<?= e($templateFieldLocale) ?>" name="safety_invitation_subject_<?= e($templateFieldLocale) ?>" value="<?= e((string)$invitationTemplate['subject']) ?>">
										<label for="safety_invitation_body_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.body') ?></label><textarea id="safety_invitation_body_<?= e($templateFieldLocale) ?>" name="safety_invitation_body_<?= e($templateFieldLocale) ?>" rows="7"><?= e((string)$invitationTemplate['body_text']) ?></textarea>
										<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{app}</code> — <?= e__('mail.placeholders.app') ?>; <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{url}</code> — <?= e__('mail.placeholders.safety_url') ?>.</p>
										<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
										<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$invitationDefault['subject']) ?></div><pre><?= e((string)$invitationDefault['body_text']) ?></pre></div></details>
									</div>
								</details>
								<details class="mail-template-kind">
									<summary><?= e__('monitors.escalation.messages.reminder.heading') ?></summary>
									<div class="mail-template-kind-body">
										<label for="safety_reminder_subject_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.subject') ?></label><input type="text" id="safety_reminder_subject_<?= e($templateFieldLocale) ?>" name="safety_reminder_subject_<?= e($templateFieldLocale) ?>" value="<?= e((string)$reminderTemplate['subject']) ?>">
										<label for="safety_reminder_body_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.body') ?></label><textarea id="safety_reminder_body_<?= e($templateFieldLocale) ?>" name="safety_reminder_body_<?= e($templateFieldLocale) ?>" rows="7"><?= e((string)$reminderTemplate['body_text']) ?></textarea>
										<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{app}</code> — <?= e__('mail.placeholders.app') ?>; <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{url}</code> — <?= e__('mail.placeholders.safety_url') ?>. <?= e__('monitors.escalation.messages.reminder_placeholders') ?> <code>{number}</code> — <?= e__('mail.placeholders.reminder_number') ?>; <code>{total}</code> — <?= e__('mail.placeholders.reminder_total') ?>.</p>
										<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
										<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$reminderDefault['subject']) ?></div><pre><?= e((string)$reminderDefault['body_text']) ?></pre></div></details>
									</div>
								</details>
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="editor-subtab-panel" data-subtab-panel="portal"<?= $activeMessageSection === 'portal' ? '' : ' hidden' ?>>
					<h3><?= e__('monitors.messages.portal_content.heading') ?></h3>
					<p class="form-hint"><?= e__('monitors.messages.portal_content.hint') ?></p>
					<div class="language-template-editor" data-language-tabs data-active-language="<?= e(in_array($locale, $availableLocales, true) ? $locale : ($availableLocales[0] ?? 'en')) ?>">
						<div class="language-template-tabs" role="tablist" aria-label="<?= e__('mail.templates.languages') ?>">
							<?php foreach ($availableLocales as $templateLocale): ?><button type="button" class="language-template-tab" role="tab" data-language-target="<?= e($templateLocale) ?>"><?= e(notification_language_name($templateLocale)) ?></button><?php endforeach; ?>
						</div>
						<?php foreach ($availableLocales as $templateLocale): ?>
							<?php
							$templateFieldLocale = language_field_suffix($templateLocale);
							$portalTemplate = $portalTemplates[$templateLocale] ?? ['message_text' => '', 'intro_text' => ''];
							$portalDefault = $portalDefaults[$templateLocale] ?? ['message_text' => '', 'intro_text' => ''];
							?>
							<div class="language-template-panel" data-language-panel="<?= e($templateLocale) ?>">
								<label for="portal_message_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.portal_content.message') ?></label>
								<textarea id="portal_message_<?= e($templateFieldLocale) ?>" name="portal_message_<?= e($templateFieldLocale) ?>" rows="7"><?= e((string)$portalTemplate['message_text']) ?></textarea>
								<p class="form-hint"><?= e__('monitors.messages.portal_content.message_hint') ?></p>
								<p class="form-hint placeholder-help"><?= e__('monitors.messages.portal_content.placeholders') ?> <code>{app}</code> — <?= e__('mail.placeholders.app') ?>; <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>.</p>
								<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><pre><?= e((string)$portalDefault['message_text']) ?></pre></div></details>

								<label for="portal_intro_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.portal_content.intro') ?></label>
								<textarea id="portal_intro_<?= e($templateFieldLocale) ?>" name="portal_intro_<?= e($templateFieldLocale) ?>" rows="5"><?= e((string)$portalTemplate['intro_text']) ?></textarea>
								<p class="form-hint"><?= e__('monitors.messages.portal_content.intro_hint') ?></p>
								<p class="form-hint placeholder-help"><?= e__('monitors.messages.portal_content.placeholders') ?> <code>{app}</code> — <?= e__('mail.placeholders.app') ?>; <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>.</p>
								<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><pre><?= e((string)$portalDefault['intro_text']) ?></pre></div></details>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="configuration-block portal-expiry-settings" data-portal-expiry>
						<h3><?= e__('monitors.messages.portal_expiry.heading') ?></h3>
						<p class="form-hint"><?= e__('monitors.messages.portal_expiry.hint') ?></p>
						<label for="recipient_portal_expiry_mode"><?= e__('monitors.messages.portal_expiry.label') ?></label>
						<select id="recipient_portal_expiry_mode" name="recipient_portal_expiry_mode" data-portal-expiry-mode>
							<option value="none"<?= $portalExpiryMode === 'none' ? ' selected' : '' ?>><?= e__('monitors.messages.portal_expiry.none') ?></option>
							<option value="30"<?= $portalExpiryMode === '30' ? ' selected' : '' ?>><?= e__('monitors.messages.portal_expiry.30') ?></option>
							<option value="90"<?= $portalExpiryMode === '90' ? ' selected' : '' ?>><?= e__('monitors.messages.portal_expiry.90') ?></option>
							<option value="365"<?= $portalExpiryMode === '365' ? ' selected' : '' ?>><?= e__('monitors.messages.portal_expiry.365') ?></option>
							<option value="custom"<?= $portalExpiryMode === 'custom' ? ' selected' : '' ?>><?= e__('monitors.messages.portal_expiry.custom') ?></option>
						</select>
						<div data-portal-expiry-custom<?= $portalExpiryMode === 'custom' ? '' : ' hidden' ?>><label for="recipient_portal_expiry_custom_days"><?= e__('monitors.messages.portal_expiry.custom_days') ?></label><input type="number" id="recipient_portal_expiry_custom_days" name="recipient_portal_expiry_custom_days" min="1" max="3650" value="<?= $portalExpiryMode === 'custom' ? (int)$portalExpiryDays : 90 ?>"></div>
						<p class="form-hint"><?= e__('monitors.messages.portal_expiry.starts_on_send') ?></p>
					</div>
				</section>
			</div>

			<p class="form-hint"><?= e__('monitors.messages.recipient_pages_hint') ?></p>
			<button type="submit" class="btn-primary"><?= e__('monitors.messages.submit') ?></button>
		</form>
	</section>

	</fieldset>

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
			<?php if ($monitorContacts === []): ?><div class="review-warning"><?= e__('monitors.review.warning.no_recipients') ?></div><?php endif; ?>
			<?php if ($uncheckedContactCount > 0 && empty($monitor['is_paused'])): ?><div class="review-warning"><?= e__('monitors.review.warning.unchecked', ['count' => $uncheckedContactCount]) ?></div><?php endif; ?>
			<?php if ($recipientMessageWarningCount > 0): ?><div class="review-warning"><?= e__('monitors.review.warning.recipient_configuration', ['count' => $recipientMessageWarningCount]) ?></div><?php endif; ?>
			<?php if (!$hasCompleteMessageCoverage): ?><div class="review-warning"><?= e__('monitors.review.warning.no_message') ?></div><?php endif; ?>
			<?php if ((string)$monitor['escalation_policy'] === 'safety_contact' && $safetyContactIds === []): ?><div class="review-warning"><?= e__('monitors.review.warning.no_safety_contacts') ?></div><?php endif; ?>
			<?php if ($monitorContacts !== [] && $recipientConfigurationWarningCount === 0 && $hasCompleteMessageCoverage): ?><div class="review-ready"><?= e__('monitors.review.ready') ?></div><?php endif; ?>
		</div>

		<div class="activation-card">
			<?php if ($currentStatus === 'escalated'): ?>
				<div><h3><?= e__('monitors.activation.heading') ?></h3><p><?= e__('monitors.activation.escalated_hint') ?></p></div>
				<div class="table-actions">
					<form method="post" action="<?= e($base_url) ?>/monitors/reset-reactivate" data-confirm="<?= e__('monitors.reset.confirm') ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
						<button type="submit" class="btn-primary"><?= e__('monitors.reset.submit') ?></button>
					</form>
					<form method="post" action="<?= e($base_url) ?>/monitors/archive" data-confirm="<?= e__('monitors.archive.confirm') ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
						<button type="submit"><?= e__('monitors.archive.submit') ?></button>
					</form>
				</div>
			<?php elseif ($currentStatus === 'archived'): ?>
				<div><h3><?= e__('monitors.activation.heading') ?></h3><p><?= e__('monitors.activation.archived_hint') ?></p></div>
				<form method="post" action="<?= e($base_url) ?>/monitors/reset-reactivate" data-confirm="<?= e__('monitors.reset.confirm') ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
					<button type="submit" class="btn-primary"><?= e__('monitors.reset.submit') ?></button>
				</form>
			<?php else: ?>
				<div><h3><?= e__('monitors.activation.heading') ?></h3><p><?= e__($currentStatus === 'paused' ? 'monitors.activation.paused_hint' : 'monitors.activation.active_hint') ?></p></div>
				<form method="post" action="<?= e($base_url) ?>/monitors/<?= $currentStatus === 'paused' ? 'resume' : 'pause' ?>">
					<?= csrf_field() ?>
					<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
					<input type="hidden" name="redirect" value="/monitors/edit?id=<?= (int)$monitor['id'] ?>&amp;tab=review">
					<button type="submit" class="<?= $currentStatus === 'paused' ? 'btn-primary' : '' ?>"><?= e__($currentStatus === 'paused' ? 'monitors.resume.submit' : 'monitors.pause.submit') ?></button>
				</form>
			<?php endif; ?>
		</div>
	</section>
</div>

<?php if (!$isArchived): ?>
	<div class="editor-save-bar" data-settings-save-bar data-settings-tabs="details,schedule,escalation,review"<?= in_array($activeTab, ['details', 'schedule', 'escalation', 'review'], true) ? '' : ' hidden' ?>>
		<span><?= e__('monitors.edit.save_hint') ?></span>
		<div class="editor-save-actions">
			<a href="<?= e($base_url) ?>/monitors" class="button-link editor-cancel-button"><?= e__('monitors.edit.cancel') ?></a>
			<button type="submit" form="monitor-settings-form" class="btn-primary"><?= e__('monitors.edit.submit') ?></button>
		</div>
	</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = e__('monitors.edit.title');
require __DIR__ . '/../layouts/main.php';
