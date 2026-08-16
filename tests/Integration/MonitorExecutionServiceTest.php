<?php

/**
 * @file MonitorExecutionServiceTest.php
 * @brief MySQL integration tests for atomic check-in lifecycle operations.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Core\Logger;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\MonitorStateMachine;

class MonitorExecutionServiceTest extends TestCase
{
	private ?PDO $_connection = null;
	private ?MonitorExecutionService $_service = null;

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
		$this->_service = new MonitorExecutionService(
			$database,
			new MonitorStateMachine(),
			new Logger(sys_get_temp_dir() . '/pulse-lifecycle-test.log')
		);
		$this->CreateFixture();
	}

	protected function tearDown(): void
	{
		if (!$this->_connection instanceof PDO)
		{
			return;
		}

		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 0');

		foreach (['recipient_release_deliveries', 'recipient_releases', 'safety_contact_requests', 'mail_queue', 'audit_log', 'check_cycles', 'monitors', 'users'] as $table)
		{
			$this->_connection->exec('DROP TABLE IF EXISTS `' . $table . '`');
		}

		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	public function testGlobalCheckInRestartsEveryActiveIntervalFromOneInstant(): void
	{
		self::assertInstanceOf(MonitorExecutionService::class, $this->_service);
		$this->_service->InitializeMonitorForUser(1, 1);
		$this->_service->InitializeMonitorForUser(2, 1);
		$result = $this->_service->CheckInAllActiveForUser(1);

		self::assertSame(['updated' => 2], $result);

		$rows = $this->_connection?->query('
			SELECT id, is_paused, last_confirmed_at, next_check_due_at,
				TIMESTAMPDIFF(DAY, last_confirmed_at, next_check_due_at) AS interval_days
			FROM monitors
			ORDER BY id
		')->fetchAll(PDO::FETCH_ASSOC) ?: [];

		self::assertSame($rows[0]['last_confirmed_at'], $rows[1]['last_confirmed_at']);
		self::assertSame(3, (int)$rows[0]['interval_days']);
		self::assertSame(10, (int)$rows[1]['interval_days']);
		self::assertNull($rows[2]['next_check_due_at']);
		self::assertSame(2, (int)$this->_connection?->query("SELECT COUNT(*) FROM check_cycles WHERE status = 'scheduled'")->fetchColumn());
		self::assertSame(2, (int)$this->_connection?->query("SELECT COUNT(*) FROM check_cycles WHERE status = 'confirmed'")->fetchColumn());
	}

	public function testPauseCancelsAndResumeStartsAFreshInterval(): void
	{
		self::assertInstanceOf(MonitorExecutionService::class, $this->_service);
		$this->_service->InitializeMonitorForUser(1, 1);

		self::assertTrue($this->_service->PauseMonitorForUser(1, 1));
		self::assertSame(1, (int)$this->_connection?->query('SELECT is_paused FROM monitors WHERE id = 1')->fetchColumn());
		$pausedMonitor = $this->_connection?->query('SELECT next_check_due_at FROM monitors WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
		self::assertIsArray($pausedMonitor);
		self::assertNull($pausedMonitor['next_check_due_at']);
		self::assertSame('cancelled', $this->_connection?->query('SELECT status FROM check_cycles WHERE monitor_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn());

		self::assertTrue($this->_service->ResumeMonitorForUser(1, 1));
		self::assertSame(0, (int)$this->_connection?->query('SELECT is_paused FROM monitors WHERE id = 1')->fetchColumn());
		self::assertSame(3, (int)$this->_connection?->query('SELECT TIMESTAMPDIFF(DAY, last_confirmed_at, next_check_due_at) FROM monitors WHERE id = 1')->fetchColumn());
		self::assertSame('scheduled', $this->_connection?->query('SELECT status FROM check_cycles WHERE monitor_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn());
	}

	public function testForceDueCreatesAPersistedAwaitingState(): void
	{
		self::assertInstanceOf(MonitorExecutionService::class, $this->_service);
		$this->_service->InitializeMonitorForUser(1, 1);

		self::assertTrue($this->_service->ForceDueForUser(1, 1));
		self::assertSame('awaiting', $this->_connection?->query('SELECT status FROM check_cycles WHERE monitor_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn());
		self::assertFalse($this->_service->ForceDueForUser(1, 1));
	}


	public function testEscalatedMonitorRequiresExplicitResetOrArchive(): void
	{
		self::assertInstanceOf(MonitorExecutionService::class, $this->_service);
		$this->_service->InitializeMonitorForUser(1, 1);
		self::assertTrue($this->_service->ForceDueForUser(1, 1));
		$cycleId = (int)$this->_connection?->query("SELECT id FROM check_cycles WHERE monitor_id = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
		$this->_connection?->exec('UPDATE check_cycles SET status = \'overdue\', overdue_at = UTC_TIMESTAMP() WHERE id = ' . $cycleId);
		self::assertTrue($this->_service->MarkCycleEscalated($cycleId));

		$result = $this->_service->CheckInAllActiveForUser(1);
		self::assertSame(1, (int)$result['updated']);
		self::assertSame('escalated', $this->_connection?->query('SELECT status FROM check_cycles WHERE id = ' . $cycleId)->fetchColumn());

		self::assertTrue($this->_service->ArchiveEscalatedMonitorForUser(1, 1));
		self::assertSame(1, (int)$this->_connection?->query('SELECT is_archived FROM monitors WHERE id = 1')->fetchColumn());
		self::assertTrue($this->_service->ResetAndReactivateMonitorForUser(1, 1));
		self::assertSame(0, (int)$this->_connection?->query('SELECT is_archived FROM monitors WHERE id = 1')->fetchColumn());
		self::assertSame('cancelled', $this->_connection?->query('SELECT status FROM check_cycles WHERE id = ' . $cycleId)->fetchColumn());
		self::assertSame('scheduled', $this->_connection?->query('SELECT status FROM check_cycles WHERE monitor_id = 1 ORDER BY id DESC LIMIT 1')->fetchColumn());
	}

	public function testOverdueRequiresTheInitialDueNoticeToHaveBeenSent(): void
	{
		self::assertInstanceOf(MonitorExecutionService::class, $this->_service);
		$this->_service->InitializeMonitorForUser(1, 1);
		self::assertTrue($this->_service->ForceDueForUser(1, 1));
		$this->_connection?->exec("
			UPDATE check_cycles
			SET response_deadline_at = TIMESTAMPADD(DAY, -3, UTC_TIMESTAMP()), reminders_sent = max_reminders
			WHERE monitor_id = 1 AND status = 'awaiting'
		");
		$cycleId = (int)$this->_connection?->query("SELECT id FROM check_cycles WHERE monitor_id = 1 AND status = 'awaiting'")->fetchColumn();

		self::assertFalse($this->_service->MarkCycleOverdue($cycleId));
		$this->_connection?->exec('UPDATE check_cycles SET due_notice_sent_at = UTC_TIMESTAMP() WHERE id = ' . $cycleId);
		self::assertTrue($this->_service->MarkCycleOverdue($cycleId));
	}

	private function CreateFixture(): void
	{
		self::assertInstanceOf(PDO::class, $this->_connection);
		$this->tearDown();
		$this->_connection->exec('CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
		$this->_connection->exec('
			CREATE TABLE monitors
			(
				id BIGINT UNSIGNED PRIMARY KEY,
				user_id BIGINT UNSIGNED NOT NULL,
				check_interval_days INT UNSIGNED NOT NULL,
				response_window_days INT UNSIGNED NOT NULL,
				reminder_interval_days INT UNSIGNED NOT NULL,
				max_reminders INT UNSIGNED NOT NULL,
				escalation_policy ENUM(\'direct\',\'safety_contact\') NOT NULL DEFAULT \'direct\',
				safety_response_window_days INT UNSIGNED NOT NULL DEFAULT 3,
				safety_reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1,
				safety_max_reminders INT UNSIGNED NOT NULL DEFAULT 1,
				safety_required_confirmations INT UNSIGNED NOT NULL DEFAULT 1,
				safety_confirmation_days INT UNSIGNED NULL,
				is_paused TINYINT(1) NOT NULL DEFAULT 0,
				paused_at DATETIME NULL,
				is_archived TINYINT(1) NOT NULL DEFAULT 0,
				archived_at DATETIME NULL,
				location_check_in_enabled TINYINT(1) NOT NULL DEFAULT 0,
				last_confirmed_at DATETIME NULL,
				next_check_due_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
			) ENGINE=InnoDB
		');
		$this->_connection->exec('
			CREATE TABLE check_cycles
			(
				id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				monitor_id BIGINT UNSIGNED NOT NULL,
				status ENUM(\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\',\'confirmed\',\'cancelled\') NOT NULL,
				started_at DATETIME NOT NULL,
				due_at DATETIME NOT NULL,
				response_deadline_at DATETIME NOT NULL,
				reminder_interval_days INT UNSIGNED NOT NULL,
				max_reminders INT UNSIGNED NOT NULL,
				escalation_policy_snapshot ENUM(\'direct\',\'safety_contact\') NOT NULL DEFAULT \'direct\',
				safety_response_window_days INT UNSIGNED NOT NULL DEFAULT 3,
				safety_reminder_interval_days INT UNSIGNED NOT NULL DEFAULT 1,
				safety_max_reminders INT UNSIGNED NOT NULL DEFAULT 1,
				safety_required_confirmations INT UNSIGNED NOT NULL DEFAULT 1,
				safety_confirmation_days INT UNSIGNED NOT NULL DEFAULT 1,
				reminders_sent INT UNSIGNED NOT NULL DEFAULT 0,
				due_notice_sent_at DATETIME NULL,
				last_reminder_sent_at DATETIME NULL,
				safety_gate_started_at DATETIME NULL,
				safety_gate_deadline_at DATETIME NULL,
				safety_confirmed_at DATETIME NULL,
				safety_confirmation_count INT UNSIGNED NOT NULL DEFAULT 0,
				confirmed_at DATETIME NULL,
				overdue_at DATETIME NULL,
				escalated_at DATETIME NULL,
				cancelled_at DATETIME NULL,
				updated_at DATETIME NOT NULL,
				FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
			) ENGINE=InnoDB
		');
		$this->_connection->exec('
			CREATE TABLE audit_log
			(
				id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				user_id BIGINT UNSIGNED NOT NULL,
				event_type VARCHAR(100) NOT NULL,
				entity_type VARCHAR(100) NULL,
				entity_id BIGINT UNSIGNED NULL,
				message TEXT NOT NULL,
				context_json JSON NULL,
				created_at DATETIME NOT NULL
			) ENGINE=InnoDB
		');
		$this->_connection->exec("CREATE TABLE mail_queue
		(
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			check_cycle_id BIGINT UNSIGNED NULL,
			mail_type VARCHAR(50) NOT NULL,
			body_text LONGTEXT NOT NULL,
			status ENUM('queued','retrying','processing','sent','failed','cancelled') NOT NULL,
			cancelled_at DATETIME NULL,
			updated_at DATETIME NOT NULL
		) ENGINE=InnoDB");
		$this->_connection->exec("CREATE TABLE safety_contact_requests
		(
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			check_cycle_id BIGINT UNSIGNED NOT NULL,
			status ENUM('pending','confirmed','declined','expired','cancelled') NOT NULL,
			updated_at DATETIME NOT NULL
		) ENGINE=InnoDB");
		$this->_connection->exec("CREATE TABLE recipient_releases
		(
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			check_cycle_id BIGINT UNSIGNED NOT NULL,
			status ENUM('blocked','pending','partial','sent','failed','cancelled') NOT NULL,
			cancelled_at DATETIME NULL,
			updated_at DATETIME NOT NULL
		) ENGINE=InnoDB");
		$this->_connection->exec("CREATE TABLE recipient_release_deliveries
		(
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			check_cycle_id BIGINT UNSIGNED NOT NULL,
			status ENUM('queued','sent','failed','cancelled') NOT NULL,
			cancelled_at DATETIME NULL,
			updated_at DATETIME NOT NULL
		) ENGINE=InnoDB");
		$this->_connection->exec('INSERT INTO users (id) VALUES (1)');
		$this->_connection->exec("INSERT INTO monitors
			(id, user_id, check_interval_days, response_window_days, reminder_interval_days, max_reminders, is_paused, paused_at, last_confirmed_at, next_check_due_at, created_at, updated_at)
			VALUES
			(1, 1, 3, 2, 1, 2, 0, NULL, UTC_TIMESTAMP(), TIMESTAMPADD(DAY, 3, UTC_TIMESTAMP()), UTC_TIMESTAMP(), UTC_TIMESTAMP()),
			(2, 1, 10, 2, 1, 2, 0, NULL, UTC_TIMESTAMP(), TIMESTAMPADD(DAY, 10, UTC_TIMESTAMP()), UTC_TIMESTAMP(), UTC_TIMESTAMP()),
			(3, 1, 7, 2, 1, 2, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
	}
}
