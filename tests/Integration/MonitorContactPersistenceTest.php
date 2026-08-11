<?php

/**
 * @file MonitorContactPersistenceTest.php
 * @brief Verifies that monitor edits preserve retained assignment IDs and recipient data.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Repositories\MonitorRepository;

class MonitorContactPersistenceTest extends TestCase
{
	private ?PDO $_connection = null;
	private ?MonitorRepository $_repository = null;

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
		$this->_repository = new MonitorRepository($database);
		$this->CreateFixture();
	}

	protected function tearDown(): void
	{
		if (!$this->_connection instanceof PDO)
		{
			return;
		}

		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 0');

		foreach (['document_monitor_contacts', 'contact_messages', 'documents', 'monitor_contacts', 'monitors', 'contacts', 'users'] as $table)
		{
			$this->_connection->exec('DROP TABLE IF EXISTS `' . $table . '`');
		}

		$this->_connection->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	public function testRetainedAssignmentsKeepTheirIdsAndDependentData(): void
	{
		self::assertInstanceOf(MonitorRepository::class, $this->_repository);
		$this->_repository->ReplaceContactsForMonitor(1, 1, [1, 2]);
		$initial = $this->AssignmentIds();
		$retainedId = $initial[1];

		$this->_connection?->exec("INSERT INTO contact_messages (monitor_contact_id, subject, body_text) VALUES ({$retainedId}, 'Subject', 'Body')");
		$this->_connection?->exec("INSERT INTO documents (id, monitor_id, title, storage_type) VALUES (1, 1, 'Document', 'text')");
		$this->_connection?->exec("INSERT INTO document_monitor_contacts (document_id, monitor_contact_id) VALUES (1, {$retainedId})");

		$this->_repository->ReplaceContactsForMonitor(1, 1, [1, 2]);
		self::assertSame($retainedId, $this->AssignmentIds()[1]);
		self::assertSame(1, (int)$this->_connection?->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn());
		self::assertSame(1, (int)$this->_connection?->query('SELECT COUNT(*) FROM document_monitor_contacts')->fetchColumn());

		$this->_repository->ReplaceContactsForMonitor(1, 1, [1]);
		self::assertSame($retainedId, $this->AssignmentIds()[1]);
		self::assertSame(1, (int)$this->_connection?->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn());
		self::assertSame(1, (int)$this->_connection?->query('SELECT COUNT(*) FROM document_monitor_contacts')->fetchColumn());
	}

	/** @return array<int, int> */
	private function AssignmentIds(): array
	{
		$rows = $this->_connection?->query('SELECT id, contact_id FROM monitor_contacts ORDER BY contact_id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
		$result = [];

		foreach ($rows as $row)
		{
			$result[(int)$row['contact_id']] = (int)$row['id'];
		}

		return $result;
	}

	private function CreateFixture(): void
	{
		self::assertInstanceOf(PDO::class, $this->_connection);
		$this->tearDown();
		$this->_connection->exec('CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE contacts (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE monitors (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE monitor_contacts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, monitor_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, sort_order INT NOT NULL, UNIQUE KEY (monitor_id, contact_id), FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE, FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE contact_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, monitor_contact_id BIGINT UNSIGNED NOT NULL, subject VARCHAR(255) NOT NULL, body_text LONGTEXT NOT NULL, FOREIGN KEY (monitor_contact_id) REFERENCES monitor_contacts(id) ON DELETE CASCADE) ENGINE=InnoDB');
		$this->_connection->exec("CREATE TABLE documents (id BIGINT UNSIGNED PRIMARY KEY, monitor_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, storage_type ENUM('text','file') NOT NULL, FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE) ENGINE=InnoDB");
		$this->_connection->exec('CREATE TABLE document_monitor_contacts (document_id BIGINT UNSIGNED NOT NULL, monitor_contact_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (document_id, monitor_contact_id), FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE, FOREIGN KEY (monitor_contact_id) REFERENCES monitor_contacts(id) ON DELETE CASCADE) ENGINE=InnoDB');
		$this->_connection->exec('INSERT INTO users (id) VALUES (1)');
		$this->_connection->exec('INSERT INTO contacts (id, user_id) VALUES (1, 1), (2, 1)');
		$this->_connection->exec('INSERT INTO monitors (id, user_id) VALUES (1, 1)');
	}
}
