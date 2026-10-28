<?php
/** @file TemplateDiagnosticsTest.php @brief Draft diagnostics and sending-locale regressions. */
declare(strict_types=1);
namespace Pulse\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Pulse\Services\TemplateDiagnostics;
use Pulse\Services\NotificationScheduler;
use Pulse\Services\NotificationComposer;
use Pulse\Services\EscalationService;
use Pulse\Core\NotificationLanguage;
use PDO;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
final class TemplateDiagnosticsTest extends TestCase
{
	/** @brief Empty pairs are fallback, incomplete pairs retain precise field errors. */
	public function testFieldRulesAndOwnerSendGate(): void
	{
		self::assertSame([], TemplateDiagnostics::Fields('', '', true));
		self::assertSame(['subject'=>'subject_missing'], TemplateDiagnostics::Fields('', 'Open {url}', true));
		self::assertSame(['body'=>'body_missing'], TemplateDiagnostics::Fields('Subject', '', true));
		self::assertSame(['body'=>'url_missing','subject'=>'url_subject'], TemplateDiagnostics::Fields('{url}', 'No link', true));
		self::assertSame([], TemplateDiagnostics::Fields('Subject', 'Owner text', false));
		$scheduler = (new ReflectionClass(NotificationScheduler::class))->newInstanceWithoutConstructor();
		$check = new ReflectionMethod(NotificationScheduler::class, 'OwnerTemplateValid');
		self::assertFalse($check->invoke($scheduler, ['mail_templates'=>['owner_due_notice'=>['owner'=>['subject'=>'','body_text'=>'draft']]]], 'owner_due_notice'));
		self::assertTrue($check->invoke($scheduler, ['mail_templates'=>[]], 'owner_due_notice'));
	}
	/** @brief Blank contact languages use the same deployment fallback for safety and recipient templates. */
	public function testSendingUsesResolvedLanguageAndKeepsPersonalOverrides(): void
	{
		$db = new PDO('sqlite::memory:');
		$db->exec("CREATE TABLE monitor_mail_templates (monitor_id INT, template_key TEXT, locale TEXT, subject TEXT, body_text TEXT); INSERT INTO monitor_mail_templates VALUES (1,'recipient_default','de','','Deutsch {url}'),(1,'safety_invitation','de','Sicherheit','{url}');");
		$composer = new NotificationComposer(new NotificationLanguage(['en','de'], 'de'), dirname(__DIR__,2).'/app/Lang', ['name'=>'Pulse','base_url'=>'https://example.test','display_timezone'=>'UTC']);
		$service = (new ReflectionClass(EscalationService::class))->newInstanceWithoutConstructor();
		(new ReflectionProperty(EscalationService::class, '_composer'))->setValue($service, $composer);
		$resolve = new ReflectionMethod(EscalationService::class, 'ResolveContactTemplateLocales');
		$rows = $resolve->invoke($service, $db, [['notification_locale'=>null,'personal_message_id'=>null],['notification_locale'=>null,'personal_message_id'=>2,'message_subject'=>'Personal','message_body'=>'Personal {url}']], 1, false);
		self::assertSame('de',$rows[0]['notification_locale']);
		self::assertSame('', $rows[0]['message_subject']);
		self::assertSame('Deutsch {url}', $rows[0]['message_body']);
		self::assertSame('Personal',$rows[1]['message_subject']);
		$rows = $resolve->invoke($service, $db, [['notification_locale'=>null]], 1, true);
		self::assertSame('Sicherheit',$rows[0]['safety_invitation_subject']);
		$db->exec('DELETE FROM monitor_mail_templates');
		$rows = $resolve->invoke($service, $db, [['notification_locale'=>'unsupported','message_subject'=>'Old language','message_body'=>'Old {url}']], 1, false);
		self::assertNull($rows[0]['message_subject']);
		self::assertNull($rows[0]['message_body']);
	}
	/** @brief Every mail family persists incomplete drafts and removes only a deliberately empty pair. */
	public function testDraftPersistence(): void
	{
		$db = new class('sqlite::memory:') extends PDO
		{
			/** @brief SQLite adapter retains transactions and removes MySQL row-lock syntax. */
			public function prepare(string $query, array $options = []): \PDOStatement|false
			{
				return parent::prepare(str_replace('FOR UPDATE', '', $query), $options);
			}
		};
		$db->exec('CREATE TABLE monitors(id INT,user_id INT); INSERT INTO monitors VALUES(1,1); CREATE TABLE monitor_mail_templates(monitor_id INT,template_key TEXT,locale TEXT,subject TEXT,body_text TEXT);');
		$database = new class($db) extends \Pulse\Core\Database
		{
			/** @brief Supplies the fixture connection. */
			public function __construct(private PDO $connection)
			{
			}
			/** @brief Returns the fixture. */
			public function GetConnection(): PDO
			{
				return $this->connection;
			}
		};
		$repository = new \Pulse\Repositories\MessageRepository($database);
		foreach (['owner_due_notice','owner_reminder','recipient_default','safety_invitation','safety_reminder'] as $key)
		{
			$repository->ReplaceLocalizedTemplatesForMonitor(1,1,$key,['de'=>['subject'=>'','body_text'=>'Saved draft']]);
			$query = $db->prepare('SELECT subject,body_text FROM monitor_mail_templates WHERE template_key = ?');
			$query->execute([$key]);
			$row = $query->fetch(PDO::FETCH_ASSOC);
			self::assertSame('', $row['subject']);
			self::assertSame('Saved draft', $row['body_text']);
		}
		$repository->ReplaceLocalizedTemplatesForMonitor(1,1,'recipient_default',['de'=>['subject'=>'','body_text'=>'']]);
		self::assertSame(4,(int)$db->query('SELECT COUNT(*) FROM monitor_mail_templates')->fetchColumn());
	}

}
