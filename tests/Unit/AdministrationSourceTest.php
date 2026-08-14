<?php

/**
 * @file AdministrationSourceTest.php
 * @brief Static regression checks for Pulse 0.9.x Administration.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdministrationSourceTest extends TestCase
{
	/** @brief Ensures mail configuration and operations have moved completely out of Profile. */
	public function testMailOperationsLiveOnlyInAdministration(): void
	{
		$root = dirname(__DIR__, 2);
		$profile = (string)file_get_contents($root . '/app/Views/profile/index.php');
		$routes = (string)file_get_contents($root . '/public/index.php');
		$administration = (string)file_get_contents($root . '/app/Views/administration/index.php');

		self::assertStringNotContainsString('id="notifications"', $profile);
		self::assertStringNotContainsString('/profile/notifications/', $routes);
		self::assertStringContainsString('/administration/mail/test', $routes);
		self::assertStringContainsString('/administration/mail/retry', $routes);
		self::assertStringContainsString('/administration/mail/clear', $routes);
		self::assertStringContainsString('id="mail-queue"', $administration);
	}

	/** @brief Ensures Administration is protected by a real role check and existing users migrate to administrator. */
	public function testAdministratorRoleIsEnforcedAndMigrated(): void
	{
		$root = dirname(__DIR__, 2);
		$baseController = (string)file_get_contents($root . '/app/Controllers/BaseController.php');
		$administrationController = (string)file_get_contents($root . '/app/Controllers/AdministrationController.php');
		$migration = (string)file_get_contents($root . '/database/migrations/018_administrator_role.sql');

		self::assertStringContainsString("!== 'administrator'", $baseController);
		self::assertGreaterThanOrEqual(4, substr_count($administrationController, 'RequireAdministrator()'));
		self::assertStringContainsString("role ENUM('user','administrator')", $migration);
		self::assertStringContainsString("SET role = 'administrator'", $migration);
	}

	/** @brief Ensures the admin editor reuses responsive tabs and warning indicators. */
	public function testAdministrationUsesResponsiveTabsAndConfigurationWarnings(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/administration/index.php');

		self::assertStringContainsString('data-monitor-tabs', $view);
		self::assertStringContainsString('class="monitor-tabs"', $view);
		self::assertStringContainsString('tab-warning-indicator', $view);
		self::assertStringContainsString('administration-health-summary', $view);
	}

	/** @brief Ensures secrets are never rendered and database bootstrap credentials remain read-only. */
	public function testSecretsAndDatabaseCredentialsAreNotRenderedIntoEditableValues(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/administration/index.php');

		self::assertStringContainsString('name="PULSE_SMTP_PASSWORD"', $view);
		self::assertStringContainsString('name="PULSE_CRON_TOKEN"', $view);
		self::assertStringNotContainsString('value="<?= e($settings[\'PULSE_SMTP_PASSWORD\'])', $view);
		self::assertStringNotContainsString('value="<?= e($settings[\'PULSE_CRON_TOKEN\'])', $view);
		self::assertStringNotContainsString('name="PULSE_DB_PASSWORD"', $view);
		self::assertStringNotContainsString('name="PULSE_DB_USERNAME"', $view);
	}
	/** @brief Ensures installation identity and bootstrap URL cannot be casually changed from Administration. */
	public function testApplicationIdentityIsFixedAndBaseUrlIsReadOnly(): void
	{
		$root = dirname(__DIR__, 2);
		$config = (string)file_get_contents($root . '/config/app.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/AdministrationController.php');
		$view = (string)file_get_contents($root . '/app/Views/administration/index.php');
		$example = (string)file_get_contents($root . '/.env.example');

		self::assertStringContainsString("'name' => 'Pulse'", $config);
		self::assertStringNotContainsString('PULSE_APP_NAME', $config);
		self::assertStringNotContainsString('PULSE_APP_NAME', $controller);
		self::assertStringNotContainsString('PULSE_APP_NAME', $example);
		self::assertStringNotContainsString('name="PULSE_BASE_URL"', $view);
		self::assertStringContainsString('e($settings[\'PULSE_BASE_URL\'])', $view);
	}

	/** @brief Ensures the settings UI uses standard timezone choices and purpose-oriented help instead of env-key captions. */
	public function testAdministrationUsesTimezoneSelectorAndHumanHelp(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/AdministrationController.php');
		$view = (string)file_get_contents($root . '/app/Views/administration/index.php');

		self::assertStringContainsString('DateTimeZone::listIdentifiers()', $controller);
		self::assertStringContainsString('name="PULSE_DISPLAY_TIMEZONE"', $view);
		self::assertStringContainsString('$availableTimezones as $timezoneOption', $view);
		self::assertStringNotContainsString('<small><code>PULSE_', $view);
		self::assertStringContainsString('administration.field.session_idle_hint', $view);
		self::assertStringContainsString('administration.field.smtp_host_hint', $view);
	}

}
