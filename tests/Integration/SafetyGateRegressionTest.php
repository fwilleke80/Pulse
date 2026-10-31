<?php

/**
 * @file SafetyGateRegressionTest.php
 * @brief Database-backed regression for the safety-contact gate and invitation queue.
 */

declare(strict_types=1);

namespace Pulse\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Core\Logger;
use Pulse\Core\NotificationLanguage;
use Pulse\Services\EscalationService;
use Pulse\Services\MonitorStateMachine;
use Pulse\Services\NotificationComposer;

/** @brief Runs the real safety gate against temporary tables in a dedicated MySQL test database. */
final class SafetyGateRegressionTest extends TestCase
{
	private ?PDO $_connection = null;
	private ?EscalationService $_escalation = null;

	private const TABLES = [
		'users', 'contacts', 'monitors', 'monitor_mail_templates', 'monitor_safety_contacts',
		'check_cycles', 'safety_contact_requests', 'safety_request_tokens',
		'safety_contact_request_emails', 'mail_queue', 'audit_log',
	];

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
		$this->CreateFixture();
		$this->_escalation = new EscalationService(
			$database,
			new MonitorStateMachine(),
			new NotificationComposer(
				new NotificationLanguage(['en', 'de'], 'en'),
				dirname(__DIR__, 2) . '/app/Lang',
				['name' => 'Pulse', 'base_url' => 'https://pulse.example.test', 'display_timezone' => 'Europe/Berlin']
			),
			new Logger(sys_get_temp_dir() . '/pulse-safety-gate-test.log'),
			3
		);
	}

	protected function tearDown(): void
	{
		if (!$this->_connection instanceof PDO)
		{
			return;
		}
		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 0');
		foreach (array_reverse(self::TABLES) as $table)
		{
			$this->_connection->exec('DROP TABLE IF EXISTS `' . $table . '`');
		}
		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	/** @brief The formerly missing postponement fields must not interrupt safety escalation or email enqueueing. */
	public function testOverdueMonitorQueuesThreeSafetyInvitationsExactlyOnce(): void
	{
		self::assertInstanceOf(EscalationService::class, $this->_escalation);
		self::assertSame(3, $this->_escalation->StartSafetyGate(1));
		self::assertSame('safety_pending', $this->_connection?->query('SELECT status FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame(3, (int)$this->_connection?->query('SELECT COUNT(*) FROM safety_contact_requests WHERE check_cycle_id = 1')->fetchColumn());
		self::assertSame(3, (int)$this->_connection?->query("SELECT COUNT(*) FROM mail_queue WHERE mail_type = 'safety_invitation' AND status = 'queued'")->fetchColumn());
		self::assertSame(0, $this->_escalation->StartSafetyGate(1));
		self::assertSame(3, (int)$this->_connection?->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn());
	}

	/** @brief Debug timeout skips reminders and expires a delivered gate on the next normal run. */
	public function testDebugExpirySkipsRemindersAndNormalSchedulerExpiresGate(): void
	{
		self::assertInstanceOf(EscalationService::class, $this->_escalation);
		self::assertSame(3, $this->_escalation->StartSafetyGate(1));
		self::assertFalse($this->_escalation->ExpireDebugSafetyWindowForUser(1, 987));
		self::assertSame(1, (int)$this->_connection?->query('SELECT safety_max_reminders FROM check_cycles WHERE id = 1')->fetchColumn());

		$this->_connection?->exec("UPDATE mail_queue SET status = 'sent', sent_at = UTC_TIMESTAMP() WHERE mail_type = 'safety_invitation'");
		$this->_connection?->exec('UPDATE safety_contact_requests SET invitation_sent_at = UTC_TIMESTAMP()');
		// A previously queued reminder must not be delivered after debug expiry.
		$this->_connection?->exec(<<<'SQL'
			INSERT INTO mail_queue (user_id, check_cycle_id, monitor_id, contact_id,
				safety_request_id, mail_type, idempotency_key, reminder_number,
				recipient_email, subject, body_text, status)
			SELECT user_id, check_cycle_id, monitor_id, contact_id,
				safety_request_id, 'safety_reminder', 'debug-test-reminder', 1,
				recipient_email, subject, body_text, 'queued'
			FROM mail_queue WHERE mail_type = 'safety_invitation' LIMIT 1
		SQL);

		self::assertTrue($this->_escalation->ExpireDebugSafetyWindowForUser(1, 1));
		self::assertSame(0, (int)$this->_connection?->query('SELECT safety_max_reminders FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame('cancelled', $this->_connection?->query("SELECT status FROM mail_queue WHERE mail_type = 'safety_reminder'")->fetchColumn());
		self::assertSame('safety_pending', $this->_connection?->query('SELECT status FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame(['reminders_ready' => 0, 'expired' => 1], $this->_escalation->RunSafetyGates());
		self::assertSame('overdue', $this->_connection?->query('SELECT status FROM check_cycles WHERE id = 1')->fetchColumn());
		self::assertSame(3, (int)$this->_connection?->query("SELECT COUNT(*) FROM safety_contact_requests WHERE status = 'expired'")->fetchColumn());
		self::assertSame(0, (int)$this->_connection?->query("SELECT COUNT(*) FROM mail_queue WHERE mail_type = 'recipient_notification'")->fetchColumn());
		self::assertSame(['reminders_ready' => 0, 'expired' => 0], $this->_escalation->RunSafetyGates());
	}

	/** @brief Debug expiry still cannot bypass undelivered safety invitations. */
	public function testDebugExpiryDoesNotBypassUnsentInvitations(): void
	{
		self::assertInstanceOf(EscalationService::class, $this->_escalation);
		self::assertSame(3, $this->_escalation->StartSafetyGate(1));
		self::assertTrue($this->_escalation->ExpireDebugSafetyWindowForUser(1, 1));
		self::assertSame(['reminders_ready' => 0, 'expired' => 0], $this->_escalation->RunSafetyGates());
		self::assertSame('safety_pending', $this->_connection?->query('SELECT status FROM check_cycles WHERE id = 1')->fetchColumn());
	}

	/** @brief Install only actual production-schema tables used by the safety-gate method. */
	private function CreateFixture(): void
	{
		self::assertInstanceOf(PDO::class, $this->_connection);
		$this->tearDown();
		$schema = (string)file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
		foreach (self::TABLES as $table)
		{
			$pattern = '/CREATE TABLE ' . preg_quote($table, '/') . '\\s*\\(.*?\\) ENGINE=InnoDB[^;]*;/s';
			self::assertSame(1, preg_match($pattern, $schema, $matches), 'Missing reference schema for ' . $table);
			$this->_connection->exec($matches[0]);
		}
		$this->_connection->exec("INSERT INTO users (id, email, password_hash, display_name, role, notification_locale) VALUES (1, 'owner@example.test', 'dummy', 'Owner', 'administrator', 'de')");
		$this->_connection->exec("INSERT INTO monitors (id, user_id, name, check_interval_days, response_window_days, reminder_interval_days, escalation_policy, safety_required_confirmations, safety_postponement_limit, consecutive_safety_postponements) VALUES (1, 1, 'Test monitor', 1, 1, 1, 'safety_contact', 1, 2, 0)");
		$this->_connection->exec("INSERT INTO check_cycles (id, monitor_id, status, started_at, due_at, response_deadline_at, escalation_policy_snapshot, safety_required_confirmations) VALUES (1, 1, 'awaiting', UTC_TIMESTAMP() - INTERVAL 3 DAY, UTC_TIMESTAMP() - INTERVAL 2 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY, 'safety_contact', 1)");
		for ($number = 1; $number <= 3; ++$number)
		{
			$this->_connection->exec("INSERT INTO contacts (id, user_id, name, email, email_checked_at, notification_locale) VALUES ($number, 1, 'Safety $number', 'safety$number@example.test', UTC_TIMESTAMP(), 'de')");
			$this->_connection->exec("INSERT INTO monitor_safety_contacts (monitor_id, contact_id, sort_order) VALUES (1, $number, $number)");
		}
	}
}
