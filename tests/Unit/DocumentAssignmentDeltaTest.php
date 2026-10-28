<?php
/** @brief Executes assignment deltas against SQL, including ownership and rollback. */
declare(strict_types=1);
namespace Pulse\Tests\Unit;
use PDO;
use PDOStatement;
use Pulse\Core\Database;
use Pulse\Repositories\RecipientRepository;
use PHPUnit\Framework\TestCase;
final class AssignmentSql extends PDO
{
	/** @brief Removes MySQL locking syntax for SQLite validation. */
	public function prepare(string $query, array $options = []): PDOStatement|false
	{
		return parent::prepare(str_replace('FOR UPDATE', '', $query), $options);
	}
}
final class AssignmentDatabase extends Database
{
	/** @brief Supplies the fixture connection. */
	public function __construct(private PDO $connection)
	{
	}
	/** @brief Returns the connection. */
	public function GetConnection(): PDO
	{
		return $this->connection;
	}
}
final class DocumentAssignmentDeltaTest extends TestCase
{
	/** @brief Ensures deltas are additive, repeatable, scoped and atomic. */
	public function testDeltas(): void
	{
		$db = new AssignmentSql('sqlite::memory:');
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$db->exec('CREATE TABLE monitors(id INT, user_id INT, is_archived INT); CREATE TABLE monitor_contacts(id INT, monitor_id INT); CREATE TABLE documents(id INT, monitor_id INT); CREATE TABLE document_monitor_contacts(document_id INT, monitor_contact_id INT, PRIMARY KEY(document_id, monitor_contact_id)); INSERT INTO monitors VALUES(1,1,0),(2,2,0),(3,1,1); INSERT INTO monitor_contacts VALUES(1,1),(2,2),(3,3); INSERT INTO documents VALUES(1,1),(2,1),(3,2),(4,3); INSERT INTO document_monitor_contacts VALUES(1,1);');
		$repository = new RecipientRepository(new AssignmentDatabase($db));
		$repository->ChangeDocumentsForUser(1, 1, [2, 2], true);
		$repository->ChangeDocumentsForUser(1, 1, [2], true);
		self::assertSame(2, (int)$db->query('SELECT COUNT(*) FROM document_monitor_contacts')->fetchColumn());
		$repository->ChangeDocumentsForUser(1, 1, [1], false);
		self::assertSame(2, (int)$db->query('SELECT document_id FROM document_monitor_contacts')->fetchColumn());
		foreach ([[1,1,[1,3]], [2,1,[3]], [3,1,[4]]] as [$recipient, $user, $ids])
		{
			try
			{
				$repository->ChangeDocumentsForUser($recipient, $user, $ids, true);
				self::fail('Invalid assignment was accepted');
			}
			catch (\RuntimeException)
			{
				self::assertSame(1, (int)$db->query('SELECT COUNT(*) FROM document_monitor_contacts')->fetchColumn());
			}
		}
		self::assertSame(4, (int)$db->query('SELECT COUNT(*) FROM documents')->fetchColumn());
	}
}
