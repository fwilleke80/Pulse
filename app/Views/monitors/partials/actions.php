<?php

/**
 * @file actions.php
 * @brief Shared monitor action buttons for monitor overview tables.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $monitor */
/** @var string $base_url */
/** @var bool $debugEnabled */
/** @var bool $mailEnabled */
/** @var string $actionStatus */
/** @var string $actionRedirect */
/** @var bool $actionAllowDelete */

$cycleEscalationPolicy = (string)($monitor['latest_escalation_policy'] ?? $monitor['escalation_policy'] ?? 'direct');
$dueNoticeQueueStatus = (string)($monitor['due_notice_queue_status'] ?? '');
$dueNoticeSatisfied = !empty($monitor['due_notice_sent_at']) || $dueNoticeQueueStatus === 'sent';
?>

<div class="table-actions">
	<form method="post" action="<?= e($base_url) ?>/monitors/<?= $actionStatus === 'paused' ? 'resume' : 'pause' ?>">
		<?= csrf_field() ?>
		<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
		<input type="hidden" name="redirect" value="<?= e($actionRedirect) ?>">
		<button type="submit" class="btn-table-inline"><?= e__($actionStatus === 'paused' ? 'monitors.resume.submit' : 'monitors.pause.submit') ?></button>
	</form>

	<?php if ($debugEnabled && $actionStatus === 'checked-in'): ?>
		<form method="post" action="<?= e($base_url) ?>/monitors/force-due">
			<?= csrf_field() ?>
			<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
			<input type="hidden" name="redirect" value="<?= e($actionRedirect) ?>">
			<button type="submit" class="btn-table-inline"><?= e__('monitors.force_due.submit') ?></button>
		</form>
	<?php elseif ($debugEnabled && $actionStatus === 'awaiting' && !$dueNoticeSatisfied): ?>
		<?php if (in_array($dueNoticeQueueStatus, ['queued', 'retrying', 'processing'], true)): ?>
			<button type="button" class="btn-table-inline" disabled title="<?= e__('monitors.send_due_notice.pending_hint') ?>"><?= e__('monitors.send_due_notice.pending') ?></button>
		<?php elseif ($dueNoticeQueueStatus === 'failed'): ?>
			<button type="button" class="btn-table-inline" disabled title="<?= e__('monitors.send_due_notice.failed_hint') ?>"><?= e__('monitors.send_due_notice.failed_short') ?></button>
		<?php elseif ($mailEnabled): ?>
			<form method="post" action="<?= e($base_url) ?>/monitors/send-due-notice">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
				<input type="hidden" name="redirect" value="<?= e($actionRedirect) ?>">
				<button type="submit" class="btn-table-inline"><?= e__('monitors.send_due_notice.submit') ?></button>
			</form>
		<?php else: ?>
			<button type="button" class="btn-table-inline" disabled title="<?= e__('monitors.send_due_notice.mail_disabled') ?>"><?= e__('monitors.send_due_notice.submit') ?></button>
		<?php endif; ?>
	<?php elseif ($debugEnabled && $actionStatus === 'awaiting' && $cycleEscalationPolicy === 'safety_contact'): ?>
		<?php if ($mailEnabled): ?>
			<form method="post" action="<?= e($base_url) ?>/monitors/send-safety-contact-notifications" data-confirm="<?= e__('monitors.send_safety_contacts.confirm') ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
				<input type="hidden" name="redirect" value="<?= e($actionRedirect) ?>">
				<button type="submit" class="btn-table-inline"><?= e__('monitors.send_safety_contacts.submit') ?></button>
			</form>
		<?php else: ?>
			<button type="button" class="btn-table-inline" disabled title="<?= e__('monitors.send_safety_contacts.mail_disabled') ?>"><?= e__('monitors.send_safety_contacts.submit') ?></button>
		<?php endif; ?>
	<?php elseif ($debugEnabled && in_array($actionStatus, ['awaiting', 'safety-pending', 'overdue'], true)): ?>
		<?php if ($mailEnabled): ?>
			<form method="post" action="<?= e($base_url) ?>/monitors/send-recipient-notifications" data-confirm="<?= e__('monitors.send_recipients.confirm') ?>">
				<?= csrf_field() ?>
				<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
				<input type="hidden" name="redirect" value="<?= e($actionRedirect) ?>">
				<button type="submit" class="btn-table-inline btn-danger"><?= e__('monitors.send_recipients.submit') ?></button>
			</form>
		<?php else: ?>
			<button type="button" class="btn-table-inline" disabled title="<?= e__('monitors.send_recipients.mail_disabled') ?>"><?= e__('monitors.send_recipients.submit') ?></button>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ($actionAllowDelete): ?>
		<form method="post" action="<?= e($base_url) ?>/monitors/delete" data-confirm="<?= e__('monitors.index.delete_confirm') ?>">
			<?= csrf_field() ?>
			<input type="hidden" name="id" value="<?= (int)$monitor['id'] ?>">
			<button type="submit" class="btn-table-inline btn-danger"><?= e__('monitors.index.table.buttons.delete') ?></button>
		</form>
	<?php endif; ?>
</div>
