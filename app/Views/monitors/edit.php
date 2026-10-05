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
/** @var string $ownerNotificationLocale */
/** @var array<string, array{message_text: string, intro_text: string}> $portalTemplates */
/** @var array<string, array{message_text: string, intro_text: string}> $portalDefaults */
/** @var array<int, string> $availableLocales */
/** @var string $locale */
/** @var array<string, mixed> $monitor */
/** @var array<string, mixed>|null $monitorStatus */
/** @var array<int, array<string, mixed>> $monitorHistory */
/** @var string $activeTab */
/** @var string $activeMessageSection */
/** @var string $base_url */
/** @var int $uploadMaximumBytes */

$uncheckedContactCount = count(array_filter(
	$monitorContacts,
	static fn (array $contact): bool => !\Pulse\Core\EmailAddressCollection::HasChecked($contact)
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
$eligibleSafetyContactCount = count(array_filter(
	$contacts,
	static fn (array $contact): bool => in_array((int)$contact['id'], $safetyContactIds, true)
		&& \Pulse\Core\EmailAddressCollection::HasChecked($contact)
));
$safetyRequiredConfirmations = (int)$monitor['safety_required_confirmations'];
$safetyConfigurationWarning = (string)$monitor['escalation_policy'] === 'safety_contact'
	&& $eligibleSafetyContactCount < $safetyRequiredConfirmations;

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
$uploadSizeMegabytes = max(0.1, $uploadMaximumBytes / 1048576);
$uploadSizeLabel = number_format($uploadSizeMegabytes, $uploadSizeMegabytes >= 10 || floor($uploadSizeMegabytes) === $uploadSizeMegabytes ? 0 : 1) . ' MB';
$tabDefinitions = [
	'status' => 'monitors.tabs.status',
	'details' => 'monitors.tabs.details',
	'schedule' => 'monitors.tabs.schedule',
	'documents' => 'monitors.tabs.documents',
	'recipients' => 'monitors.tabs.recipients',
	'escalation' => 'monitors.tabs.escalation',
	'messages' => 'monitors.tabs.messages_content',
	'review' => 'monitors.tabs.review',
];
$messageSections = [
	'owner' => 'monitors.messages.sections.owner',
	'recipient' => 'monitors.messages.sections.recipient',
	'safety' => 'monitors.messages.sections.safety',
	'portal' => 'monitors.messages.sections.portal',
];

$monitorContactByContactId = [];

foreach ($monitorContacts as $monitorContact)
{
	$monitorContactByContactId[(int)$monitorContact['contact_id']] = $monitorContact;
}

$recipientAssignmentContacts = $contacts;
usort($recipientAssignmentContacts, static function (array $left, array $right) use ($assignedContactIds): int
{
	$leftAssigned = in_array((int)$left['id'], $assignedContactIds, true);
	$rightAssigned = in_array((int)$right['id'], $assignedContactIds, true);

	if ($leftAssigned !== $rightAssigned)
	{
		return $leftAssigned ? -1 : 1;
	}

	return strcasecmp((string)$left['name'], (string)$right['name']);
});

$statusHistoryTranslationKeys = [
	'monitor.checked_in' => 'dashboard.activity.checked_in',
	'monitor.awaiting' => 'dashboard.activity.awaiting',
	'monitor.safety_requested' => 'dashboard.activity.safety_requested',
	'monitor.safety_expired' => 'dashboard.activity.safety_expired',
	'monitor.safety_confirmed' => 'dashboard.activity.safety_confirmed',
	'monitor.overdue' => 'dashboard.activity.overdue',
	'monitor.escalated' => 'dashboard.activity.escalated',
	'monitor.reset_reactivated' => 'dashboard.activity.reset_reactivated',
	'monitor.archived' => 'dashboard.activity.archived',
	'monitor.paused' => 'dashboard.activity.paused',
	'monitor.resumed' => 'dashboard.activity.resumed',
	'monitor.forced_due' => 'dashboard.activity.forced_due',
	'mail.due_notice_sent' => 'dashboard.activity.due_notice_sent',
	'mail.reminder_sent' => 'dashboard.activity.reminder_sent',
	'mail.safety_invitation_sent' => 'dashboard.activity.safety_invitation_sent',
	'mail.safety_reminder_sent' => 'dashboard.activity.safety_reminder_sent',
	'mail.recipient_sent' => 'dashboard.activity.recipient_sent',
	'mail.recipient_failed' => 'dashboard.activity.recipient_failed',
];
$statusHistoryCategories = [
	'monitor.checked_in' => 'checkin',
	'monitor.awaiting' => 'state',
	'monitor.safety_requested' => 'safety',
	'monitor.safety_expired' => 'safety',
	'monitor.safety_confirmed' => 'safety',
	'monitor.overdue' => 'escalation',
	'monitor.escalated' => 'escalation',
	'monitor.reset_reactivated' => 'state',
	'monitor.archived' => 'state',
	'monitor.paused' => 'state',
	'monitor.resumed' => 'state',
	'monitor.forced_due' => 'state',
	'mail.due_notice_sent' => 'notification',
	'mail.reminder_sent' => 'notification',
	'mail.safety_invitation_sent' => 'safety',
	'mail.safety_reminder_sent' => 'safety',
	'mail.recipient_sent' => 'notification',
	'mail.recipient_failed' => 'failure',
];
$lastMonitorEvent = $monitorHistory[0] ?? null;

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

<form id="monitor-settings-form" method="post" action="<?= e($base_url) ?>/monitors/update" class="form-carrier" data-monitor-settings-form>
	<?= csrf_field() ?>
	<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
	<input type="hidden" name="active_tab" value="<?= e($activeTab) ?>" data-active-tab-input>
</form>

<div class="monitor-editor" data-monitor-tabs data-active-tab="<?= e($activeTab) ?>">
	<div class="monitor-tabs monitor-primary-tabs" role="tablist" aria-label="<?= e__('monitors.tabs.label') ?>">
		<?php $tabNumber = 0; ?>
		<?php foreach ($tabDefinitions as $tabName => $translationKey): ?>
			<?php
			$tabNumber++;
			$isActiveTab = $activeTab === $tabName;
			$tabHasWarning = ($tabName === 'recipients' && $recipientConfigurationWarningCount > 0)
				|| ($tabName === 'messages' && $recipientMessageWarningCount > 0)
				|| ($tabName === 'escalation' && $safetyConfigurationWarning)
				|| ($tabName === 'review' && ($recipientConfigurationWarningCount > 0 || $safetyConfigurationWarning));
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
	<section id="monitor-tab-status" class="monitor-tab-panel<?= $activeTab === 'status' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="status"<?= $activeTab === 'status' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.status.heading') ?></h2>
			<p><?= e__('monitors.status.hint') ?></p>
		</div>

		<?php
		$operationalStatus = is_array($monitorStatus) ? $monitorStatus : [];
		$statusIssues = is_array($operationalStatus['issues'] ?? null) ? $operationalStatus['issues'] : [];
		$nextAction = is_array($operationalStatus['next_action'] ?? null) ? $operationalStatus['next_action'] : ['type' => 'none', 'at' => null];
		$futurePlan = is_array($operationalStatus['plan'] ?? null) ? $operationalStatus['plan'] : [];
		$needsAttention = $statusIssues !== [];
		$lastEventTranslationKey = is_array($lastMonitorEvent)
			? ($statusHistoryTranslationKeys[(string)($lastMonitorEvent['event_type'] ?? '')] ?? null)
			: null;
		?>

		<?php if (!$needsAttention): ?>
			<div class="monitor-health-banner monitor-health-ok" role="status">
				<strong><?= e__('monitors.status.health.ok.heading') ?></strong>
				<span><?= e__('monitors.status.summary.' . $currentStatus) ?></span>
			</div>
		<?php else: ?>
			<div class="monitor-health-banner monitor-health-warning" role="alert">
				<strong><?= e__('monitors.status.health.attention.heading') ?></strong>
				<span><?= e__('monitors.status.summary.' . $currentStatus) ?></span>
			</div>
		<?php endif; ?>


		<div class="monitor-status-summary-grid">
			<div class="review-stat">
				<span class="monitor-status-card-label"><?= e__('monitors.status.current') ?></span>
				<strong><span class="status-badge status-<?= e($currentStatus) ?>"><?= e__('monitors.status.' . $currentStatus) ?></span></strong>
			</div>
			<div class="review-stat monitor-status-last-event">
				<span class="monitor-status-card-label"><?= e__('monitors.status.last_event') ?></span>
				<?php if (is_array($lastMonitorEvent) && is_string($lastEventTranslationKey)): ?>
					<strong><?= e__($lastEventTranslationKey, ['name' => (string)$monitor['name']]) ?></strong>
					<span><?= e(format_datetime((string)$lastMonitorEvent['created_at'])) ?></span>
				<?php else: ?>
					<strong><?= e__('monitors.status.history.none_short') ?></strong>
				<?php endif; ?>
			</div>
			<div class="review-stat monitor-status-next-action">
				<span class="monitor-status-card-label"><?= e__('monitors.status.next_action') ?></span>
				<strong><?= e(monitor_action_label($nextAction)) ?></strong>
				<span><?= e(monitor_action_time_label($nextAction)) ?></span>
			</div>
		</div>

		<section class="configuration-block monitor-status-plan">
			<h3><?= e__('monitors.status.next.heading') ?></h3>
			<p class="form-hint"><?= e__('monitors.status.next.hint') ?></p>
			<?php if ($futurePlan === []): ?>
				<p><?= e__('monitors.status.next.none') ?></p>
			<?php else: ?>
				<ol class="monitor-status-timeline">
					<?php foreach ($futurePlan as $index => $plannedAction): ?>
						<?php
						$conditionLabel = monitor_action_condition_label($plannedAction);
						$itemClasses = [];
						$plannedType = (string)($plannedAction['type'] ?? 'none');
						$nextType = (string)($nextAction['type'] ?? 'none');
						$isNextAction = $plannedType === $nextType;

						if ($isNextAction && isset($plannedAction['number'], $nextAction['number']))
						{
							$isNextAction = (int)$plannedAction['number'] === (int)$nextAction['number'];
						}

						if ($isNextAction || ($index === 0 && $nextType === 'check_due'))
						{
							$itemClasses[] = 'is-next';
						}

						if ($conditionLabel !== '')
						{
							$itemClasses[] = 'is-conditional';
						}
						?>
						<li<?= $itemClasses === [] ? '' : ' class="' . e(implode(' ', $itemClasses)) . '"' ?>>
							<span class="monitor-status-timeline-marker" aria-hidden="true"></span>
							<div>
								<strong><?= e(monitor_action_label($plannedAction)) ?></strong>
								<span class="monitor-status-timeline-time"><?= e(monitor_action_time_label($plannedAction)) ?></span>
								<?php if ($conditionLabel !== ''): ?>
									<small class="monitor-status-timeline-condition"><?= e($conditionLabel) ?></small>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
			<p class="form-hint"><?= e__('monitors.status.next.cron_note') ?></p>
		</section>

		<section class="configuration-block monitor-status-history">
			<h3><?= e__('monitors.status.history.heading') ?></h3>
			<p class="form-hint"><?= e__('monitors.status.history.hint', ['count' => 100]) ?></p>
			<?php if ($monitorHistory === []): ?>
				<p><?= e__('monitors.status.history.none') ?></p>
			<?php else: ?>
				<div class="monitor-status-history-scroll">
					<ol class="monitor-status-history-list">
						<?php foreach ($monitorHistory as $entry): ?>
							<?php
							$eventType = (string)$entry['event_type'];
							$translationKey = $statusHistoryTranslationKeys[$eventType] ?? null;
							$eventCategory = $statusHistoryCategories[$eventType] ?? 'state';
							?>
							<?php if (is_string($translationKey)): ?>
								<li class="monitor-history-event monitor-history-event-<?= e($eventCategory) ?>">
									<span class="monitor-event-badge"><?= e__('monitors.status.history.category.' . $eventCategory) ?></span>
									<span><?= e__($translationKey, ['name' => (string)$monitor['name']]) ?></span>
									<time datetime="<?= e((string)$entry['created_at']) ?>"><?= e(format_datetime((string)$entry['created_at'])) ?></time>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>
		</section>
	</section>

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
				<small><?= e__('monitors.edit.check_interval_days_hint') ?></small>
			</label>
			<label>
				<?= e__('monitors.edit.response_window_days') ?>
				<input type="number" name="response_window_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['response_window_days'] ?>" required>
				<small><?= e__('monitors.edit.response_window_days_hint') ?></small>
			</label>
			<label>
				<?= e__('monitors.edit.reminder_interval_days') ?>
				<input type="number" name="reminder_interval_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['reminder_interval_days'] ?>" required>
				<small><?= e__('monitors.edit.reminder_interval_days_hint') ?></small>
			</label>
			<label>
				<?= e__('monitors.edit.max_reminders') ?>
				<input type="number" name="max_reminders" form="monitor-settings-form" min="0" max="100" value="<?= (int)$monitor['max_reminders'] ?>" required>
				<small><?= e__('monitors.edit.max_reminders_hint') ?></small>
			</label>
		</div>

		<div class="configuration-block check-in-location-settings" data-location-settings data-location-permission-settings data-location-requesting="<?= e__('location.permission.requesting') ?>" data-location-recorded="<?= e__('location.permission.granted') ?>" data-location-unavailable="<?= e__('location.permission.unavailable') ?>" data-location-denied="<?= e__('location.permission.denied') ?>">
			<h3><?= e__('monitors.location.heading') ?></h3>
			<label class="checkbox-option" for="location_check_in_enabled">
				<input
					type="checkbox"
					id="location_check_in_enabled"
					name="location_check_in_enabled"
					form="monitor-settings-form"
					value="1"
					data-location-recording-toggle
					<?= !empty($monitor['location_check_in_enabled']) ? 'checked' : '' ?>
				>
				<span><strong><?= e__('monitors.location.record.label') ?></strong><small><?= e__('monitors.location.record.hint') ?></small></span>
			</label>
			<small class="check-in-location-status" data-location-permission-status hidden aria-live="polite"></small>

			<div class="location-sharing-settings" data-location-sharing-settings<?= !empty($monitor['location_check_in_enabled']) ? '' : ' hidden' ?>>
				<label class="checkbox-option" for="portal_location_sharing_enabled">
					<input
						type="checkbox"
						id="portal_location_sharing_enabled"
						name="portal_location_sharing_enabled"
						form="monitor-settings-form"
						value="1"
						data-location-sharing-toggle
						<?= !empty($monitor['portal_location_sharing_enabled']) ? 'checked' : '' ?>
					>
					<span><strong><?= e__('monitors.location.portal.label') ?></strong><small><?= e__('monitors.location.portal.hint') ?></small></span>
				</label>

				<label class="location-history-limit" data-location-history-limit<?= !empty($monitor['portal_location_sharing_enabled']) ? '' : ' hidden' ?>>
					<?= e__('monitors.location.portal.count') ?>
					<input type="number" name="portal_location_history_limit" form="monitor-settings-form" min="1" max="20" value="<?= max(1, min(20, (int)($monitor['portal_location_history_limit'] ?? 5))) ?>" required>
					<small><?= e__('monitors.location.portal.count_hint') ?></small>
				</label>
			</div>

			<p class="privacy-note"><?= e__('monitors.location.privacy') ?></p>
		</div>
	</section>

	<section id="monitor-tab-documents" class="monitor-tab-panel<?= $activeTab === 'documents' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="documents"<?= $activeTab === 'documents' ? '' : ' hidden' ?>>
		<div class="section-heading document-library-heading">
			<div>
				<h2><?= e__('monitors.tabs.documents') ?></h2>
				<p><?= e__('monitors.documents.library_hint') ?></p>
			</div>
			<?php if (!$isArchived): ?>
				<div class="document-library-add-actions">
					<a href="<?= e($base_url) ?>/monitors/documents/text/new?monitor_id=<?= (int)$monitor['id'] ?>" class="button-link"><?= e__('monitors.documents.text.create.action') ?></a>
					<button
						type="button"
						class="button-link"
						data-document-upload-toggle
						aria-expanded="false"
						aria-controls="monitor-document-upload"
					><?= e__('monitors.documents.upload.heading') ?></button>
				</div>
			<?php endif; ?>
		</div>

		<?php if (!$isArchived): ?>
		<div id="monitor-document-upload" class="document-upload-panel configuration-block" data-document-upload-panel hidden>
			<h3><?= e__('monitors.documents.upload.heading') ?></h3>
			<form method="post" action="<?= e($base_url) ?>/monitors/documents/upload" enctype="multipart/form-data" class="document-upload-form">
				<?= csrf_field() ?>
				<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
				<div class="field-grid field-grid-two">
					<label><?= e__('monitors.documents.upload.title') ?><input type="text" name="title"></label>
					<label><?= e__('monitors.documents.upload.file') ?><input type="file" name="document_file" required></label>
				</div>
				<label><?= e__('monitors.documents.description') ?><textarea name="description" rows="3"></textarea></label>
				<p class="form-hint"><?= e__('monitors.documents.description_hint') ?></p>
				<p class="form-hint"><?= e__('monitors.documents.upload.preview_hint', ['size' => $uploadSizeLabel]) ?></p>
				<button type="submit"><?= e__('monitors.documents.upload.submit') ?></button>
			</form>
		</div>
		<?php endif; ?>

		<div class="privacy-note">
			<strong><?= e__('monitors.documents.assignment_heading') ?></strong>
			<?= e__('monitors.documents.assignment_hint') ?>
		</div>

		<?php if ($documents === []): ?>
			<p><?= e__('monitors.documents.none') ?></p>
		<?php else: ?>
			<div class="document-library-list">
				<?php foreach ($documents as $document): ?>
					<?php
					$isTextDocument = (string)$document['storage_type'] === 'text';
					$textContent = (string)($document['text_content'] ?? '');
					$textLength = strlen(preg_replace('/[\x80-\xBF]/', '', $textContent) ?? $textContent);
					?>
					<article class="document-library-item">
						<div class="document-library-main">
							<span class="document-type-badge"><?= e__('monitors.documents.type.' . (string)$document['storage_type']) ?></span>
							<div>
								<h3><?= e((string)$document['title']) ?></h3>
								<div class="document-library-meta">
									<span><?= e__('monitors.documents.list.modified') ?>: <?= e(format_datetime((string)$document['updated_at'])) ?></span>
									<?php if ($isTextDocument): ?>
										<span><?= e__('monitors.documents.list.text_size', ['count' => number_format($textLength)]) ?></span>
									<?php else: ?>
										<span><?= e((string)($document['original_filename'] ?? '')) ?></span>
										<span><?= e__('monitors.documents.list.file_size', ['count' => number_format((int)($document['file_size_bytes'] ?? 0))]) ?></span>
									<?php endif; ?>
								</div>
							</div>
						</div>
						<div class="document-library-actions">
							<a href="<?= e($base_url) ?>/monitors/documents/edit?monitor_id=<?= (int)$monitor['id'] ?>&amp;document_id=<?= (int)$document['id'] ?>" class="button-link"><?= e__('monitors.documents.list.edit') ?></a>
							<?php if (!$isTextDocument): ?>
								<a href="<?= e($base_url) ?>/monitors/documents/download?monitor_id=<?= (int)$monitor['id'] ?>&amp;document_id=<?= (int)$document['id'] ?>" class="button-link"><?= e__('monitors.documents.download.submit') ?></a>
							<?php endif; ?>
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
			<p><?= e__('monitors.recipients.assignment_hint') ?></p>
		</div>

		<div class="privacy-note recipient-assignment-note">
			<strong><?= e__('monitors.contacts.silent.heading') ?></strong>
			<?= e__('monitors.contacts.silent.message') ?>
		</div>

		<?php if ($contacts === []): ?>
			<p><?= e__('monitors.contacts.none') ?></p>
		<?php else: ?>
			<form method="post" action="<?= e($base_url) ?>/monitors/recipients/assignments" class="recipient-assignment-form" data-recipient-assignment-form data-removal-confirm="<?= e__('recipients.assignments.remove_confirm') ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">

				<div class="recipient-assignment-toolbar" aria-label="<?= e__('recipients.assignments.sort.label') ?>">
					<span><?= e__('recipients.assignments.sort.label') ?></span>
					<div class="recipient-assignment-sort-buttons">
						<button type="button" class="button-link is-active" data-recipient-sort="assigned"><?= e__('recipients.assignments.sort.assigned') ?></button>
						<button type="button" class="button-link" data-recipient-sort="name"><?= e__('recipients.assignments.sort.name') ?></button>
					</div>
				</div>

				<div class="recipient-assignment-list" data-recipient-assignment-list>
					<?php foreach ($recipientAssignmentContacts as $contact): ?>
						<?php
						$contactId = (int)$contact['id'];
						$isAssigned = in_array($contactId, $assignedContactIds, true);
						$monitorContact = $monitorContactByContactId[$contactId] ?? null;
						$configurationIssue = is_array($monitorContact) ? ($recipientConfigurationIssues[(int)$monitorContact['id']] ?? null) : null;
						$addresses = \Pulse\Core\EmailAddressCollection::FromRow($contact);
						?>
						<article class="recipient-assignment-card<?= $isAssigned ? ' is-assigned' : '' ?>" data-recipient-assignment-card data-recipient-name="<?= e((string)$contact['name']) ?>" data-assigned="<?= $isAssigned ? '1' : '0' ?>">
							<div class="recipient-assignment-main">
								<input
									type="checkbox"
									id="recipient_assignment_<?= $contactId ?>"
									name="contact_ids[]"
									value="<?= $contactId ?>"
									data-recipient-assignment-checkbox
									data-initially-assigned="<?= $isAssigned ? '1' : '0' ?>"
									<?= $isAssigned ? 'checked' : '' ?>
								>
								<label for="recipient_assignment_<?= $contactId ?>" class="recipient-assignment-identity">
									<strong><?= e((string)$contact['name']) ?></strong>
									<small><?= e(implode(', ', array_column($addresses, 'email'))) ?></small>
								</label>
							</div>

							<div class="recipient-assignment-meta">
								<span><strong><?= e__('recipients.overview.language') ?>:</strong> <?= e(notification_language_name(isset($contact['notification_locale']) ? (string)$contact['notification_locale'] : null)) ?></span>
								<?php if ($isAssigned && is_array($monitorContact)): ?>
									<span><?= e__('recipients.overview.documents', ['count' => (int)$monitorContact['document_count']]) ?></span>
								<?php endif; ?>
							</div>

							<div class="recipient-assignment-actions">
								<span class="mini-status <?= $isAssigned ? 'mini-status-ok' : 'recipient-assignment-unassigned' ?>" data-recipient-assignment-state data-label-assigned="<?= e__('recipients.assignments.assigned') ?>" data-label-unassigned="<?= e__('recipients.assignments.not_assigned') ?>"><?= e__($isAssigned ? 'recipients.assignments.assigned' : 'recipients.assignments.not_assigned') ?></span>
								<?php if ($isAssigned && is_array($monitorContact)): ?>
									<a href="<?= e($base_url) ?>/monitors/recipients/edit?id=<?= (int)$monitorContact['id'] ?>" class="button-link recipient-assignment-configure"><?= e__('recipients.assignments.configure') ?></a>
								<?php endif; ?>
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

				<p class="form-hint recipient-assignment-removal-hint"><?= e__('recipients.assignments.removal_hint') ?></p>
				<button type="submit"><?= e__('recipients.assignments.save') ?></button>
			</form>
		<?php endif; ?>

		<p class="form-hint recipient-safety-hint">
			<?= e__('recipients.overview.safety_hint') ?>
			<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>&amp;tab=escalation"><?= e__('recipients.overview.safety_action') ?></a>
		</p>
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
								<input type="checkbox" name="safety_contact_ids[]" form="monitor-settings-form" value="<?= (int)$contact['id'] ?>" data-safety-contact-eligible="<?= \Pulse\Core\EmailAddressCollection::HasChecked($contact) ? '1' : '0' ?>" <?= in_array((int)$contact['id'], $safetyContactIds, true) ? 'checked' : '' ?>>
								<span>
									<strong><?= e((string)$contact['name']) ?></strong><br>
									<small><?= e(implode(', ', array_column(\Pulse\Core\EmailAddressCollection::FromRow($contact), 'email'))) ?> · <?= e(notification_language_name((string)$contact['notification_locale'])) ?></small>
									<span class="mini-status mini-status-<?= \Pulse\Core\EmailAddressCollection::HasChecked($contact) ? 'ok' : 'warning' ?>"><?= e__(\Pulse\Core\EmailAddressCollection::HasChecked($contact) ? 'contacts.status.checked_available' : 'contacts.status.none_checked') ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="configuration-block">
				<h3><?= e__('monitors.escalation.timing.heading') ?></h3>
				<div class="field-grid field-grid-four">
					<label><?= e__('monitors.escalation.response_window') ?><input type="number" name="safety_response_window_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['safety_response_window_days'] ?>" required><small><?= e__('monitors.escalation.response_window_hint') ?></small></label>
					<label><?= e__('monitors.escalation.reminder_interval') ?><input type="number" name="safety_reminder_interval_days" form="monitor-settings-form" min="1" max="365" value="<?= (int)$monitor['safety_reminder_interval_days'] ?>" required><small><?= e__('monitors.escalation.reminder_interval_hint') ?></small></label>
					<label><?= e__('monitors.escalation.max_reminders') ?><input type="number" name="safety_max_reminders" form="monitor-settings-form" min="0" max="100" value="<?= (int)$monitor['safety_max_reminders'] ?>" required><small><?= e__('monitors.escalation.max_reminders_hint') ?></small></label>
					<label><?= e__('monitors.escalation.required_confirmations') ?><input type="number" name="safety_required_confirmations" form="monitor-settings-form" min="1" max="100" value="<?= $safetyRequiredConfirmations ?>" data-safety-required-confirmations required><small><?= e__('monitors.escalation.required_confirmations_hint') ?></small></label>
					<label><?= e__('monitors.escalation.confirmation_days') ?><input type="number" name="safety_confirmation_days" form="monitor-settings-form" min="0" max="3650" value="<?= (int)($monitor['safety_confirmation_days'] ?? 0) ?>"><small><?= e__('monitors.escalation.confirmation_days_hint') ?></small></label>
				</div>
				<div class="template-validation-warning safety-confirmation-warning" role="alert" data-safety-confirmation-warning data-message-template="<?= e__('monitors.escalation.confirmation_warning.message', ['required' => '{required}', 'available' => '{available}']) ?>"<?= $safetyConfigurationWarning ? '' : ' hidden' ?>>
					<strong><?= e__('monitors.escalation.confirmation_warning.heading') ?></strong>
					<span data-safety-confirmation-warning-message><?= e__('monitors.escalation.confirmation_warning.message', ['required' => $safetyRequiredConfirmations, 'available' => $eligibleSafetyContactCount]) ?></span>
				</div>
			</div>
		</div>
	</section>

	<section id="monitor-tab-messages" class="monitor-tab-panel monitor-messages-panel<?= $activeTab === 'messages' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="messages"<?= $activeTab === 'messages' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('monitors.tabs.messages_content') ?></h2>
			<p><?= e__('monitors.messages_content.hint') ?></p>
		</div>


		<form id="monitor-messages-form" method="post" action="<?= e($base_url) ?>/monitors/messages/update" data-monitor-messages-form data-save-error="<?= e__('monitors.messages.save_error') ?>">
			<?= csrf_field() ?>
			<input type="hidden" name="monitor_id" value="<?= (int)$monitor['id'] ?>">
			<input type="hidden" name="message_section" value="<?= e($activeMessageSection) ?>" data-active-subtab-input>

			<div class="editor-subtabs" data-editor-subtabs data-active-subtab="<?= e($activeMessageSection) ?>" data-query-key="section">
				<div class="monitor-tabs editor-subtab-list" role="tablist" aria-label="<?= e__('monitors.messages.sections.label') ?>">
					<?php foreach ($messageSections as $sectionName => $translationKey): ?>
						<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$monitor['id'] ?>&amp;tab=messages&amp;section=<?= e($sectionName) ?>" class="monitor-tab-link editor-subtab-link<?= $activeMessageSection === $sectionName ? ' is-active' : '' ?>" role="tab" data-subtab-target="<?= e($sectionName) ?>" aria-selected="<?= $activeMessageSection === $sectionName ? 'true' : 'false' ?>"><?= e__($translationKey) ?></a>
					<?php endforeach; ?>
				</div>


				<section class="editor-subtab-panel" data-subtab-panel="owner"<?= $activeMessageSection === 'owner' ? '' : ' hidden' ?>>
					<h3><?= e__('monitors.messages.owner.heading') ?></h3>
					<p class="form-hint"><?= e__('monitors.messages.owner.hint') ?></p>
					<p class="form-hint"><?= e__('mail.templates.owner_language_hint') ?></p>
					<?php
					$ownerDueTemplate = $mailTemplates['owner_due_notice']['owner'] ?? ['subject' => '', 'body_text' => ''];
					$ownerReminderTemplate = $mailTemplates['owner_reminder']['owner'] ?? ['subject' => '', 'body_text' => ''];
					$ownerDueDefault = $mailDefaults['owner_due_notice']['owner'] ?? ['subject' => '', 'body_text' => ''];
					$ownerReminderDefault = $mailDefaults['owner_reminder']['owner'] ?? ['subject' => '', 'body_text' => ''];
					?>
					<details class="mail-template-kind" open>
						<summary><?= e__('monitors.messages.owner.due.heading') ?></summary>
						<div class="mail-template-kind-body">
							<p class="form-hint"><?= e__('monitors.messages.owner.due.hint') ?></p>
							<label for="owner_due_notice_subject"><?= e__('monitors.messages.subject') ?></label>
							<input type="text" id="owner_due_notice_subject" name="owner_due_notice_subject" value="<?= e((string)$ownerDueTemplate['subject']) ?>">
							<label for="owner_due_notice_body"><?= e__('monitors.messages.body') ?></label>
							<?= markdown_editor($base_url, 'owner_due_notice_body', 'owner_due_notice_body', (string)$ownerDueTemplate['body_text'], 9, 'email') ?>
							<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{due}</code> — <?= e__('mail.placeholders.due') ?>; <code>{deadline}</code> — <?= e__('mail.placeholders.deadline') ?>; <code>{response_window}</code> — <?= e__('mail.placeholders.response_window') ?>; <code>{max_followup_reminders}</code> — <?= e__('mail.placeholders.max_followup_reminders') ?>; <code>{url}</code> — <?= e__('mail.placeholders.owner_url') ?>; <code>{quickcheckin}</code> — <?= e__('mail.placeholders.quickcheckin') ?>; <code>{quickurl}</code> — <?= e__('mail.placeholders.quickurl') ?>.</p>
							<p class="form-hint"><?= e__('mail.templates.owner_quickurl_fallback') ?></p>
							<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
							<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$ownerDueDefault['subject']) ?></div><div class="markdown-preview-email markdown-content"><?= markdown_email_html((string)$ownerDueDefault['body_text']) ?></div></div></details>
						</div>
					</details>
					<details class="mail-template-kind">
						<summary><?= e__('monitors.messages.owner.reminder.heading') ?></summary>
						<div class="mail-template-kind-body">
							<p class="form-hint"><?= e__('monitors.messages.owner.reminder.hint') ?></p>
							<label for="owner_reminder_subject"><?= e__('monitors.messages.subject') ?></label>
							<input type="text" id="owner_reminder_subject" name="owner_reminder_subject" value="<?= e((string)$ownerReminderTemplate['subject']) ?>">
							<label for="owner_reminder_body"><?= e__('monitors.messages.body') ?></label>
							<?= markdown_editor($base_url, 'owner_reminder_body', 'owner_reminder_body', (string)$ownerReminderTemplate['body_text'], 9, 'email') ?>
							<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{due}</code> — <?= e__('mail.placeholders.due') ?>; <code>{number}</code> — <?= e__('mail.placeholders.number') ?>; <code>{total}</code> — <?= e__('mail.placeholders.total') ?>; <code>{url}</code> — <?= e__('mail.placeholders.owner_url') ?>; <code>{quickcheckin}</code> — <?= e__('mail.placeholders.quickcheckin') ?>; <code>{quickurl}</code> — <?= e__('mail.placeholders.quickurl') ?>.</p>
							<p class="form-hint"><?= e__('mail.templates.owner_quickurl_fallback') ?></p>
							<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
							<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$ownerReminderDefault['subject']) ?></div><div class="markdown-preview-email markdown-content"><?= markdown_email_html((string)$ownerReminderDefault['body_text']) ?></div></div></details>
						</div>
					</details>
				</section>

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
								<?= markdown_editor($base_url, 'recipient_default_body_' . $templateFieldLocale, 'recipient_default_body_' . $templateFieldLocale, (string)$recipientTemplate['body_text'], 9, 'email', ['data-recipient-template-body' => true]) ?>
								<div class="template-validation-warning" role="alert" data-recipient-url-warning<?= $recipientTemplateUrlMissing ? '' : ' hidden' ?>>
									<strong><?= e__('mail.validation.portal_url_missing.heading') ?></strong>
									<?= e__('monitors.messages.portal_url_missing_warning', ['language' => notification_language_name($templateLocale)]) ?>
									<?php if ($recipientTemplateUsers !== []): ?><small><?= e__('monitors.messages.portal_url_missing_recipients', ['recipients' => implode(', ', $recipientTemplateUsers)]) ?></small><?php endif; ?>
								</div>
								<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{url}</code> — <?= e__('mail.placeholders.recipient_url') ?>.</p>
								<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
								<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$recipientDefault['subject']) ?></div><div class="markdown-preview-email markdown-content"><?= markdown_html((string)$recipientDefault['body_text']) ?></div></div></details>
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
										<label for="safety_invitation_body_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.body') ?></label><?= markdown_editor($base_url, 'safety_invitation_body_' . $templateFieldLocale, 'safety_invitation_body_' . $templateFieldLocale, (string)$invitationTemplate['body_text'], 7, 'email') ?>
									<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{url}</code> — <?= e__('mail.placeholders.safety_url') ?>.</p>
										<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
										<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$invitationDefault['subject']) ?></div><div class="markdown-preview-email markdown-content"><?= markdown_html((string)$invitationDefault['body_text']) ?></div></div></details>
									</div>
								</details>
								<details class="mail-template-kind">
									<summary><?= e__('monitors.escalation.messages.reminder.heading') ?></summary>
									<div class="mail-template-kind-body">
										<label for="safety_reminder_subject_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.subject') ?></label><input type="text" id="safety_reminder_subject_<?= e($templateFieldLocale) ?>" name="safety_reminder_subject_<?= e($templateFieldLocale) ?>" value="<?= e((string)$reminderTemplate['subject']) ?>">
										<label for="safety_reminder_body_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.body') ?></label><?= markdown_editor($base_url, 'safety_reminder_body_' . $templateFieldLocale, 'safety_reminder_body_' . $templateFieldLocale, (string)$reminderTemplate['body_text'], 7, 'email') ?>
									<p class="form-hint placeholder-help"><?= e__('monitors.messages.placeholders') ?> <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>; <code>{url}</code> — <?= e__('mail.placeholders.safety_url') ?>. <?= e__('monitors.escalation.messages.reminder_placeholders') ?> <code>{number}</code> — <?= e__('mail.placeholders.reminder_number') ?>; <code>{total}</code> — <?= e__('mail.placeholders.reminder_total') ?>.</p>
										<p class="form-hint"><?= e__('mail.templates.empty_uses_default') ?></p>
										<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><div><strong><?= e__('monitors.messages.subject') ?>:</strong> <?= e((string)$reminderDefault['subject']) ?></div><div class="markdown-preview-email markdown-content"><?= markdown_html((string)$reminderDefault['body_text']) ?></div></div></details>
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
							$portalContent = $portalTemplates[$templateLocale] ?? ['intro_text' => ''];
							$portalBuiltIn = $portalDefaults[$templateLocale] ?? ['intro_text' => ''];
							?>
							<div class="language-template-panel" data-language-panel="<?= e($templateLocale) ?>">
								<label for="portal_intro_<?= e($templateFieldLocale) ?>"><?= e__('monitors.messages.portal_content.intro') ?></label>
								<textarea id="portal_intro_<?= e($templateFieldLocale) ?>" name="portal_intro_<?= e($templateFieldLocale) ?>" rows="5"><?= e((string)$portalContent['intro_text']) ?></textarea>
								<p class="form-hint"><?= e__('monitors.messages.portal_content.intro_hint') ?></p>
								<p class="form-hint placeholder-help"><?= e__('monitors.messages.portal_content.placeholders') ?> <code>{name}</code> — <?= e__('mail.placeholders.name') ?>; <code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>; <code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>.</p>
								<details class="mail-default-disclosure"><summary><?= e__('mail.templates.show_default') ?></summary><div class="mail-default-template"><pre><?= e((string)$portalBuiltIn['intro_text']) ?></pre></div></details>
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
			<noscript><button type="submit" class="btn-primary"><?= e__('monitors.messages.submit') ?></button></noscript>
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
			<?php if ((string)$monitor['escalation_policy'] === 'safety_contact' && $safetyContactIds === []): ?><div class="review-warning"><?= e__('monitors.review.warning.no_safety_contacts') ?></div><?php elseif ($safetyConfigurationWarning): ?><div class="review-warning"><?= e__('monitors.review.warning.safety_confirmations', ['required' => $safetyRequiredConfirmations, 'available' => $eligibleSafetyContactCount]) ?></div><?php endif; ?>
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
	<div class="editor-save-bar" data-settings-save-bar data-settings-tabs="details,schedule,escalation,messages,review"<?= in_array($activeTab, ['details', 'schedule', 'escalation', 'messages', 'review'], true) ? '' : ' hidden' ?>>
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
