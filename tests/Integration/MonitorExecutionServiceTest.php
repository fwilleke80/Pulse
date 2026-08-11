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

		foreach (['audit_log', 'check_cycles', 'monitors', 'users'] as $table)
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

		self::assertSame(['updated' => 2, 'escalated' => 0], $result);

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
				is_paused TINYINT(1) NOT NULL DEFAULT 0,
				paused_at DATETIME NULL,
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
				status ENUM(\'scheduled\',\'awaiting\',\'overdue\',\'escalated\',\'confirmed\',\'cancelled\') NOT NULL,
				started_at DATETIME NOT NULL,
				due_at DATETIME NOT NULL,
				response_deadline_at DATETIME NOT NULL,
				reminder_interval_days INT UNSIGNED NOT NULL,
				max_reminders INT UNSIGNED NOT NULL,
				reminders_sent INT UNSIGNED NOT NULL DEFAULT 0,
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
		$this->_connection->exec('INSERT INTO users (id) VALUES (1)');
		$this->_connection->exec("INSERT INTO monitors VALUES
			(1, 1, 3, 2, 1, 2, 0, NULL, UTC_TIMESTAMP(), TIMESTAMPADD(DAY, 3, UTC_TIMESTAMP()), UTC_TIMESTAMP(), UTC_TIMESTAMP()),
			(2, 1, 10, 2, 1, 2, 0, NULL, UTC_TIMESTAMP(), TIMESTAMPADD(DAY, 10, UTC_TIMESTAMP()), UTC_TIMESTAMP(), UTC_TIMESTAMP()),
			(3, 1, 7, 2, 1, 2, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
	}
}
