<?php

/**
 * @file NotificationQueueTest.php
 * @brief MySQL integration tests for durable, leased, retrying mail delivery.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Core\Logger;
use Pulse\Mail\MailTransportException;
use Pulse\Mail\MailTransportInterface;
use Pulse\Repositories\MailQueueRepository;
use Pulse\Services\MailQueueWorker;

class NotificationQueueTest extends TestCase
{
	private ?PDO $_connection = null;
	private ?MailQueueRepository $_queue = null;

	protected function setUp(): void
	{
		$databaseName = getenv('PULSE_TEST_DB_DATABASE');

		if (!is_string($databaseName) || !str_ends_with($databaseName, '_test'))
		{
			self::markTestSkipped('Set PULSE_TEST_DB_DATABASE to a dedicated database ending in _test.');
		}

		$database = new Database([
			'host' => getenv('PULSE_TEST_DB_HOST') ?: 'localhost',
			'port' => (int)(getenv('PULSE_TEST_DB_PORT') ?: 3306),
			'database' => $databaseName,
			'username' => getenv('PULSE_TEST_DB_USERNAME') ?: '',
			'password' => getenv('PULSE_TEST_DB_PASSWORD') ?: '',
			'charset' => 'utf8mb4',
		]);
		$this->_connection = $database->GetConnection();
		$this->_queue = new MailQueueRepository($database);
		$this->CreateFixture();
	}

	protected function tearDown(): void
	{
		if (!$this->_connection instanceof PDO)
		{
			return;
		}

		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 0');

		foreach (['mail_log', 'mail_queue', 'audit_log', 'check_cycles', 'monitors', 'users'] as $table)
		{
			$this->_connection->exec('DROP TABLE IF EXISTS `' . $table . '`');
		}

		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	public function testSuccessfulReminderAdvancesCycleOnlyAfterTransportSuccess(): void
	{
		self::assertInstanceOf(MailQueueRepository::class, $this->_queue);
		$jobId = $this->EnqueueReminder('reminder:test:1');
		$transport = new RecordingMailTransport();
		$worker = new MailQueueWorker($this->_queue, $transport, new Logger(sys_get_temp_dir() . '/pulse-mail-test.log'), 120, [0]);
		$result = $worker->Process(10);

		self::assertSame(1, $result['sent']);
		self::assertSame('sent', $this->_connection?->query('SELECT status FROM mail_queue WHERE id = ' . $jobId)->fetchColumn());
		self::assertSame(1, (int)$this->_connection?->query('SELECT reminders_sent FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame(1, (int)$this->_connection?->query("SELECT COUNT(*) FROM audit_log WHERE event_type = 'mail.reminder_sent'")->fetchColumn());
		self::assertCount(1, $transport->Messages);
	}

	public function testSuccessfulDueNoticeRecordsSeparateCycleState(): void
	{
		self::assertInstanceOf(MailQueueRepository::class, $this->_queue);
		$jobId = $this->_queue->Enqueue([
			'user_id' => 1,
			'check_cycle_id' => 1,
			'monitor_id' => 1,
			'contact_id' => null,
			'mail_type' => 'owner_due_notice',
			'idempotency_key' => 'due-notice:test:1',
			'reminder_number' => null,
			'recipient_email' => 'owner@example.com',
			'subject' => 'Check-in due',
			'body_text' => 'Please check in.',
			'max_attempts' => 3,
			'available_at' => gmdate('Y-m-d H:i:s'),
		]);
		$transport = new RecordingMailTransport();
		$worker = new MailQueueWorker($this->_queue, $transport, new Logger(sys_get_temp_dir() . '/pulse-mail-test.log'), 120, [0]);

		self::assertSame(1, $worker->Process(10)['sent']);
		self::assertSame('sent', $this->_connection?->query('SELECT status FROM mail_queue WHERE id = ' . $jobId)->fetchColumn());
		self::assertNotFalse($this->_connection?->query('SELECT due_notice_sent_at FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame(0, (int)$this->_connection?->query('SELECT reminders_sent FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame(1, (int)$this->_connection?->query("SELECT COUNT(*) FROM audit_log WHERE event_type = 'mail.due_notice_sent'")->fetchColumn());
	}

	public function testTransientFailureIsRetriedAndThenSent(): void
	{
		self::assertInstanceOf(MailQueueRepository::class, $this->_queue);
		$jobId = $this->EnqueueReminder('reminder:test:retry');
		$transport = new RecordingMailTransport(1);
		$worker = new MailQueueWorker($this->_queue, $transport, new Logger(sys_get_temp_dir() . '/pulse-mail-test.log'), 120, [0]);

		self::assertSame(1, $worker->Process(10)['retrying']);
		self::assertSame(0, (int)$this->_connection?->query('SELECT reminders_sent FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame(1, $worker->Process(10)['sent']);
		self::assertSame('sent', $this->_connection?->query('SELECT status FROM mail_queue WHERE id = ' . $jobId)->fetchColumn());
		self::assertSame(2, (int)$this->_connection?->query('SELECT attempt_count FROM mail_queue WHERE id = ' . $jobId)->fetchColumn());
	}

	public function testIdempotencyAndSkipLockedPreventDuplicateClaims(): void
	{
		self::assertInstanceOf(MailQueueRepository::class, $this->_queue);
		$firstId = $this->EnqueueReminder('reminder:test:unique');
		$secondId = $this->EnqueueReminder('reminder:test:unique');
		self::assertSame($firstId, $secondId);
		self::assertCount(1, $this->_queue->ClaimBatch('worker-a', 10, 120));
		self::assertCount(0, $this->_queue->ClaimBatch('worker-b', 10, 120));
	}

	private function EnqueueReminder(string $key): int
	{
		self::assertInstanceOf(MailQueueRepository::class, $this->_queue);
		return $this->_queue->Enqueue([
			'user_id' => 1,
			'check_cycle_id' => 1,
			'monitor_id' => 1,
			'contact_id' => null,
			'mail_type' => 'owner_reminder',
			'idempotency_key' => $key,
			'reminder_number' => 1,
			'recipient_email' => 'owner@example.com',
			'subject' => 'Reminder',
			'body_text' => 'Please check in.',
			'max_attempts' => 3,
			'available_at' => gmdate('Y-m-d H:i:s'),
		]);
	}

	private function CreateFixture(): void
	{
		self::assertInstanceOf(PDO::class, $this->_connection);
		$this->tearDown();
		$this->_connection->exec('CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(255), display_name VARCHAR(255), is_active TINYINT(1)) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE monitors (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED, name VARCHAR(255), is_paused TINYINT(1)) ENGINE=InnoDB');
		$this->_connection->exec("CREATE TABLE check_cycles
		(
			id BIGINT UNSIGNED PRIMARY KEY,
			monitor_id BIGINT UNSIGNED,
			status ENUM('scheduled','awaiting','safety_pending','overdue','escalated','confirmed','cancelled'),
			reminders_sent INT UNSIGNED NOT NULL DEFAULT 0,
			due_notice_sent_at DATETIME NULL,
			last_reminder_sent_at DATETIME NULL,
			updated_at DATETIME NOT NULL
		) ENGINE=InnoDB");
		$this->_connection->exec("CREATE TABLE mail_queue
		(
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			user_id BIGINT UNSIGNED NOT NULL,
			check_cycle_id BIGINT UNSIGNED NULL,
			monitor_id BIGINT UNSIGNED NULL,
			contact_id BIGINT UNSIGNED NULL,
			safety_request_id BIGINT UNSIGNED NULL,
			recipient_delivery_id BIGINT UNSIGNED NULL,
			mail_type VARCHAR(50) NOT NULL,
			idempotency_key VARCHAR(191) NOT NULL UNIQUE,
			reminder_number INT UNSIGNED NULL,
			recipient_email VARCHAR(255) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			body_text LONGTEXT NOT NULL,
			status ENUM('queued','retrying','processing','sent','failed','cancelled') NOT NULL,
			attempt_count INT UNSIGNED NOT NULL,
			max_attempts INT UNSIGNED NOT NULL,
			last_error TEXT NULL,
			available_at DATETIME NOT NULL,
			locked_at DATETIME NULL,
			locked_until DATETIME NULL,
			locked_by VARCHAR(64) NULL,
			lease_token CHAR(32) NULL,
			sent_at DATETIME NULL,
			failed_at DATETIME NULL,
			cancelled_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL
		) ENGINE=InnoDB");
		$this->_connection->exec("CREATE TABLE mail_log
		(
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			queue_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			check_cycle_id BIGINT UNSIGNED NULL,
			mail_type VARCHAR(50) NOT NULL,
			recipient_email VARCHAR(255) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			attempt_number INT UNSIGNED NOT NULL,
			status ENUM('sent','retrying','failed') NOT NULL,
			error_message TEXT NULL,
			smtp_message TEXT NULL,
			created_at DATETIME NOT NULL
		) ENGINE=InnoDB");
		$this->_connection->exec('CREATE TABLE audit_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED, event_type VARCHAR(100), entity_type VARCHAR(100), entity_id BIGINT UNSIGNED, message TEXT, context_json JSON NULL, created_at DATETIME) ENGINE=InnoDB');
		$this->_connection->exec("INSERT INTO users VALUES (1, 'owner@example.com', 'Owner', 1)");
		$this->_connection->exec("INSERT INTO monitors VALUES (1, 1, 'Weekly check', 0)");
		$this->_connection->exec("INSERT INTO check_cycles VALUES (1, 1, 'awaiting', 0, NULL, NULL, UTC_TIMESTAMP())");
	}
}

/** @brief Deterministic fake mail transport for queue integration tests. */
final class RecordingMailTransport implements MailTransportInterface
{
	/** @var array<int, array{recipient: string, subject: string, body: string}> */
	public array $Messages = [];
	private int $_failuresRemaining;

	/** @brief Constructs a transport that fails a selected number of first attempts. */
	public function __construct(int $failuresRemaining = 0)
	{
		$this->_failuresRemaining = $failuresRemaining;
	}

	/** @inheritDoc */
	public function Send(string $recipientEmail, string $subject, string $bodyText): void
	{
		if ($this->_failuresRemaining > 0)
		{
			$this->_failuresRemaining--;
			throw new MailTransportException('Synthetic transient failure.');
		}

		$this->Messages[] = ['recipient' => $recipientEmail, 'subject' => $subject, 'body' => $bodyText];
	}
}
