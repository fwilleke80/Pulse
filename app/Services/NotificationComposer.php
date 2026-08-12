<?php

/**
 * @file NotificationComposer.php
 * @brief Builds immutable owner notification snapshots.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use DateTimeImmutable;
use DateTimeZone;
use Pulse\Core\NotificationLanguage;
use Pulse\Core\Translator;
use Throwable;

/**
 * @brief Creates localized immutable mail snapshots for owners, safety contacts, and recipients.
 */
final class NotificationComposer
{
	private NotificationLanguage $_languages;
	private string $_languagePath;
	/** @var array<string, Translator> */
	private array $_translators = [];
	private string $_appName;
	private string $_baseUrl;
	private DateTimeZone $_displayTimezone;

	/**
	 * @brief Constructs the composer.
	 * @param NotificationLanguage $languages Recipient-language resolver.
	 * @param string $languagePath Directory containing translation files.
	 * @param array<string, mixed> $config Application configuration.
	 */
	public function __construct(NotificationLanguage $languages, string $languagePath, array $config)
	{
		$this->_languages = $languages;
		$this->_languagePath = $languagePath;
		$this->_appName = (string)$config['name'];
		$this->_baseUrl = rtrim((string)$config['base_url'], '/');
		$this->_displayTimezone = new DateTimeZone((string)$config['display_timezone']);
	}

	/**
	 * @brief Composes the initial notification sent when a monitor becomes due.
	 * @param array<string, mixed> $cycle Awaiting cycle and owner data.
	 * @return array{subject: string, body_text: string}
	 */
	public function ComposeOwnerDueNotice(array $cycle): array
	{
		$locale = $this->_languages->Resolve(isset($cycle['notification_locale']) ? (string)$cycle['notification_locale'] : null);
		$responseWindowDays = $this->ResponseWindowDays($cycle);
		$params = [
			'app' => $this->_appName,
			'name' => (string)$cycle['display_name'],
			'monitor' => (string)$cycle['monitor_name'],
			'due' => $this->FormatUtc((string)$cycle['due_at']),
			'deadline' => $this->FormatUtc((string)$cycle['response_deadline_at']),
			'response_window' => $this->Translate(
				$locale,
				$responseWindowDays === 1 ? 'mail.duration.day' : 'mail.duration.days',
				['count' => $responseWindowDays]
			),
			'max_followup_reminders' => (int)$cycle['max_reminders'],
			'url' => $this->_baseUrl . '/login',
		];

		$bodyKey = (int)$cycle['max_reminders'] > 0
			? 'mail.owner_due_notice.body'
			: 'mail.owner_due_notice.body_no_reminders';

		return [
			'subject' => $this->Translate($locale, 'mail.owner_due_notice.subject', $params),
			'body_text' => $this->Translate($locale, $bodyKey, $params),
		];
	}

	/** @brief Resolves the configured response window for mail copy. */
	private function ResponseWindowDays(array $cycle): int
	{
		if (isset($cycle['response_window_days']) && (int)$cycle['response_window_days'] > 0)
		{
			return (int)$cycle['response_window_days'];
		}

		try
		{
			$due = new DateTimeImmutable((string)$cycle['due_at'], new DateTimeZone('UTC'));
			$deadline = new DateTimeImmutable((string)$cycle['response_deadline_at'], new DateTimeZone('UTC'));
			$days = $due->diff($deadline)->days;
			return max(1, is_int($days) ? $days : 1);
		}
		catch (Throwable)
		{
			return 1;
		}
	}

	/**
	 * @brief Composes one numbered reminder for a monitor owner.
	 * @param array<string, mixed> $cycle Awaiting cycle and owner data.
	 * @param int $reminderNumber One-based reminder number.
	 * @return array{subject: string, body_text: string}
	 */
	public function ComposeOwnerReminder(array $cycle, int $reminderNumber): array
	{
		$locale = $this->_languages->Resolve(isset($cycle['notification_locale']) ? (string)$cycle['notification_locale'] : null);
		$params = [
			'app' => $this->_appName,
			'name' => (string)$cycle['display_name'],
			'monitor' => (string)$cycle['monitor_name'],
			'due' => $this->FormatUtc((string)$cycle['due_at']),
			'number' => $reminderNumber,
			'total' => (int)$cycle['max_reminders'],
			'url' => $this->_baseUrl . '/login',
		];

		return [
			'subject' => $this->Translate($locale, 'mail.owner_reminder.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.owner_reminder.body', $params),
		];
	}

