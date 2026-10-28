<?php
/** @file EmergencyResumeTest.php @brief Exercises atomic emergency resume against real local SQL transactions. */
declare(strict_types=1);
namespace Pulse\Tests\Unit;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Core\Logger;
use Pulse\Services\EmergencyStopService;
use Pulse\Services\MonitorExecutionService;
use Pulse\Services\MonitorStateMachine;
use RuntimeException;

/** @brief SQLite adapter removes only MySQL row-lock syntax; transaction behavior remains real. */
final class ResumeSql extends PDO
{
	public bool $failInsert = false;
	/** @brief Creates an isolated in-memory fixture. */
	public function __construct()
	{
		parent::__construct('sqlite::memory:');
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	/** @brief Adapts lock syntax and optionally injects a cycle insert failure. */
	public function prepare(string $query, array $options = []): PDOStatement|false
	{
		if ($this->failInsert && str_contains($query, 'INSERT INTO check_cycles'))
		{
			throw new RuntimeException('Injected cycle failure');
		}
		return parent::prepare(str_replace('FOR UPDATE', '', $query), $options);
	}
}

/** @brief Supplies the isolated connection to the unchanged production service. */
final class ResumeDatabase extends Database
{
	/** @brief Retains the test connection. */
	public function __construct(private PDO $_connection) {}
	/** @brief Returns the fixture connection. */
	public function GetConnection(): PDO
	{
		return $this->_connection;
	}
}

/** @brief Regresses the stuck Paused state and both owner-authorized resume entry points. */
final class EmergencyResumeTest extends TestCase
{
	/** @brief Builds fixture columns from the reference schema, without copying service behavior. */
	private function Fixture(): array
	{
		if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required'); }
		$db = new ResumeSql();
		$schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
		foreach (['monitors', 'check_cycles', 'audit_log', 'mail_queue', 'safety_contact_requests', 'recipient_release_deliveries', 'recipient_releases'] as $table)
		{
			preg_match('/CREATE TABLE ' . $table . '\s*\((.*?)\n\) ENGINE/s', $schema, $match);
			preg_match_all('/^\s*([a-z_]+)\s+(BIGINT|INT|TINYINT|SMALLINT|DATETIME|VARCHAR|TEXT|LONGTEXT|JSON|ENUM|CHAR)\b/m', $match[1], $columns, PREG_SET_ORDER);
			$definitions = [];
			foreach ($columns as $column)
			{
				$definitions[] = $column[1] === 'id' ? 'id INTEGER PRIMARY KEY' : $column[1] . (in_array($column[2], ['BIGINT','INT','TINYINT','SMALLINT']) ? ' INTEGER' : ' TEXT');
			}
			$db->exec('CREATE TABLE ' . $table . ' (' . implode(',', $definitions) . ')');
		}
		$database = new ResumeDatabase($db);
		$logger = new Logger(sys_get_temp_dir() . '/pulse-resume-regression.log');
		$execution = new MonitorExecutionService($database, new MonitorStateMachine(), $logger);
		return [$db, $execution, new EmergencyStopService($database, $execution, $logger)];
	}
	/** @brief Seeds a stopped owner monitor including the historical inconsistent state. */
	private function Monitor(PDO $db, int $id, int $paused, int $wasPaused = 0): void
	{
		$db->exec("INSERT INTO monitors (id,user_id,name,check_interval_days,response_window_days,reminder_interval_days,max_reminders,escalation_policy,safety_response_window_days,safety_reminder_interval_days,safety_max_reminders,safety_required_confirmations,safety_confirmation_days,is_paused,is_archived,emergency_stopped_at,emergency_was_paused,created_at) VALUES ($id,1,'Monitor',7,2,1,3,'direct',3,1,1,1,7,$paused,0,'2026-01-01 00:00:00',$wasPaused,'2026-01-01 00:00:00')");
	}
	/** @brief Editor check-in touches only its owned active monitor, preserving global check-in semantics. */
	public function testScopedCheckIn(): void
	{
		[$db, $execution] = $this->Fixture();
		$this->Monitor($db, 1, 1);
		$this->Monitor($db, 2, 1);
		$execution->ResumeMonitorForUser(1, 1);
		$execution->ResumeMonitorForUser(2, 1);
		self::assertSame(0, $execution->CheckInAllActiveForUser(99, 'manual', null, 1)['updated']);
		self::assertSame(1, $execution->CheckInAllActiveForUser(1, 'manual', null, 1)['updated']);
		self::assertSame(0, (int)$db->query("SELECT COUNT(*) FROM check_cycles WHERE monitor_id=2 AND status='confirmed'")->fetchColumn());
		self::assertSame(2, $execution->CheckInAllActiveForUser(1)['updated']);
	}

