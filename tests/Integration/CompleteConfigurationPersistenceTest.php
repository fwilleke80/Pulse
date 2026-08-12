<?php

/**
 * @file CompleteConfigurationPersistenceTest.php
 * @brief Verifies message and editable text-document persistence.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Repositories\DocumentRepository;
use Pulse\Repositories\MessageRepository;
use Pulse\Repositories\MonitorRepository;

class CompleteConfigurationPersistenceTest extends TestCase
{
	private ?PDO $_connection = null;
	private ?MonitorRepository $_monitorRepository = null;
	private ?MessageRepository $_messageRepository = null;
	private ?DocumentRepository $_documentRepository = null;

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
		$this->_monitorRepository = new MonitorRepository($database);
		$this->_messageRepository = new MessageRepository($database);
		$this->_documentRepository = new DocumentRepository($database);
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

	public function testDefaultAndRecipientMessagesAreReplacedTogether(): void
	{
		self::assertInstanceOf(MonitorRepository::class, $this->_monitorRepository);
		self::assertInstanceOf(MessageRepository::class, $this->_messageRepository);
		$this->_monitorRepository->ReplaceContactsForMonitor(1, 1, [1, 2]);
		$assignmentIds = $this->AssignmentIds();

		$this->_messageRepository->ReplaceForMonitor(
			1,
			1,
			'Default subject',
			'Default body',
			[
				$assignmentIds[2] => [
					'subject' => 'Personal subject',
					'body_text' => 'Personal body',
				],
			]
		);

		$monitor = $this->_connection?->query('SELECT default_message_subject, default_message_body FROM monitors WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
		self::assertSame('Default subject', $monitor['default_message_subject'] ?? null);
		self::assertSame('Default body', $monitor['default_message_body'] ?? null);
		self::assertSame(
			[
				$assignmentIds[2] => [
					'subject' => 'Personal subject',
					'body_text' => 'Personal body',
				],
			],
			$this->_messageRepository->FindByMonitorIdForUser(1, 1)
		);

		$this->_messageRepository->ReplaceForMonitor(1, 1, null, null, []);
		self::assertSame([], $this->_messageRepository->FindByMonitorIdForUser(1, 1));
	}

	public function testTextDocumentCanBeCreatedEditedAndAssigned(): void
	{
		self::assertInstanceOf(MonitorRepository::class, $this->_monitorRepository);
		self::assertInstanceOf(DocumentRepository::class, $this->_documentRepository);
		$this->_monitorRepository->ReplaceContactsForMonitor(1, 1, [1]);
		$assignmentId = $this->AssignmentIds()[1];
		$documentId = $this->_documentRepository->CreateTextDocumentForMonitor(1, 'Instructions', 'Initial text');
		$this->_documentRepository->ReplaceRecipientsForDocument($documentId, [$assignmentId]);
		$this->_documentRepository->UpdateTextDocument($documentId, 'Updated instructions', 'Updated text');

		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, 1, 1);
		self::assertSame('text', $document['storage_type'] ?? null);
		self::assertSame('Updated instructions', $document['title'] ?? null);
		self::assertSame('Updated text', $document['text_content'] ?? null);
		self::assertSame([$assignmentId], $this->_documentRepository->FindAssignedMonitorContactIds($documentId));
	}

	public function testUploadedFileMetadataCanBeEditedWithoutChangingStorageIdentity(): void
	{
		self::assertInstanceOf(DocumentRepository::class, $this->_documentRepository);
		$documentId = $this->_documentRepository->CreateFileDocumentForMonitor(
			1,
			'Original title',
			null,
			'random-storage.bin',
			'original.pdf',
			'application/pdf',
			1234
		);
		$this->_documentRepository->UpdateFileDocumentMetadata($documentId, 'Readable title', 'Short description');

		$document = $this->_documentRepository->FindByIdForMonitorAndUser($documentId, 1, 1);
		self::assertSame('Readable title', $document['title'] ?? null);
		self::assertSame('Short description', $document['description'] ?? null);
		self::assertSame('random-storage.bin', $document['stored_filename'] ?? null);
		self::assertSame('original.pdf', $document['original_filename'] ?? null);
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
		$this->_connection->exec('CREATE TABLE contacts (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, email_checked_at DATETIME NULL, cell_phone VARCHAR(50) NULL, notes TEXT NULL) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE monitors (id BIGINT UNSIGNED PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, default_message_subject VARCHAR(255) NULL, default_message_body LONGTEXT NULL, updated_at DATETIME NULL) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE monitor_contacts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, monitor_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, sort_order INT NOT NULL, UNIQUE KEY (monitor_id, contact_id), FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE, FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE) ENGINE=InnoDB');
		$this->_connection->exec('CREATE TABLE contact_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, monitor_contact_id BIGINT UNSIGNED NOT NULL UNIQUE, subject VARCHAR(255) NOT NULL, body_text LONGTEXT NOT NULL, FOREIGN KEY (monitor_contact_id) REFERENCES monitor_contacts(id) ON DELETE CASCADE) ENGINE=InnoDB');
		$this->_connection->exec("CREATE TABLE documents (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, monitor_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NULL, storage_type ENUM('text','file') NOT NULL, text_content LONGTEXT NULL, stored_filename VARCHAR(255) NULL, original_filename VARCHAR(255) NULL, mime_type VARCHAR(255) NULL, file_size_bytes BIGINT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE) ENGINE=InnoDB");
		$this->_connection->exec('CREATE TABLE document_monitor_contacts (document_id BIGINT UNSIGNED NOT NULL, monitor_contact_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (document_id, monitor_contact_id), FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE, FOREIGN KEY (monitor_contact_id) REFERENCES monitor_contacts(id) ON DELETE CASCADE) ENGINE=InnoDB');
		$this->_connection->exec('INSERT INTO users (id) VALUES (1)');
		$this->_connection->exec("INSERT INTO contacts (id, user_id, name, email) VALUES (1, 1, 'First', 'first@example.org'), (2, 1, 'Second', 'second@example.org')");
		$this->_connection->exec('INSERT INTO monitors (id, user_id, updated_at) VALUES (1, 1, UTC_TIMESTAMP())');
	}
}