	/**
	 * @brief Composes a scanner-safe invitation for a safety contact.
	 * @param array<string, mixed> $request Safety-contact request snapshot.
	 * @param string $rawToken URL token whose resolver stores a hash; the raw value is embedded in the outgoing mail snapshot.
	 * @return array{subject: string, body_text: string}
	 */
	public function ComposeSafetyInvitation(array $request, string $rawToken): array
	{
		$locale = $this->_languages->Resolve(isset($request['notification_locale']) ? (string)$request['notification_locale'] : null);
		$params = [
			'app' => $this->_appName,
			'name' => (string)$request['contact_name'],
			'owner' => (string)$request['owner_name'],
			'monitor' => (string)$request['monitor_name'],
			'url' => $this->_baseUrl . '/safety/confirm?token=' . rawurlencode($rawToken),
		];

		return [
			'subject' => $this->Translate($locale, 'mail.safety_invitation.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.safety_invitation.body', $params),
		];
	}

	/**
	 * @brief Composes one numbered safety-contact reminder.
	 * @param array<string, mixed> $request Safety-contact request snapshot.
	 * @param string $rawToken URL token whose resolver stores a hash; the raw value is embedded in the outgoing mail snapshot.
	 * @param int $reminderNumber One-based reminder number.
	 * @return array{subject: string, body_text: string}
	 */
	public function ComposeSafetyReminder(array $request, string $rawToken, int $reminderNumber): array
	{
		$locale = $this->_languages->Resolve(isset($request['notification_locale']) ? (string)$request['notification_locale'] : null);
		$params = [
			'app' => $this->_appName,
			'name' => (string)$request['contact_name'],
			'owner' => (string)$request['owner_name'],
			'monitor' => (string)$request['monitor_name'],
			'number' => $reminderNumber,
			'total' => (int)$request['safety_max_reminders'],
			'url' => $this->_baseUrl . '/safety/confirm?token=' . rawurlencode($rawToken),
		];

		return [
			'subject' => $this->Translate($locale, 'mail.safety_reminder.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.safety_reminder.body', $params),
		];
	}

	/**
	 * @brief Wraps the owner's configured message in localized recipient context.
	 * @param array<string, mixed> $recipient Immutable recipient and message snapshot.
	 * @return array{subject: string, body_text: string}
	 */
	public function ComposeRecipientNotification(array $recipient): array
	{
		$locale = $this->_languages->Resolve(isset($recipient['notification_locale']) ? (string)$recipient['notification_locale'] : null);
		$params = [
			'app' => $this->_appName,
			'name' => (string)$recipient['recipient_name'],
			'owner' => (string)$recipient['owner_name'],
			'monitor' => (string)$recipient['monitor_name'],
			'message_subject' => (string)$recipient['message_subject'],
			'message_body' => (string)$recipient['message_body'],
		];

		return [
			'subject' => $this->Translate($locale, 'mail.recipient_notification.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.recipient_notification.body', $params),
		];
	}

	/** @return array{subject: string, body_text: string} @brief Composes a delivery-system test. */
	public function ComposeTest(string $displayName, ?string $notificationLocale): array
	{
		$locale = $this->_languages->Resolve($notificationLocale);
		$params = [
			'app' => $this->_appName,
			'name' => $displayName,
			'time' => (new DateTimeImmutable('now', $this->_displayTimezone))->format('Y-m-d H:i T'),
		];

		return [
			'subject' => $this->Translate($locale, 'mail.test.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.test.body', $params),
		];
	}

	/** @brief Performs parameter replacement without HTML escaping. */
	private function Translate(string $locale, string $key, array $params): string
	{
		if (!isset($this->_translators[$locale]))
		{
			$this->_translators[$locale] = new Translator($this->_languagePath, $locale);
		}

		$text = $this->_translators[$locale]->Translate($key);

		foreach ($params as $name => $value)
		{
			$text = str_replace('{' . $name . '}', (string)$value, $text);
		}

		return $text;
	}

	/** @brief Formats a UTC database timestamp for human-readable mail. */
	private function FormatUtc(string $value): string
	{
		try
		{
			return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
				->setTimezone($this->_displayTimezone)
				->format('Y-m-d H:i T');
		}
		catch (Throwable)
		{
			return $value;
		}
	}
}
