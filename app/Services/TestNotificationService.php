<?php

/**
 * @file TestNotificationService.php
 * @brief Queues and immediately attempts authenticated SMTP test notifications.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Repositories\MailQueueRepository;

/**
 * @brief Exercises the same durable queue and SMTP worker used by reminders.
 */
final class TestNotificationService
{
	private MailQueueRepository $_queue;
	private NotificationComposer $_composer;
	private MailQueueWorker $_worker;
	private bool $_enabled;
	private int $_maxAttempts;

	/** @brief Constructs the service. */
	public function __construct(
		MailQueueRepository $queue,
		NotificationComposer $composer,
		MailQueueWorker $worker,
		bool $enabled,
		int $maxAttempts
	)
	{
		$this->_queue = $queue;
		$this->_composer = $composer;
		$this->_worker = $worker;
		$this->_enabled = $enabled;
		$this->_maxAttempts = $maxAttempts;
	}

	/**
	 * @brief Sends a test to the authenticated owner's current profile address.
	 * @param array<string, mixed> $user User row.
	 * @return string Queue outcome.
	 */
	public function SendForUser(array $user): string
	{
		if (!$this->_enabled)
		{
			return 'disabled';
		}

		$content = $this->_composer->ComposeTest(
			(string)$user['display_name'],
			isset($user['notification_locale']) ? (string)$user['notification_locale'] : null
		);
		$jobId = $this->_queue->Enqueue([
			'user_id' => (int)$user['id'],
			'check_cycle_id' => null,
			'monitor_id' => null,
			'contact_id' => null,
			'mail_type' => 'test',
			'idempotency_key' => 'test:' . (int)$user['id'] . ':' . bin2hex(random_bytes(16)),
			'reminder_number' => null,
			'recipient_email' => (string)$user['email'],
			'subject' => $content['subject'],
			'body_text' => $content['body_text'],
			'max_attempts' => $this->_maxAttempts,
			'available_at' => gmdate('Y-m-d H:i:s'),
		]);

		return $this->_worker->ProcessById($jobId);
	}
}