	/** @brief Normal Resume recovers both fully stopped and mixed historical states exactly once. */
	public function testExplicitResumeClearsBothFlagsAndSchedulesOneFreshCycle(): void
	{
		foreach ([0, 1] as $paused)
		{
			[$db, $execution] = $this->Fixture();
			$this->Monitor($db, 1, $paused);
			$db->exec("INSERT INTO check_cycles (id,monitor_id,status) VALUES (9,1,'scheduled')");
			$db->exec("INSERT INTO mail_queue (check_cycle_id,status,mail_type,body_text) VALUES (9,'queued','owner_due_notice','Old mail')");
			self::assertTrue($execution->ResumeMonitorForUser(1, 1));
			$row = $db->query('SELECT * FROM monitors WHERE id=1')->fetch(PDO::FETCH_ASSOC);
			self::assertSame(0, $row['is_paused']);
			self::assertNull($row['emergency_stopped_at']);
			self::assertSame(0, $row['emergency_was_paused']);
			self::assertSame(7 * 86400, strtotime($row['next_check_due_at']) - strtotime($row['last_confirmed_at']));
			self::assertSame('cancelled', $db->query('SELECT status FROM check_cycles WHERE id=9')->fetchColumn());
			self::assertSame('cancelled', $db->query('SELECT status FROM mail_queue')->fetchColumn());
			self::assertSame(1, (int)$db->query("SELECT COUNT(*) FROM check_cycles WHERE status='scheduled'")->fetchColumn());
			self::assertFalse($execution->ResumeMonitorForUser(1, 1));
		}
	}
	/** @brief Global reactivation preserves pre-existing pauses, while explicit resume can end them. */
	public function testGlobalReactivationPreservesEarlierPauseAndOwnerIsolation(): void
	{
		[$db, $execution, $emergency] = $this->Fixture();
		$this->Monitor($db, 1, 1);
		$this->Monitor($db, 2, 1, 1);
		self::assertFalse($execution->ResumeMonitorForUser(1, 99));
		self::assertSame(1, $emergency->ReactivateForUser(1));
		self::assertSame(1, (int)$db->query('SELECT is_paused FROM monitors WHERE id=2')->fetchColumn());
		self::assertNull($db->query('SELECT emergency_stopped_at FROM monitors WHERE id=2')->fetchColumn());
		self::assertSame(0, $emergency->ReactivateForUser(1));
		self::assertTrue($execution->ResumeMonitorForUser(2, 1));
	}
	/** @brief Failed scheduling rolls back the flag changes and remains retryable. */
	public function testSchedulingFailurePreservesEmergencyStop(): void
	{
		[$db, $execution] = $this->Fixture();
		$this->Monitor($db, 1, 1);
		$db->failInsert = true;
		try
		{
			$execution->ResumeMonitorForUser(1, 1);
			self::fail('Expected failure');
		}
		catch (RuntimeException $error)
		{
			self::assertSame('Injected cycle failure', $error->getMessage());
		}
		self::assertFalse($db->inTransaction());
		self::assertSame('2026-01-01 00:00:00', $db->query('SELECT emergency_stopped_at FROM monitors')->fetchColumn());
		self::assertSame(1, (int)$db->query('SELECT is_paused FROM monitors')->fetchColumn());
		$db->failInsert = false;
		self::assertTrue($execution->ResumeMonitorForUser(1, 1));
	}
}
