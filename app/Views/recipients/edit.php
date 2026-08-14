<?php

/**
 * @file edit.php
 * @brief Dedicated monitor-recipient configuration page.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $recipient */
/** @var array<int, array<string, mixed>> $documents */
/** @var array<int> $assignedDocumentIds */
/** @var array<int, array<string, mixed>> $deliveryHistory */
/** @var array{subject: string, body_text: string} $preview */
/** @var array{subject: string, body_text: string} $defaultPreview */
/** @var array<int, string> $messageIssues */
/** @var array<int, string> $defaultMessageIssues */
/** @var array{message_text: string, intro_text: string} $defaultPortalPreview */
/** @var string $base_url */

$hasOverride = !empty($recipient['override_message_id']);
$hasPortalOverride = !empty($recipient['portal_override_id']);

ob_start();
?>

<div class="editor-heading">
	<div>
		<h1><?= e__('recipients.edit.heading', ['name' => (string)$recipient['name']]) ?></h1>
		<p class="form-hint"><?= e__('recipients.edit.intro', ['monitor' => (string)$recipient['monitor_name']]) ?></p>
	</div>
	<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$recipient['monitor_id'] ?>&amp;tab=recipients" class="button-link"><?= e__('recipients.edit.back') ?></a>
</div>

<?php if (!empty($recipient['release_in_progress'])): ?>
	<div class="dashboard-system-warning" role="alert">
		<div>
			<strong><?= e__('recipients.snapshot.heading') ?></strong>
			<p><?= e__('recipients.snapshot.message') ?></p>
		</div>
	</div>
<?php endif; ?>

<section class="configuration-block recipient-contact-summary">
	<h2><?= e__('recipients.contact.heading') ?></h2>
	<dl class="recipient-summary-list">
		<div><dt><?= e__('recipients.contact.name') ?></dt><dd><?= e((string)$recipient['name']) ?></dd></div>
		<div><dt><?= e__('recipients.contact.email') ?></dt><dd><?= e((string)$recipient['email']) ?></dd></div>
		<div><dt><?= e__('recipients.contact.language') ?></dt><dd><?= e(notification_language_name(isset($recipient['notification_locale']) ? (string)$recipient['notification_locale'] : null)) ?></dd></div>
		<div><dt><?= e__('recipients.contact.address_status') ?></dt><dd><?= e__(!empty($recipient['email_checked_at']) ? 'contacts.status.checked' : 'contacts.status.not_checked') ?></dd></div>
	</dl>
	<a href="<?= e($base_url) ?>/contacts/edit?id=<?= (int)$recipient['contact_id'] ?>&amp;return_monitor_id=<?= (int)$recipient['monitor_id'] ?>"><?= e__('recipients.contact.edit_global') ?></a>
</section>

<form method="post" action="<?= e($base_url) ?>/monitors/recipients/update">
	<?= csrf_field() ?>
	<input type="hidden" name="id" value="<?= (int)$recipient['id'] ?>">

	<section class="configuration-block" data-message-override data-recipient-template-validation data-empty-valid="false">
		<h2><?= e__('recipients.message.heading') ?></h2>
		<label class="compact-check">
			<input type="checkbox" name="use_message_override" value="1" data-message-override-toggle <?= $hasOverride ? 'checked' : '' ?>>
			<?= e__('recipients.message.use_override') ?>
		</label>
		<p class="form-hint"><?= e__('recipients.message.default_hint') ?></p>
		<div data-message-fields>
			<label for="message_subject"><?= e__('monitors.messages.subject') ?></label>
			<input type="text" id="message_subject" name="message_subject" value="<?= e((string)($recipient['override_subject'] ?? '')) ?>">
			<label for="message_body"><?= e__('monitors.messages.body') ?></label>
			<textarea id="message_body" name="message_body" rows="10" data-recipient-template-body><?= e((string)($recipient['override_body'] ?? '')) ?></textarea>
			<div class="template-validation-warning" role="alert" data-recipient-url-warning<?= ($hasOverride && in_array('recipient_portal_url_missing', $messageIssues, true)) ? '' : ' hidden' ?>>
				<strong><?= e__('mail.validation.portal_url_missing.heading') ?></strong>
				<?= e__('recipients.message.portal_url_missing_warning') ?>
			</div>
			<p class="form-hint">
				<?= e__('recipients.message.placeholders') ?>
				<code>{app}</code> — <?= e__('mail.placeholders.app') ?>;
				<code>{name}</code> — <?= e__('mail.placeholders.name') ?>;
				<code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>;
				<code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>;
				<code>{url}</code> — <?= e__('mail.placeholders.recipient_url') ?>.
			</p>
			<p class="form-hint"><?= e__('recipients.message.custom_hint') ?></p>
		</div>
		<div class="mail-default-template">
			<strong><?= e__('recipients.message.default_preview.heading') ?></strong>
			<div><strong><?= e__('recipients.preview.subject') ?>:</strong> <?= e($defaultPreview['subject']) ?></div>
			<pre><?= e($defaultPreview['body_text']) ?></pre>
		</div>
		<?php if (in_array('recipient_portal_url_missing', $defaultMessageIssues, true)): ?>
			<div class="template-validation-warning" role="alert">
				<strong><?= e__('mail.validation.portal_url_missing.heading') ?></strong>
				<?= e__('recipients.message.default_portal_url_missing_warning', ['language' => notification_language_name((string)$recipient['notification_locale'])]) ?>
				<a href="<?= e($base_url) ?>/monitors/edit?id=<?= (int)$recipient['monitor_id'] ?>&amp;tab=messages"><?= e__('recipients.message.edit_default') ?></a>
			</div>
		<?php endif; ?>
	</section>

	<section class="configuration-block" data-message-override>
		<h2><?= e__('recipients.portal_message.heading') ?></h2>
		<p class="form-hint"><?= e__('recipients.portal_message.hint') ?></p>
		<label class="compact-check">
			<input type="checkbox" name="use_portal_message_override" value="1" data-message-override-toggle <?= $hasPortalOverride ? 'checked' : '' ?>>
			<?= e__('recipients.portal_message.use_override') ?>
		</label>
		<div data-message-fields>
			<label for="portal_message_body"><?= e__('recipients.portal_message.body') ?></label>
			<textarea id="portal_message_body" name="portal_message_body" rows="7"><?= e((string)($recipient['portal_override_body'] ?? '')) ?></textarea>
			<p class="form-hint placeholder-help">
				<?= e__('recipients.portal_message.placeholders') ?>
				<code>{app}</code> — <?= e__('mail.placeholders.app') ?>;
				<code>{name}</code> — <?= e__('mail.placeholders.name') ?>;
				<code>{owner}</code> — <?= e__('mail.placeholders.owner') ?>;
				<code>{monitor}</code> — <?= e__('mail.placeholders.monitor') ?>.
			</p>
		</div>
		<div class="mail-default-template">
			<strong><?= e__('recipients.portal_message.default_preview.heading') ?></strong>
			<pre><?= e((string)$defaultPortalPreview['message_text']) ?></pre>
		</div>
	</section>

	<section class="configuration-block">
		<h2><?= e__('recipients.documents.heading') ?></h2>
		<p class="form-hint"><?= e__('recipients.documents.hint') ?></p>
		<?php if ($documents === []): ?>
			<p><?= e__('recipients.documents.none') ?></p>
		<?php else: ?>
			<div class="recipient-document-checklist">
				<?php foreach ($documents as $document): ?>
					<label class="recipient-document-row">
						<input type="checkbox" name="document_ids[]" value="<?= (int)$document['id'] ?>" <?= in_array((int)$document['id'], $assignedDocumentIds, true) ? 'checked' : '' ?>>
						<span>
							<strong><?= e((string)$document['title']) ?></strong><br>
							<small><?= e__('monitors.documents.type.' . (string)$document['storage_type']) ?></small>
							<?php if (trim((string)($document['description'] ?? '')) !== ''): ?>
								<br><small><?= e((string)$document['description']) ?></small>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="privacy-note">
			<strong><?= e__('recipients.documents.gated_heading') ?></strong>
			<?= e__('recipients.documents.gated_message') ?>
		</div>
	</section>

	<button type="submit" class="btn-primary"><?= e__('recipients.edit.submit') ?></button>
