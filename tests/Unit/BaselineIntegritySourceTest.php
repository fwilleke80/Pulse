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
	/** @brief Ensures the immutable 1.0 baseline is retained and later schema changes use a follow-up migration. */
	public function testStableSchemaRetainsInitialMigrationAndAddsSecurityMigration(): void
	{
		$root = dirname(__DIR__, 2);
		$migrations = glob($root . '/database/migrations/*.sql');
		$schema = (string)file_get_contents($root . '/database/migrations/001_initial_schema.sql');
		$securityMigration = (string)file_get_contents($root . '/database/migrations/002_security_methods_and_owner_mail.sql');
		$locationMigration = (string)file_get_contents($root . '/database/migrations/003_check_in_locations.sql');
		$emailMigration = (string)file_get_contents($root . '/database/migrations/004_multiple_email_addresses.sql');
		$portalMessageMigration = (string)file_get_contents($root . '/database/migrations/005_recipient_portal_message_state.sql');
		$totpMigration = (string)file_get_contents($root . '/database/migrations/006_totp_two_factor_authentication.sql');
		$cronDiagnosticsMigration = (string)file_get_contents($root . '/database/migrations/007_cron_diagnostics.sql');

		self::assertIsArray($migrations);
		sort($migrations);
		self::assertCount(7, $migrations);
		self::assertStringEndsWith('/001_initial_schema.sql', str_replace('\\', '/', $migrations[0]));
		self::assertStringEndsWith('/002_security_methods_and_owner_mail.sql', str_replace('\\', '/', $migrations[1]));
		self::assertStringEndsWith('/003_check_in_locations.sql', str_replace('\\', '/', $migrations[2]));
		self::assertStringEndsWith('/004_multiple_email_addresses.sql', str_replace('\\', '/', $migrations[3]));
		self::assertStringEndsWith('/005_recipient_portal_message_state.sql', str_replace('\\', '/', $migrations[4]));
		self::assertStringEndsWith('/006_totp_two_factor_authentication.sql', str_replace('\\', '/', $migrations[5]));
		self::assertStringEndsWith('/007_cron_diagnostics.sql', str_replace('\\', '/', $migrations[6]));
		self::assertStringContainsString('CREATE TABLE recipient_release_deliveries', $schema);
		self::assertStringContainsString('is_archived TINYINT(1) NOT NULL DEFAULT 0', $schema);
		self::assertStringContainsString('CREATE TABLE system_status', $schema);
		self::assertStringNotContainsString('CREATE TABLE access_tokens', $schema);
		self::assertStringNotContainsString('CREATE TABLE app_settings', $schema);
		self::assertStringContainsString('CREATE TABLE user_security_methods', $securityMigration);
		self::assertStringContainsString('CREATE TABLE user_passkey_credentials', $securityMigration);
		self::assertStringContainsString('CREATE TABLE quick_checkin_tokens', $securityMigration);
		self::assertStringContainsString('CREATE TABLE check_in_locations', $locationMigration);
		self::assertStringContainsString('email_4_checked_at', $emailMigration);
		self::assertStringContainsString('ADD COLUMN is_enabled', $portalMessageMigration);
		self::assertStringContainsString('CREATE TABLE user_totp_credentials', $totpMigration);
		self::assertStringContainsString('CREATE TABLE user_totp_recovery_codes', $totpMigration);
		self::assertStringContainsString('CREATE TABLE recipient_release_locations', $locationMigration);
		self::assertStringContainsString('CREATE TABLE cron_failures', $cronDiagnosticsMigration);
		self::assertStringContainsString('cron_token_changed_at', $cronDiagnosticsMigration);
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
