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
		self::assertStringContainsString('&lang=en', $message['body_text']);
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
		self::assertStringContainsString('&lang=de', $message['body_text']);
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

	public function testRecipientNotificationExpandsConfiguredPlaceholdersButAddsNoWrapper(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeRecipientNotification([
			'recipient_name' => 'Recipient',
			'owner_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'notification_locale' => 'en',
			'message_subject' => '{app}: note from {owner} for {name}',
			'message_body' => 'Hello {name}. This concerns {monitor}. Open {url}',
			'portal_url' => 'https://pulse.example.com/portal?token=abc',
		]);

		self::assertSame('Pulse: note from Owner for Recipient', $message['subject']);
		self::assertSame('Hello Recipient. This concerns Weekly check. Open https://pulse.example.com/portal?token=abc', $message['body_text']);
		self::assertStringNotContainsString('/documents/', $message['body_text']);
		self::assertStringNotContainsString('/access', $message['body_text']);
	}

	public function testRecipientNotificationUsesLocalizedPulseDefaultWhenCustomTextIsEmpty(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeRecipientNotification([
			'recipient_name' => 'Empfänger',
			'owner_name' => 'Frank',
			'monitor_name' => 'Wichtiger Monitor',
			'notification_locale' => 'de',
			'message_subject' => '',
			'message_body' => '',
		]);

		self::assertSame('[Pulse] Nachricht von Frank', $message['subject']);
		self::assertStringContainsString('Hallo Empfänger', $message['body_text']);
		self::assertStringContainsString('Wichtiger Monitor', $message['body_text']);
	}

	public function testRecipientPortalUrlCarriesRecipientLanguage(): void
	{
		$composer = $this->Composer();
		$url = $composer->RecipientPortalUrl(str_repeat('a', 64), 'de');

		self::assertStringContainsString('/portal?token=' . str_repeat('a', 64), $url);
		self::assertStringContainsString('&lang=de', $url);
	}

	public function testRecipientAccessCodeMailContainsCodeAndLifetime(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeRecipientAccessCode([
			'recipient_name' => 'Recipient',
			'owner_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'notification_locale' => 'en',
			'access_code' => 'mako-rift',
			'valid_minutes' => 30,
		]);

		self::assertStringContainsString('mako-rift', $message['body_text']);
		self::assertStringContainsString('30 minutes', $message['body_text']);
		self::assertStringNotContainsString('@', $message['body_text']);
	}

	public function testBuiltInTemplatesCanBePreviewedInARequestedLanguage(): void
	{
		$composer = $this->Composer();
		$english = $composer->BuiltInTemplate('safety_invitation', 'en');
		$german = $composer->BuiltInTemplate('safety_invitation', 'de');

		self::assertStringContainsString('{owner}', $english['subject']);
		self::assertStringContainsString('{url}', $english['body_text']);
		self::assertNotSame($english['body_text'], $german['body_text']);
	}


	/** @brief Ensures a custom owner template is one literal override rather than a localized variant. */
	public function testCustomOwnerDueTemplateIsUsedExactlyAsWritten(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeOwnerDueNotice([
			'display_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'due_at' => '2026-08-11 10:00:00',
			'response_deadline_at' => '2026-08-13 10:00:00',
			'response_window_days' => 2,
			'max_reminders' => 3,
			'notification_locale' => 'de',
			'quick_checkin_url' => 'https://pulse.example.com/quick-check-in?token=abc',
			'mail_templates' => [
				'owner_due_notice' => [
					'owner' => [
						'subject' => 'Custom {monitor}',
						'body_text' => 'Use **this**: {quickurl}',
					],
				],
			],
		]);

		self::assertSame('Custom Weekly check', $message['subject']);
		self::assertSame('Use **this**: https://pulse.example.com/quick-check-in?token=abc', $message['body_text']);
		self::assertStringNotContainsString('Hallo', $message['body_text']);
	}

	/** @brief Ensures owner built-in defaults remain localized and expose the quick-check-in placeholder when enabled. */
	public function testOwnerBuiltInDefaultRemainsLocalizedWithQuickPlaceholder(): void
	{
		$composer = new NotificationComposer(
			new NotificationLanguage(['en', 'de'], 'en'),
			dirname(__DIR__, 2) . '/app/Lang',
			[
				'name' => 'Pulse',
				'base_url' => 'https://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
				'security' => ['passkey_quick_checkin_enabled' => true],
			]
		);
		$english = $composer->BuiltInTemplate('owner_due_notice', 'en', ['max_reminders' => 0]);
		$german = $composer->BuiltInTemplate('owner_due_notice', 'de', ['max_reminders' => 0]);

		self::assertStringContainsString('{quickcheckin}', $english['body_text']);
		self::assertStringContainsString('{quickcheckin}', $german['body_text']);
		self::assertNotSame($english['body_text'], $german['body_text']);
		self::assertStringNotContainsString('{max_followup_reminders}', $english['body_text']);
	}


	/** @brief Ensures built-in owner mail exposes quick check-in only through the explicit optional placeholder. */
	public function testBuiltInOwnerQuickCheckInIsExplicitlyExpanded(): void
	{
		$composer = $this->Composer();
		$withQuick = $composer->ComposeOwnerDueNotice([
			'display_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'due_at' => '2026-08-11 10:00:00',
			'response_deadline_at' => '2026-08-13 10:00:00',
			'response_window_days' => 2,
			'max_reminders' => 3,
			'notification_locale' => 'en',
			'quick_checkin_url' => 'https://pulse.example.com/quick-check-in?token=abc',
		]);
		$withoutQuick = $composer->ComposeOwnerDueNotice([
			'display_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'due_at' => '2026-08-11 10:00:00',
			'response_deadline_at' => '2026-08-13 10:00:00',
			'response_window_days' => 2,
			'max_reminders' => 3,
			'notification_locale' => 'en',
		]);

		self::assertStringContainsString('[authenticate with your passkey to check in all active monitors](https://pulse.example.com/quick-check-in?token=abc)', $withQuick['body_text']);
		self::assertStringNotContainsString('{quickcheckin}', $withQuick['body_text']);
		self::assertStringNotContainsString('Quick check-in:', $withoutQuick['body_text']);
		self::assertStringNotContainsString('{quickcheckin}', $withoutQuick['body_text']);
	}

	/** @brief Ensures custom owner mail receives no hidden quick-check-in content. */
	public function testCustomOwnerTemplateWithoutQuickPlaceholderGetsNoInjectedLink(): void
	{
		$composer = $this->Composer();
		$message = $composer->ComposeOwnerReminder([
			'display_name' => 'Owner',
			'monitor_name' => 'Weekly check',
			'due_at' => '2026-08-11 10:00:00',
			'max_reminders' => 3,
			'notification_locale' => 'en',
			'quick_checkin_url' => 'https://pulse.example.com/quick-check-in?token=abc',
			'mail_templates' => [
				'owner_reminder' => [
					'owner' => [
						'subject' => 'Reminder {number}',
						'body_text' => 'Normal sign-in: {url}',
					],
				],
			],
		], 1);

		self::assertSame('Reminder 1', $message['subject']);
		self::assertSame('Normal sign-in: https://pulse.example.com/login', $message['body_text']);
		self::assertStringNotContainsString('quick-check-in', $message['body_text']);
	}

	/** @brief Personal portal content exists only for an enabled non-empty recipient message. */
	public function testPortalMessageRequiresEnabledRecipientText(): void
	{
		$composer = $this->Composer();
		$base = [
			'recipient_name' => 'Alex',
			'owner_name' => 'Owner',
			'monitor_name' => 'Monitor',
			'notification_locale' => 'en',
			'portal_intro_text' => '',
			'portal_default_message' => 'Obsolete monitor default',
		];

		$disabled = $composer->ComposeRecipientPortalContent($base + [
			'portal_message_override_enabled' => false,
			'portal_message_override' => 'Private message',
		]);
		$empty = $composer->ComposeRecipientPortalContent($base + [
			'portal_message_override_enabled' => true,
			'portal_message_override' => '   ',
		]);
		$enabled = $composer->ComposeRecipientPortalContent($base + [
			'portal_message_override_enabled' => true,
			'portal_message_override' => 'Goodbye, {name}. — {owner}',
		]);

		self::assertSame('', $disabled['message_text']);
		self::assertSame('', $empty['message_text']);
		self::assertSame('Goodbye, Alex. — Owner', $enabled['message_text']);
		self::assertNotSame('', $enabled['intro_text']);
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
