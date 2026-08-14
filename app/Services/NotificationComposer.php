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
			'url' => $this->_baseUrl . '/safety/confirm?token=' . rawurlencode($rawToken) . '&lang=' . rawurlencode($locale),
		];

		if (trim((string)($request['message_subject'] ?? '')) !== '' && trim((string)($request['message_body'] ?? '')) !== '')
		{
			return [
				'subject' => $this->ReplaceParams((string)$request['message_subject'], $params),
				'body_text' => $this->ReplaceParams((string)$request['message_body'], $params),
			];
		}

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
			'url' => $this->_baseUrl . '/safety/confirm?token=' . rawurlencode($rawToken) . '&lang=' . rawurlencode($locale),
		];

		if (trim((string)($request['message_subject'] ?? '')) !== '' && trim((string)($request['message_body'] ?? '')) !== '')
		{
			return [
				'subject' => $this->ReplaceParams((string)$request['message_subject'], $params),
				'body_text' => $this->ReplaceParams((string)$request['message_body'], $params),
			];
		}

		return [
			'subject' => $this->Translate($locale, 'mail.safety_reminder.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.safety_reminder.body', $params),
		];
	}

	/**
	 * @brief Composes a recipient notification from custom text or the localized Pulse fallback.
	 * @param array<string, mixed> $recipient Immutable recipient and message snapshot.
	 * @return array{subject: string, body_text: string}
	 */
	public function ComposeRecipientNotification(array $recipient): array
	{
		$locale = $this->_languages->Resolve(isset($recipient['notification_locale']) ? (string)$recipient['notification_locale'] : null);
		$params = [
			'app' => $this->_appName,
			'name' => (string)($recipient['recipient_name'] ?? ''),
			'owner' => (string)($recipient['owner_name'] ?? ''),
			'monitor' => (string)($recipient['monitor_name'] ?? ''),
			'url' => (string)($recipient['portal_url'] ?? ($this->_baseUrl . '/portal?token=…')),
		];

		if (trim((string)($recipient['message_subject'] ?? '')) !== '' && trim((string)($recipient['message_body'] ?? '')) !== '')
		{
			return [
				'subject' => $this->ReplaceParams((string)$recipient['message_subject'], $params),
				'body_text' => $this->ReplaceParams((string)$recipient['message_body'], $params),
			];
		}

		return [
			'subject' => $this->Translate($locale, 'mail.recipient_notification.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.recipient_notification.body', $params),
		];
	}


	/**
	 * @brief Composes the Pulse-authored short-lived recipient access-code email.
	 * @param array<string, mixed> $recipient Recipient delivery and generated code snapshot.
	 * @return array{subject: string, body_text: string}
	 */
	public function ComposeRecipientAccessCode(array $recipient): array
	{
		$locale = $this->_languages->Resolve(isset($recipient['notification_locale']) ? (string)$recipient['notification_locale'] : null);
		$params = [
			'app' => $this->_appName,
			'name' => (string)($recipient['recipient_name'] ?? ''),
			'owner' => (string)($recipient['owner_name'] ?? ''),
			'monitor' => (string)($recipient['monitor_name'] ?? ''),
			'code' => (string)($recipient['access_code'] ?? ''),
			'minutes' => (int)($recipient['valid_minutes'] ?? 30),
		];

		return [
			'subject' => $this->Translate($locale, 'mail.recipient_access_code.subject', $params),
			'body_text' => $this->Translate($locale, 'mail.recipient_access_code.body', $params),
		];
	}

	/**
	 * @brief Builds a recipient portal invitation URL for a raw token and locale.
	 * @param string $rawToken Raw 64-character portal token.
	 * @param string $locale Recipient locale.
	 * @return string Absolute portal URL.
	 */
	public function RecipientPortalUrl(string $rawToken, string $locale): string
	{
		$locale = $this->_languages->Resolve($locale);
		return $this->_baseUrl . '/portal?token=' . rawurlencode($rawToken) . '&lang=' . rawurlencode($locale);
	}

	/**
	 * @brief Returns one built-in mail template without expanding its placeholders.
	 * @param string $templateKey Supported template identifier.
	 * @param string $locale Requested locale.
	 * @return array{subject: string, body_text: string}
	 */
	public function BuiltInTemplate(string $templateKey, string $locale): array
	{
		$locale = $this->_languages->Resolve($locale);
		$keys = [
			'recipient_default' => ['mail.recipient_notification.subject', 'mail.recipient_notification.body'],
			'safety_invitation' => ['mail.safety_invitation.subject', 'mail.safety_invitation.body'],
			'safety_reminder' => ['mail.safety_reminder.subject', 'mail.safety_reminder.body'],
		];

		if (!isset($keys[$templateKey]))
		{
			throw new \InvalidArgumentException('Unsupported built-in mail template key.');
		}

		return [
			'subject' => $this->Translate($locale, $keys[$templateKey][0], []),
			'body_text' => $this->Translate($locale, $keys[$templateKey][1], []),
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

		return $this->ReplaceParams($this->_translators[$locale]->Translate($key), $params);
	}

	/** @brief Replaces supported template placeholders without HTML escaping. */
	private function ReplaceParams(string $text, array $params): string
	{
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
