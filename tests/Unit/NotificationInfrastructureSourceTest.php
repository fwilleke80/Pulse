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
	public function testQueueMigrationContainsRetriesLeasesAndIdempotency(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/006_notification_infrastructure.sql');
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
	}

	public function testPublicCronRunsOnlyTheCombinedNotificationOperation(): void
	{
		$source = (string)file_get_contents(dirname(__DIR__, 2) . '/public/cron/cron.php');

		self::assertStringContainsString("Environment::Get('PULSE_CRON_TOKEN')", $source);
		self::assertStringContainsString("['notificationScheduler']->Run()", $source);
		self::assertStringContainsString("['mailQueueWorker']->Process(", $source);
		self::assertStringNotContainsString('$argv', $source);
	}

	public function testNotificationLanguagesBelongToRecipients(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/007_recipient_notification_languages.sql');
		$scheduler = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/NotificationScheduler.php');
		$contactForm = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/contacts/edit.php');

		self::assertStringContainsString('ALTER TABLE users', $migration);
		self::assertStringContainsString('ALTER TABLE contacts', $migration);
		self::assertStringContainsString('notification_locale', $scheduler);
		self::assertStringContainsString('name="notification_locale"', $contactForm);
	}

	public function testSchedulerCreatesOnlyOwnerCheckInNotifications(): void
	{
		$source = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/NotificationScheduler.php');
		self::assertStringContainsString("'mail_type' => 'owner_due_notice'", $source);
		self::assertStringContainsString("'mail_type' => 'owner_reminder'", $source);
		self::assertStringNotContainsString("'mail_type' => 'recipient", $source);
	}

	public function testDueNoticePrecedesTheResponseWindowReminders(): void
	{
		$migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/migrations/008_immediate_due_notifications.sql');
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

		self::assertStringNotContainsString('PULSE_ALLOW_FORCE_DUE', $config);
		self::assertStringContainsString('(bool)$config[\'debug\']', $routes);
		self::assertStringContainsString("Post('/monitors/send-due-notice'", $routes);
		self::assertStringContainsString('QueueDueNoticeForMonitorForUser', $controller);
		self::assertStringContainsString('$debugEnabled', $view);
		self::assertStringContainsString('/monitors/send-due-notice', $view);
	}

	public function testPermanentReminderFailureIsVisibleWithoutChangingLifecycleState(): void
	{
		$repository = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/MonitorRepository.php');
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');

		self::assertStringContainsString('failed_notification_count', $repository);
		self::assertStringContainsString('owner_due_notice', $repository);
		self::assertStringContainsString('failed_mq.status', $repository);
		self::assertStringContainsString('dashboard-delivery-warning', $dashboard);
		self::assertStringContainsString('monitors.notifications.delivery_failed_message', $dashboard);
	}

	public function testDashboardActivityIsBoundedAndLinksToCompleteHistory(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/HomeController.php');
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');
		$history = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/activity.php');

		self::assertStringContainsString('FindRecentActivityForUser((int)$user[\'id\'], 10)', $controller);
		self::assertStringContainsString('/activity', $dashboard);
		self::assertStringContainsString('activity.pagination.page', $history);
	}

	public function testDisabledMailIsReportedAndCannotSubmitATest(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/HomeController.php');
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');
		$profile = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/profile/index.php');
		$styles = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');

		self::assertStringContainsString("'mailEnabled' =>", $controller);
		self::assertStringContainsString('dashboard-system-warning', $dashboard);
		self::assertStringContainsString('dashboard.notifications.disabled.message', $dashboard);
		self::assertStringContainsString('<?php if ($mailEnabled): ?>', $profile);
		self::assertStringContainsString('type="button" disabled', $profile);
		self::assertStringContainsString('class="btn-secondary" disabled', $profile);
		self::assertStringContainsString('button:disabled', $styles);
	}
}
