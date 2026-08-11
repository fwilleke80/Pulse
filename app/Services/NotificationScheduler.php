<?php

/**
 * @file NotificationScheduler.php
 * @brief Converts due check-cycle state into idempotent owner-notification jobs.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Pulse\Core\Database;
use Pulse\Core\Logger;
use Pulse\Repositories\MailQueueRepository;
use Throwable;

/**
 * @brief Schedules due notices, reminders, and honest overdue transitions for all users.
 */
final class NotificationScheduler
{
	private Database $_database;
	private MonitorExecutionService $_executionService;
	private MailQueueRepository $_queue;
	private NotificationComposer $_composer;
	private Logger $_logger;
	private int $_maxAttempts;

	/** @brief Constructs the scheduler. */
	public function __construct(
		Database $database,
		MonitorExecutionService $executionService,
		MailQueueRepository $queue,
		NotificationComposer $composer,
		Logger $logger,
		int $maxAttempts
	)
	{
		$this->_database = $database;
		$this->_executionService = $executionService;
		$this->_queue = $queue;
		$this->_composer = $composer;
		$this->_logger = $logger;
		$this->_maxAttempts = $maxAttempts;
	}

	/**
	 * @brief Synchronizes cycles, queues due notifications, and marks completed reminder windows overdue.
	 * @return array{opened: int, due_notices_ready: int, reminders_ready: int, overdue: int}
	 */
	public function Run(): array
	{
		$opened = $this->SynchronizeAllUsers();
		$dueNoticesReady = 0;
		$remindersReady = 0;
		$overdue = 0;
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

		foreach ($this->FindAwaitingCycles() as $cycle)
		{
			if (empty($cycle['due_notice_sent_at']))
			{
				$scheduledAt = $this->ParseUtc((string)$cycle['due_at']);

				if ($scheduledAt <= $now)
				{
					$this->EnqueueDueNotice($cycle, $scheduledAt);
					$dueNoticesReady++;
				}

				continue;
			}

			$sent = (int)$cycle['reminders_sent'];
			$maximum = (int)$cycle['max_reminders'];

			if ($sent < $maximum)
			{
				$reminderNumber = $sent + 1;
				$scheduledAt = $this->ReminderTime($cycle, $reminderNumber);

				if ($scheduledAt <= $now)
				{
					$content = $this->_composer->ComposeOwnerReminder($cycle, $reminderNumber);
					$this->_queue->Enqueue([
						'user_id' => (int)$cycle['user_id'],
						'check_cycle_id' => (int)$cycle['id'],
						'monitor_id' => (int)$cycle['monitor_id'],
						'contact_id' => null,
						'mail_type' => 'owner_reminder',
						'idempotency_key' => 'owner-reminder:' . (int)$cycle['id'] . ':' . $reminderNumber,
						'reminder_number' => $reminderNumber,
						'recipient_email' => (string)$cycle['email'],
						'subject' => $content['subject'],
						'body_text' => $content['body_text'],
						'max_attempts' => $this->_maxAttempts,
						'available_at' => $scheduledAt->format('Y-m-d H:i:s'),
					]);
					$remindersReady++;
				}

				continue;
			}

			$eligibleAt = $this->OverdueTime($cycle);

			if ($eligibleAt <= $now && $this->_executionService->MarkCycleOverdue((int)$cycle['id']))
			{
				$overdue++;
			}
		}

		$this->_logger->Info('Notification schedule synchronized', [
			'opened' => $opened,
			'due_notices_ready' => $dueNoticesReady,
			'reminders_ready' => $remindersReady,
			'overdue' => $overdue,
		]);

		return [
			'opened' => $opened,
			'due_notices_ready' => $dueNoticesReady,
			'reminders_ready' => $remindersReady,
			'overdue' => $overdue,
		];
	}

	/**
	 * @brief Queues one owned monitor's eligible due notice for immediate debug delivery.
	 * @return int|null Queue job ID, or null when no unsent awaiting due notice exists.
	 */
	public function QueueDueNoticeForMonitorForUser(int $monitorId, int $userId): ?int
	{
		$this->_executionService->SynchronizeMonitorForUser($monitorId, $userId);
		$cycle = $this->FindAwaitingCycleForMonitorForUser($monitorId, $userId);

		if (!is_array($cycle))
		{
			return null;
		}

		return $this->EnqueueDueNotice($cycle, new DateTimeImmutable('now', new DateTimeZone('UTC')));
	}

