<?php
/** @brief Regression coverage for database text previews and owner isolation. */
declare(strict_types=1);
namespace Pulse\Tests\Unit;
use PDO;
use PHPUnit\Framework\TestCase;
use Pulse\Core\Database;
use Pulse\Core\DocumentException;
use Pulse\Repositories\DocumentRepository;
use Pulse\Services\DocumentService;
use Pulse\Services\DocumentPreviewService;
final class OwnedTextPreviewTest extends TestCase
{
	/** @brief Native text needs no file; foreign users and monitors cannot preview it. */
	public function testOwnedTextPreview(): void
	{
		$db = new PDO('sqlite::memory:');
		$db->exec("CREATE TABLE monitors(id INT,user_id INT); INSERT INTO monitors VALUES(1,7); CREATE TABLE documents(id INT,monitor_id INT,title TEXT,description TEXT,storage_type TEXT,text_content TEXT,stored_filename TEXT,original_filename TEXT,mime_type TEXT,file_size_bytes INT,created_at TEXT,updated_at TEXT); INSERT INTO documents VALUES(2,1,'Note','','text','# Hello',NULL,NULL,NULL,0,'','');");
		$database = new class($db) extends Database
		{
			public function __construct(private PDO $connection) {}
			public function GetConnection(): PDO { return $this->connection; }
		};
		$service = (new \ReflectionClass(DocumentService::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(DocumentService::class, '_documentRepository'))->setValue($service, new DocumentRepository($database));
		$result = $service->PreparePreviewForUser(7,1,2);
		self::assertNull($result['path']);
		$preview = (new DocumentPreviewService())->BuildTextFrame($result['document'], $result['path']);
		self::assertSame('# Hello',$preview['content']);
		foreach ([[8,1,2],[7,2,2],[7,1,99]] as $args)
		{
			try
			{
				$service->PreparePreviewForUser(...$args);
				self::fail('Unauthorized or missing document accepted');
			}
			catch (DocumentException $error)
			{
				self::assertSame('monitors.documents.flash.document_not_found',$error->getMessage());
			}
		}
	}
}
