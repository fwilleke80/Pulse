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
			'max_reminders' => 3,
			'notification_locale' => 'en',
		]);

		self::assertStringContainsString('Check-in due', $message['subject']);
		self::assertStringContainsString('Weekly check', $message['subject']);
		self::assertStringContainsString('response window remains open until', $message['body_text']);
		self::assertStringContainsString('https://pulse.example.com/login', $message['body_text']);
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
}
