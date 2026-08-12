<?php

/**
 * @file NotificationComposerTest.php
 * @brief Tests immutable localized notification content.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\NotificationLanguage;
use Pulse\Services\NotificationComposer;

class NotificationComposerTest extends TestCase
{
	public function testOwnerDueNoticeExplainsTheResponseDeadline(): void
	{
		$composer = new NotificationComposer(
			new NotificationLanguage(['en', 'de'], 'de'),
			dirname(__DIR__, 2) . '/app/Lang',
			[
				'name' => 'Pulse',
				'base_url' => 'https://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
			]
		);
		$message = $composer->ComposeOwnerDueNotice([
			'display_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'due_at' => '2026-08-11 10:00:00',
			'response_deadline_at' => '2026-08-13 10:00:00',
			'response_window_days' => 2,
			'max_reminders' => 3,
			'notification_locale' => 'en',
		]);

		self::assertStringContainsString('Check-in due', $message['subject']);
		self::assertStringContainsString('Weekly check', $message['subject']);
		self::assertStringContainsString('response window remains open until', $message['body_text']);
		self::assertStringContainsString('within 2 days', $message['body_text']);
		self::assertStringContainsString('up to 3 follow-up reminders', $message['body_text']);
		self::assertStringContainsString('https://pulse.example.com/login', $message['body_text']);
	}

	public function testGermanDueNoticeUsesReadableParagraphsAndTheRecipientLink(): void
	{
		$composer = new NotificationComposer(
			new NotificationLanguage(['en', 'de'], 'en'),
			dirname(__DIR__, 2) . '/app/Lang',
			[
				'name' => 'Pulse',
				'base_url' => 'https://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
			]
		);
		$message = $composer->ComposeOwnerDueNotice([
			'display_name' => 'Frank',
			'monitor_name' => 'Wöchentliche Bestätigung',
			'due_at' => '2026-08-11 10:00:00',
			'response_deadline_at' => '2026-08-13 10:00:00',
			'response_window_days' => 2,
			'max_reminders' => 3,
			'notification_locale' => 'de',
		]);

		self::assertStringContainsString("Hallo Frank,\n\nfür den Monitor", $message['body_text']);
		self::assertStringContainsString("„Jetzt bestätigen“:\nhttps://pulse.example.com/login\n\n", $message['body_text']);
		self::assertStringContainsString('innerhalb von 2 Tagen', $message['body_text']);
		self::assertStringContainsString('bis zu 3 weitere Erinnerungen', $message['body_text']);
	}

	public function testOwnerReminderContainsLoginActionAndMonitorIdentity(): void
	{
		$composer = new NotificationComposer(
			new NotificationLanguage(['en', 'de'], 'de'),
			dirname(__DIR__, 2) . '/app/Lang',
			[
				'name' => 'Pulse',
				'base_url' => 'https://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
			]
		);
		$message = $composer->ComposeOwnerReminder([
			'display_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'due_at' => '2026-08-11 10:00:00',
			'max_reminders' => 3,
			'notification_locale' => 'en',
		], 2);

		self::assertStringContainsString('Weekly check', $message['subject']);
		self::assertStringContainsString('reminder 2 of 3', $message['body_text']);
		self::assertStringContainsString('https://pulse.example.com/login', $message['body_text']);
	}

	public function testMessagesUseTheRecipientLanguageRatherThanTheInterfaceLanguage(): void
	{
		$composer = new NotificationComposer(
			new NotificationLanguage(['en', 'de'], 'de'),
			dirname(__DIR__, 2) . '/app/Lang',
			[
				'name' => 'Pulse',
				'base_url' => 'https://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
			]
		);

		$english = $composer->ComposeTest('Owner', 'en');
		$german = $composer->ComposeTest('Owner', 'de');

		self::assertStringContainsString('Test notification', $english['subject']);
		self::assertStringContainsString('Testbenachrichtigung', $german['subject']);
	}

	public function testSafetyInvitationUsesAScannerSafeConfirmationPage(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeSafetyInvitation([
			'contact_name' => 'Trusted person',
			'owner_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'notification_locale' => 'en',
		], str_repeat('a', 64));

		self::assertStringContainsString('/safety/confirm?token=', $message['body_text']);
		self::assertStringContainsString('Opening the page does not confirm anything', $message['body_text']);
	}

	public function testCustomSafetyInvitationUsesConfiguredTextAndPlaceholders(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeSafetyInvitation([
			'contact_name' => 'Trusted person',
			'owner_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'notification_locale' => 'de',
			'message_subject' => 'Please check on {owner}',
			'message_body' => 'Hello {name}. Open {url} for {monitor}.',
		], str_repeat('b', 64));

		self::assertSame('Please check on Owner', $message['subject']);
		self::assertStringContainsString('Hello Trusted person.', $message['body_text']);
		self::assertStringContainsString('/safety/confirm?token=', $message['body_text']);
		self::assertStringContainsString('Weekly check', $message['body_text']);
		self::assertStringNotContainsString('Öffnen', $message['body_text']);
	}

	public function testCustomSafetyReminderUsesReminderCounters(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeSafetyReminder([
			'contact_name' => 'Trusted person',
			'owner_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'notification_locale' => 'en',
			'safety_max_reminders' => 3,
			'message_subject' => 'Reminder {number}/{total}',
			'message_body' => 'Please respond: {url}',
		], str_repeat('c', 64), 2);

		self::assertSame('Reminder 2/3', $message['subject']);
		self::assertStringContainsString('/safety/confirm?token=', $message['body_text']);
	}

	public function testRecipientNotificationContainsConfiguredMessageButNoDocumentAccess(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeRecipientNotification([
			'recipient_name' => 'Recipient',
			'owner_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'notification_locale' => 'en',
			'message_subject' => 'Personal note',
			'message_body' => 'This is the configured message.',
		]);

		self::assertSame('Personal note', $message['subject']);
		self::assertSame('This is the configured message.', $message['body_text']);
		self::assertStringNotContainsString('/documents/', $message['body_text']);
		self::assertStringNotContainsString('/access', $message['body_text']);
	}

	private function Composer(): NotificationComposer
	{
		return new NotificationComposer(
			new NotificationLanguage(['en', 'de'], 'en'),
			dirname(__DIR__, 2) . '/app/Lang',
			[
				'name' => 'Pulse',
				'base_url' => 'https://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
			]
		);
	}
}