</form>

<section class="configuration-block">
	<h2><?= e__('recipients.preview.heading') ?></h2>
	<p class="form-hint"><?= e__('recipients.preview.exact_hint') ?></p>
	<div class="mail-preview">
		<strong><?= e__('recipients.preview.subject') ?>:</strong> <?= e($preview['subject']) ?>
		<pre><?= e($preview['body_text']) ?></pre>
	</div>
</section>

<section class="configuration-block">
	<h2><?= e__('recipients.history.heading') ?></h2>
	<?php if ($deliveryHistory === []): ?>
		<p><?= e__('recipients.history.none') ?></p>
	<?php else: ?>
		<div class="table-scroll">
			<table>
				<thead><tr><th><?= e__('recipients.history.created') ?></th><th><?= e__('recipients.history.address') ?></th><th><?= e__('recipients.history.status') ?></th><th><?= e__('recipients.history.sent') ?></th><th><?= e__('recipients.history.portal') ?></th></tr></thead>
				<tbody>
				<?php foreach ($deliveryHistory as $delivery): ?>
					<tr>
						<td><?= e(format_datetime((string)$delivery['created_at'])) ?></td>
						<td><?= e((string)$delivery['recipient_email']) ?></td>
						<td><span class="mini-status mini-status-<?= e((string)$delivery['status']) ?>"><?= e__('recipients.delivery.status.' . (string)$delivery['status']) ?></span></td>
						<td><?= e(format_datetime(isset($delivery['sent_at']) ? (string)$delivery['sent_at'] : null)) ?></td>
						<td>
							<span><?= e__('recipients.portal.status.' . (string)$delivery['portal_status']) ?></span>
							<?php if ((string)$delivery['portal_status'] === 'available'): ?>
								<form method="post" action="<?= e($base_url) ?>/monitors/recipients/portal/revoke" data-confirm="<?= e__('recipients.portal.revoke.confirm') ?>" class="inline-form">
									<?= csrf_field() ?>
									<input type="hidden" name="recipient_id" value="<?= (int)$recipient['id'] ?>">
									<input type="hidden" name="delivery_id" value="<?= (int)$delivery['id'] ?>">
									<button type="submit" class="link-button danger-link"><?= e__('recipients.portal.revoke.submit') ?></button>
								</form>
							<?php elseif ((string)$delivery['portal_status'] === 'expired' && !empty($delivery['portal_expires_at'])): ?>
								<small><?= e(format_datetime((string)$delivery['portal_expires_at'])) ?></small>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>

<form method="post" action="<?= e($base_url) ?>/monitors/recipients/remove" data-confirm="<?= e__('recipients.remove.confirm', ['name' => (string)$recipient['name']]) ?>">
	<?= csrf_field() ?>
	<input type="hidden" name="id" value="<?= (int)$recipient['id'] ?>">
	<button type="submit" class="btn-danger"><?= e__('recipients.remove.submit') ?></button>
</form>

<?php
$content = ob_get_clean();
$title = e__('recipients.edit.title');
require __DIR__ . '/../layouts/main.php';
