<?php

/**
 * @file NotificationInfrastructureSourceTest.php
 * @brief Regression checks for notification release boundaries and operator commands.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationInfrastructureSourceTest extends TestCase
{
	public function testQueueSchemaContainsRetriesLeasesAndIdempotency(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_initial_schema.sql');
		self::assertStringContainsString('idempotency_key', $migration);
		self::assertStringContainsString('attempt_count', $migration);
		self::assertStringContainsString('locked_until', $migration);
		self::assertStringContainsString("'processing'", $migration);
	}

	public function testCronCommandExposesSchedulerWorkerTestAndManualRetry(): void
	{
		$source = (string)file_get_contents(dirname(__DIR__, 2) . '/tools/pulse.php');
		self::assertStringContainsString("'notifications:run'", $source);
		self::assertStringContainsString("'notifications:schedule'", $source);
		self::assertStringContainsString("'mail:work'", $source);
		self::assertStringContainsString("'mail:test'", $source);
		self::assertStringContainsString("'mail:retry-failed'", $source);
		self::assertStringContainsString('RecordSuccessfulCronRun()', $source);
	}

	public function testPublicCronRunsOnlyTheCombinedNotificationOperation(): void
	{
		$source = (string)file_get_contents(dirname(__DIR__, 2) . '/public/cron/cron.php');

		self::assertStringContainsString("Environment::Get('PULSE_CRON_TOKEN')", $source);
		self::assertStringContainsString("['notificationScheduler']->Run()", $source);
		self::assertStringContainsString("['mailQueueWorker']->Process(", $source);
		self::assertStringContainsString("['systemStatusRepository']->RecordSuccessfulCronRun()", $source);
		self::assertStringNotContainsString('$argv', $source);
	}

	public function testNotificationLanguagesBelongToRecipients(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_initial_schema.sql');
		$scheduler = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/NotificationScheduler.php');
		$contactForm = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/contacts/edit.php');

		self::assertStringContainsString('CREATE TABLE users', $migration);
		self::assertStringContainsString('CREATE TABLE contacts', $migration);
		self::assertStringContainsString('notification_locale', $scheduler);
		self::assertStringContainsString('name="notification_locale"', $contactForm);
	}

	public function testSchedulerStagesOwnerSafetyAndRecipientNotifications(): void
	{
		$source = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/NotificationScheduler.php');
		$escalation = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/EscalationService.php');
		self::assertStringContainsString("'mail_type' => 'owner_due_notice'", $source);
		self::assertStringContainsString("'mail_type' => 'owner_reminder'", $source);
		self::assertStringContainsString('StartSafetyGate', $source);
		self::assertStringContainsString('StageRecipientRelease', $source);
		self::assertStringContainsString("'mail_type' => 'safety_invitation'", $escalation);
		self::assertStringContainsString("'mail_type' => 'recipient_notification'", $escalation);
	}

	public function testRecipientEscalationSchemaUsesHashedMultiTokenSafetyLinksAndImmutableReleases(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_initial_schema.sql');

		self::assertStringContainsString('safety_request_tokens', $migration);
		self::assertStringContainsString('token_hash', $migration);
		self::assertStringContainsString('recipient_releases', $migration);
		self::assertStringContainsString('recipient_release_deliveries', $migration);
		self::assertStringContainsString("'safety_pending'", $migration);
	}

	public function testLocalizedMonitorMessagesAreStoredSelectedAndSnapshotted(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_initial_schema.sql');
		$escalation = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/EscalationService.php');
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');

		self::assertStringContainsString('monitor_mail_templates', $migration);
		self::assertStringContainsString("'safety_invitation'", $migration);
		self::assertStringContainsString("invitation.locale = c.notification_locale", $escalation);
		self::assertStringContainsString("'invitation_subject' => \$contact['safety_invitation_subject'] ?? null", $escalation);
		self::assertStringContainsString("'message_body' => (string)(\$current['reminder_body'] ?? '')", $escalation);
		self::assertStringContainsString('name="safety_invitation_subject_<?= e($templateFieldLocale) ?>"', $view);
		self::assertStringContainsString('data-language-tabs', $view);
	}

	public function testSafetyLinksAreRedactedAfterDeliveryOrCancellation(): void
	{
		$queue = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/MailQueueRepository.php');
		$execution = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/MonitorExecutionService.php');

		self::assertStringContainsString("mail_type IN (\\'safety_invitation\\', \\'safety_reminder\\')", $queue);
		self::assertStringContainsString('Safety link redacted after delivery', $queue);
		self::assertStringContainsString('Safety link redacted after cancellation', $queue);
		self::assertStringContainsString('Safety link redacted after cancellation', $execution);
	}

	public function testSafetyGetIsReadOnlyAndResponseRequiresPost(): void
	{
		$routes = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/SafetyController.php');

		self::assertStringContainsString("Get('/safety/confirm'", $routes);
		self::assertStringContainsString("Post('/safety/respond'", $routes);
		self::assertStringContainsString('FindSafetyRequestByToken', $controller);
		self::assertStringContainsString('RespondToSafetyToken', $controller);
	}

	public function testDueNoticePrecedesTheResponseWindowReminders(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_initial_schema.sql');
		$scheduler = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/NotificationScheduler.php');

		self::assertStringContainsString('due_notice_sent_at', $migration);
		self::assertStringContainsString("ParseUtc((string)\$cycle['due_at'])", $scheduler);
		self::assertStringContainsString("ParseUtc((string)\$cycle['response_deadline_at'])", $scheduler);
		self::assertStringContainsString("'owner-due-notice:'", $scheduler);
	}

	public function testDebugModeOwnsLifecycleTestActionsWithoutASecondEnvironmentFlag(): void
	{
		$config = (string)file_get_contents(dirname(__DIR__, 2) . '/config/app.php');
		$routes = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/MonitorController.php');
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/index.php');
		$actions = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/partials/actions.php');

		self::assertStringNotContainsString('PULSE_ALLOW_FORCE_DUE', $config);
		self::assertStringContainsString('(bool)$config[\'debug\']', $routes);
		self::assertStringContainsString("Post('/monitors/send-due-notice'", $routes);
		self::assertStringContainsString('QueueDueNoticeForMonitorForUser', $controller);
		self::assertStringContainsString('$debugEnabled', $view);
		self::assertStringContainsString('/monitors/send-due-notice', $actions);
		self::assertStringContainsString('/monitors/send-safety-contact-notifications', $actions);
		self::assertStringContainsString('/monitors/send-recipient-notifications', $actions);
		self::assertStringContainsString('FindDebugSafetyGateCycleForUser', $controller);
		self::assertStringContainsString('FindPendingQueueIdsForSafetyInvitations', $controller);
	}

	public function testPermanentReminderFailureIsVisibleWithoutChangingLifecycleState(): void
	{
		$repository = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/MonitorRepository.php');
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');

		self::assertStringContainsString('failed_notification_count', $repository);
		self::assertStringContainsString('owner_due_notice', $repository);
		self::assertStringContainsString('failed_mq.status', $repository);
		self::assertStringContainsString('table-warning-critical', $dashboard);
		self::assertStringContainsString('monitors.notifications.delivery_failed_short', $dashboard);
	}

	public function testDashboardActivityIsBoundedAndLinksToCompleteHistory(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/HomeController.php');
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');
		$history = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/activity.php');

		self::assertStringContainsString('FindRecentActivityForUser((int)$user[\'id\'], 5)', $controller);
		self::assertStringContainsString('/activity', $dashboard);
		self::assertStringContainsString('activity.pagination.page', $history);
	}

	public function testRecipientTemplatesAndFileMetadataAreExposedInTheEditor(): void
	{
		$composer = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/NotificationComposer.php');
		$monitorView = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$recipientView = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/recipients/edit.php');
		$routes = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/001_initial_schema.sql');

		self::assertStringContainsString("'mail.recipient_notification.subject'", $composer);
		self::assertStringContainsString("'owner' =>", $composer);
		self::assertStringContainsString('<code>{monitor}</code>', $monitorView);
		self::assertStringContainsString("mail.placeholders.monitor", $monitorView);
		self::assertStringContainsString("mail.placeholders.safety_url", $monitorView);
		self::assertStringContainsString('mail-default-template', $monitorView);
		self::assertStringContainsString('recipients.message.placeholders', $recipientView);
		self::assertStringContainsString("mail.placeholders.name", $recipientView);
		self::assertStringContainsString('/monitors/documents/file/update', $routes);
		self::assertStringContainsString('description TEXT NULL', $migration);
	}

	public function testDisabledMailIsReportedAndCannotSubmitATest(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/HomeController.php');
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');
		$administration = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/administration/index.php');
		$styles = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');

		self::assertStringContainsString("'mailEnabled' =>", $controller);
		self::assertStringContainsString('dashboard-system-warning', $dashboard);
		self::assertStringContainsString('dashboard.notifications.disabled.message', $dashboard);
		self::assertStringContainsString('<?php if ($mailEnabled): ?>', $administration);
		self::assertStringContainsString('type="button" disabled', $administration);
		self::assertStringContainsString('class="btn-secondary"', $administration);
		self::assertStringContainsString('button:disabled', $styles);
	}
	public function testRecipientPortalUsesVisualCardsSafeViewsAndStreamingZip64(): void
	{
		$routes = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/RecipientPortalController.php');
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/portal/access.php');
		$archive = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/RecipientPortalArchiveBuilder.php');
		$previewService = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/DocumentPreviewService.php');
		$streamer = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Core/PrivateFileStreamer.php');

		self::assertStringContainsString("Get('/portal/document/view'", $routes);
		self::assertStringContainsString('DocumentPreviewService', $controller);
		self::assertStringContainsString('PrivateFileStreamer', $controller);
		self::assertStringContainsString('KIND_AUDIO', $previewService);
		self::assertStringContainsString('KIND_VIDEO', $previewService);
		self::assertStringContainsString('Content-Range: bytes ', $streamer);
		self::assertStringNotContainsString("'image/svg+xml'", $controller);
		self::assertStringContainsString('portal-document-grid', $view);
		self::assertStringContainsString('portal-document-preview-image', $view);
		self::assertStringContainsString('loading="lazy"', $view);
		self::assertStringContainsString('PackUInt64', $archive);
		self::assertStringContainsString('0x06064b50', $archive);
		self::assertStringNotContainsString('tempnam', $archive);
	}

}
