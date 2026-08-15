<?php

/**
 * @file BaselineIntegritySourceTest.php
 * @brief Source-level regression checks for the stable schema baseline and destructive-operation guards.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BaselineIntegritySourceTest extends TestCase
{
	/** @brief Ensures the stable baseline is a single current-schema migration. */
	public function testStableSchemaUsesSingleInitialMigration(): void
	{
		$root = dirname(__DIR__, 2);
		$migrations = glob($root . '/database/migrations/*.sql');
		$schema = (string)file_get_contents($root . '/database/migrations/001_initial_schema.sql');

		self::assertIsArray($migrations);
		self::assertCount(1, $migrations);
		self::assertStringEndsWith('/001_initial_schema.sql', str_replace('\\', '/', $migrations[0]));
		self::assertStringContainsString('CREATE TABLE recipient_release_deliveries', $schema);
		self::assertStringContainsString('is_archived TINYINT(1) NOT NULL DEFAULT 0', $schema);
		self::assertStringContainsString('CREATE TABLE system_status', $schema);
		self::assertStringNotContainsString('CREATE TABLE access_tokens', $schema);
		self::assertStringNotContainsString('CREATE TABLE app_settings', $schema);
	}

	/** @brief Ensures contact deletion cannot silently mutate monitor assignments. */
	public function testContactDeletionIsGuardedByApplicationAndSchema(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/ContactController.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/ContactRepository.php');
		$schema = (string)file_get_contents($root . '/database/migrations/001_initial_schema.sql');

		self::assertStringContainsString('IsReferencedByMonitorForUser', $controller);
		self::assertStringContainsString('IsReferencedByMonitorForUser', $repository);
		self::assertSame(2, substr_count($schema, 'FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE RESTRICT'));
	}

	/** @brief Ensures released delivery history prevents monitor deletion. */
	public function testMonitorDeletionPreservesRecipientReleaseHistory(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$service = (string)file_get_contents($root . '/app/Services/DocumentService.php');
		$schema = (string)file_get_contents($root . '/database/migrations/001_initial_schema.sql');

		self::assertStringContainsString('HasReleaseHistoryForUser', $controller);
		self::assertStringContainsString('HasReleaseHistoryForUser', $service);
		self::assertGreaterThanOrEqual(2, substr_count($schema, 'FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE RESTRICT'));
		self::assertStringNotContainsString('FindRecipientDeliveryStoredFilenamesForMonitor', $service);
	}
}
