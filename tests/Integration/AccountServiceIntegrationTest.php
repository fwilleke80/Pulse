<?php

declare(strict_types=1);

namespace Pulse\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Repositories\AccountRepository;
use Pulse\Services\AccountService;
use RuntimeException;

final class AccountServiceIntegrationTest extends TestCase
{
	private ?PDO $_connection = null;
	private AccountService $_service;
	private int $_ownerId;
	private const TABLES = ['account_tokens', 'account_write_lock', 'user_totp_recovery_codes', 'user_totp_credentials', 'user_passkey_credentials', 'user_security_methods', 'mail_queue', 'audit_log', 'users'];

	protected function setUp(): void
	{
		$name = getenv('PULSE_TEST_DB_DATABASE');
		if (!is_string($name) || !str_ends_with($name, '_test'))
		{
			self::markTestSkipped('Set PULSE_TEST_DB_DATABASE to a dedicated database ending in _test.');
		}
		$database = new Database(['host' => getenv('PULSE_TEST_DB_HOST') ?: 'localhost', 'port' => (int)(getenv('PULSE_TEST_DB_PORT') ?: 3306), 'database' => $name, 'username' => getenv('PULSE_TEST_DB_USERNAME') ?: '', 'password' => getenv('PULSE_TEST_DB_PASSWORD') ?: '', 'charset' => 'utf8mb4']);
		$this->_connection = $database->GetConnection();
		$this->DropTables();
		$schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
		preg_match_all('/CREATE TABLE (\w+)\s*\(.*?ENGINE=InnoDB[^;]*;/s', $schema, $matches, PREG_SET_ORDER);
		foreach ($matches as $match)
		{
			if (in_array($match[1], self::TABLES, true))
			{
				$this->_connection->exec($match[0]);
			}
		}
		$this->_connection->exec('INSERT INTO account_write_lock (id) VALUES (1)');
		$this->_connection->exec("INSERT INTO users (email, password_hash, display_name, role, is_super_user, email_verified_at, approved_at) VALUES ('owner@example.com', 'unused', 'Owner', 'administrator', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
		$this->_ownerId = (int)$this->_connection->lastInsertId();
		$this->_service = new AccountService(new AccountRepository($database), ['base_url' => 'https://pulse.example', 'registration_enabled' => true, 'registration_requires_approval' => true]);
	}

	protected function tearDown(): void
	{
		$this->DropTables();
	}

	private function DropTables(): void
	{
		if ($this->_connection === null)
		{
			return;
		}
		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 0');
		foreach (self::TABLES as $table)
		{
			$this->_connection->exec('DROP TABLE IF EXISTS ' . $table);
		}
		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	private function Register(): array
	{
		return $this->_service->Register(['email' => 'new@example.com', 'display_name' => 'New member', 'password' => 'A strong test password']);
	}

	private function LatestToken(): string
	{
		$body = (string)$this->_connection->query('SELECT body_text FROM mail_queue ORDER BY id DESC LIMIT 1')->fetchColumn();
		self::assertSame(1, preg_match('/token=([a-f0-9]{64})/', $body, $matches));
		return $matches[1];
	}

	public function testConfirmationDoesNotApproveAndCannotReplay(): void
	{
		$user = $this->Register();
		self::assertNull($user['email_verified_at']);
		self::assertNull($user['approved_at']);
		$token = $this->LatestToken();
		self::assertSame(hash('sha256', $token), $this->_connection->query('SELECT token_hash FROM account_tokens')->fetchColumn());
		$user = $this->_service->Confirm($token);
		self::assertNotNull($user['email_verified_at']);
		self::assertNull($user['approved_at']);
		$user = $this->_service->AdminUpdate($this->_ownerId, (int)$user['id'], ['approved' => true]);
		self::assertNotNull($user['approved_at']);
		self::assertSame(2, (int)$user['auth_version']);
		$this->expectException(RuntimeException::class);
		$this->_service->Confirm($token);
	}

	public function testCreateDoesNotSendUntilExplicitInviteAndAcceptSetsPassword(): void
	{
		$user = $this->_service->AdminCreate($this->_ownerId, ['email' => 'invited@example.com', 'display_name' => 'Invited']);
		self::assertSame(0, (int)$this->_connection->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn());
		$this->_service->Invite($this->_ownerId, (int)$user['id']);
		$token = $this->LatestToken();
		$user = $this->_service->SetPassword($token, 'A new strong password');
		self::assertSame(0, (int)$user['must_change_password']);
		self::assertNotNull($user['email_verified_at']);
		self::assertNotNull($user['approved_at']);
		$hash = $this->_connection->query('SELECT password_hash FROM users WHERE id = ' . (int)$user['id'])->fetchColumn();
		self::assertTrue(password_verify('A new strong password', $hash));
		$this->expectException(RuntimeException::class);
		$this->_service->SetPassword($token, 'Another strong password');
	}

	public function testExpiredTokenCannotVerify(): void
	{
		$this->Register();
		$token = $this->LatestToken();
		$this->_connection->exec('UPDATE account_tokens SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND');
		$this->expectException(RuntimeException::class);
		$this->_service->Confirm($token);
	}

	public function testQueueFailureRollsBackUserTokenAndAudit(): void
	{
		$this->_connection->exec("CREATE TRIGGER account_test_queue_failure BEFORE INSERT ON mail_queue FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected queue failure'");
		try
		{
			$this->Register();
			self::fail('Expected queue insertion to fail.');
		}
		catch (\PDOException $exception)
		{
			self::assertStringContainsString('Injected queue failure', $exception->getMessage());
		}
		self::assertSame(1, (int)$this->_connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
		self::assertSame(0, (int)$this->_connection->query('SELECT COUNT(*) FROM account_tokens')->fetchColumn());
		self::assertSame(0, (int)$this->_connection->query('SELECT COUNT(*) FROM audit_log')->fetchColumn());
		self::assertFalse($this->_connection->inTransaction());
	}

	public function testPasswordResetRevokesPasskeysAndOldLinksAndSessions(): void
	{
		$user = $this->Register();
		$id = (int)$user['id'];
		$oldToken = $this->LatestToken();
		$this->_connection->exec("INSERT INTO user_security_methods (user_id, method, label) VALUES ($id, 'passkey', 'Test passkey')");
		$this->_service->RequestPasswordReset($this->_ownerId, $id);
		$row = $this->_connection->query('SELECT * FROM users WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
		self::assertSame(2, (int)$row['auth_version']);
		self::assertSame(1, (int)$row['must_change_password']);
		self::assertFalse(password_verify('A strong test password', $row['password_hash']));
		self::assertSame(0, (int)$this->_connection->query('SELECT COUNT(*) FROM user_security_methods')->fetchColumn());
		self::assertSame(1, (int)$this->_connection->query('SELECT COUNT(*) FROM account_tokens WHERE revoked_at IS NOT NULL')->fetchColumn());
		$this->expectException(RuntimeException::class);
		$this->_service->Confirm($oldToken);
	}

	public function testMigrationProtectsEarliestOwnerAndBackfillsExistingAccounts(): void
	{
		$this->_connection->exec('DROP TABLE account_tokens');
		$this->_connection->exec('DROP TABLE account_write_lock');
		$this->_connection->exec('ALTER TABLE users DROP INDEX uq_users_super_user, DROP COLUMN super_user_slot, DROP COLUMN is_super_user, DROP COLUMN email_verified_at, DROP COLUMN approved_at, DROP COLUMN require_totp, DROP COLUMN must_change_password, DROP COLUMN auth_version');
		$this->_connection->exec("INSERT INTO users (email, password_hash, display_name, role) VALUES ('second@example.com', 'unused', 'Second', 'administrator')");
		$sql = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/011_multiuser_accounts.sql');
		$sql = preg_replace('/^--.*$/m', '', $sql);
		foreach (explode(';', $sql) as $statement)
		{
			if (trim($statement) !== '')
			{
				$this->_connection->exec($statement);
			}
		}
		$rows = $this->_connection->query('SELECT * FROM users ORDER BY created_at, id')->fetchAll(PDO::FETCH_ASSOC);
		self::assertSame(1, (int)$rows[0]['is_super_user']);
		self::assertSame(0, (int)$rows[1]['is_super_user']);
		self::assertNotNull($rows[1]['email_verified_at']);
		self::assertNotNull($rows[1]['approved_at']);
		$this->expectException(RuntimeException::class);
		$this->_service->AdminDelete((int)$rows[1]['id'], (int)$rows[0]['id']);
	}
}