	/** @brief Synchronizes due cycles for every active account. */
	private function SynchronizeAllUsers(): int
	{
		$rows = $this->_database->GetConnection()->query('
			SELECT DISTINCT user_id
			FROM monitors
			WHERE is_paused = 0
			ORDER BY user_id ASC
		')->fetchAll(PDO::FETCH_COLUMN);
		$opened = 0;

		foreach (is_array($rows) ? $rows : [] as $userId)
		{
			$opened += $this->_executionService->SynchronizeDueCyclesForUser((int)$userId);
		}

		return $opened;
	}

	/** @return array<int, array<string, mixed>> @brief Returns all awaiting cycles with owner snapshots. */
	private function FindAwaitingCycles(): array
	{
		$rows = $this->_database->GetConnection()->query('
			SELECT
				cc.id,
				cc.monitor_id,
				cc.due_at,
				cc.response_deadline_at,
				cc.reminder_interval_days,
				cc.max_reminders,
				cc.reminders_sent,
				cc.due_notice_sent_at,
				m.user_id,
				m.name AS monitor_name,
				m.response_window_days,
				u.email,
				u.display_name,
				u.notification_locale
			FROM check_cycles cc
			INNER JOIN monitors m ON m.id = cc.monitor_id
			INNER JOIN users u ON u.id = m.user_id
			WHERE cc.status = \'awaiting\'
				AND m.is_paused = 0
				AND u.is_active = 1
			ORDER BY cc.id ASC
		')->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/** @return array<string, mixed>|null @brief Finds one owned awaiting cycle without a delivered due notice. */
	private function FindAwaitingCycleForMonitorForUser(int $monitorId, int $userId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT
				cc.id,
				cc.monitor_id,
				cc.due_at,
				cc.response_deadline_at,
				cc.reminder_interval_days,
				cc.max_reminders,
				cc.reminders_sent,
				cc.due_notice_sent_at,
				m.user_id,
				m.name AS monitor_name,
				m.response_window_days,
				u.email,
				u.display_name,
				u.notification_locale
			FROM check_cycles cc
			INNER JOIN monitors m ON m.id = cc.monitor_id
			INNER JOIN users u ON u.id = m.user_id
			WHERE cc.monitor_id = :monitor_id
				AND m.user_id = :user_id
				AND cc.status = \'awaiting\'
				AND cc.due_notice_sent_at IS NULL
				AND m.is_paused = 0
				AND u.is_active = 1
			ORDER BY cc.id DESC
			LIMIT 1
		');
		$statement->execute(['monitor_id' => $monitorId, 'user_id' => $userId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/** @brief Freezes and idempotently queues an initial due notice. */
	private function EnqueueDueNotice(array $cycle, DateTimeImmutable $availableAt): int
	{
		$content = $this->_composer->ComposeOwnerDueNotice($cycle);

		return $this->_queue->Enqueue([
			'user_id' => (int)$cycle['user_id'],
			'check_cycle_id' => (int)$cycle['id'],
			'monitor_id' => (int)$cycle['monitor_id'],
			'contact_id' => null,
			'mail_type' => 'owner_due_notice',
			'idempotency_key' => 'owner-due-notice:' . (int)$cycle['id'],
			'reminder_number' => null,
			'recipient_email' => (string)$cycle['email'],
			'subject' => $content['subject'],
			'body_text' => $content['body_text'],
			'max_attempts' => $this->_maxAttempts,
			'available_at' => $availableAt->format('Y-m-d H:i:s'),
		]);
	}

	/** @brief Calculates the scheduled time of a one-based reminder. */
	private function ReminderTime(array $cycle, int $reminderNumber): DateTimeImmutable
	{
		$deadline = $this->ParseUtc((string)$cycle['response_deadline_at']);
		$days = (int)$cycle['reminder_interval_days'] * max(0, $reminderNumber - 1);
		return $days === 0 ? $deadline : $deadline->add(new DateInterval('P' . $days . 'D'));
	}

	/** @brief Calculates when all sent reminders have received their full response window. */
	private function OverdueTime(array $cycle): DateTimeImmutable
	{
		$deadline = $this->ParseUtc((string)$cycle['response_deadline_at']);
		$days = (int)$cycle['reminder_interval_days'] * (int)$cycle['max_reminders'];
		return $days === 0 ? $deadline : $deadline->add(new DateInterval('P' . $days . 'D'));
	}

	/** @brief Parses a required UTC database timestamp. */
	private function ParseUtc(string $value): DateTimeImmutable
	{
		try
		{
			return new DateTimeImmutable($value, new DateTimeZone('UTC'));
		}
		catch (Throwable $throwable)
		{
			throw new \RuntimeException('Invalid check-cycle timestamp.', 0, $throwable);
		}
	}
}
