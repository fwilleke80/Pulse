<?php

/**
 * @file InstallationSourceTest.php
 * @brief Static regression checks for the Pulse browser installer.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InstallationSourceTest extends TestCase
{
	/** @brief Ensures normal application and web-cron entry points stay locked until the installer is removed. */
	public function testInstallerGatesApplicationAndWebCron(): void
	{
		$root = dirname(__DIR__, 2);
		$index = (string)file_get_contents($root . '/public/index.php');
		$cron = (string)file_get_contents($root . '/public/cron/cron.php');
		$cli = (string)file_get_contents($root . '/tools/pulse.php');

		self::assertStringContainsString("is_file(\$installerPath)", $index);
		self::assertStringContainsString("header('Location: /install.php')", $index);
		self::assertStringContainsString("is_file(dirname(__DIR__) . '/install.php')", $cron);
		self::assertStringContainsString('Pulse installation is not finalized.', $cron);
		self::assertStringContainsString("'/public/install.php'", $cli);
		self::assertStringContainsString('Pulse installation is not finalized.', $cli);
	}

	/** @brief Ensures the installer exposes the complete six-stage workflow. */
	public function testInstallerContainsAllWizardStages(): void
	{
		$root = dirname(__DIR__, 2);
		$installer = (string)file_get_contents($root . '/public/install.php');

		foreach (['system', 'database', 'application', 'administrator', 'mail', 'finish'] as $stage)
		{
			self::assertStringContainsString("'" . $stage . "'", $installer);
		}

		self::assertStringContainsString('First administrator', $installer);
		self::assertStringContainsString('Skip for now', $installer);
		self::assertStringContainsString('Installation complete', $installer);
		self::assertStringContainsString('Invalid or out-of-order installation action.', $installer);
	}

	/** @brief Ensures the application step prefers the current request URL over the reference-template placeholder. */
	public function testInstallerSuggestsCurrentPublicBaseUrl(): void
	{
		$root = dirname(__DIR__, 2);
		$installer = (string)file_get_contents($root . '/public/install.php');

		self::assertStringContainsString('function suggested_public_base_url(): string', $installer);
		self::assertStringContainsString("HTTP_X_FORWARDED_PROTO", $installer);
		self::assertStringContainsString("['https://pulse.example.com', 'http://pulse.example.com']", $installer);
		self::assertStringContainsString('installer_base_url_default($installer)', $installer);
		self::assertStringContainsString('Detected from the address used to open this installer.', $installer);
	}

	/** @brief Ensures setup uses migrations, a real administrator role, strong hashing, and a generated cron token. */
	public function testInstallerBuildsARealPulseInstallation(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Installation/InstallationService.php');

		self::assertStringContainsString('new MigrationRunner(', $service);
		self::assertStringContainsString("'administrator'", $service);
		self::assertStringContainsString('password_hash($password, PASSWORD_DEFAULT)', $service);
		self::assertStringContainsString('bin2hex(random_bytes(32))', $service);
		self::assertGreaterThanOrEqual(6, substr_count($service, 'RequireStage('));
		self::assertStringContainsString("PULSE_MAIL_ENABLED' => 'false'", $service);
	}

	/** @brief Ensures completion reuses runtime validation and removes the browser installer only afterward. */
	public function testFinalizationUsesRuntimeValidationAndSelfRemoval(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Installation/InstallationService.php');
		$installer = (string)file_get_contents($root . '/public/install.php');

		self::assertStringContainsString('ConfigurationValidator::Validate(', $service);
		self::assertStringContainsString("role = 'administrator' AND is_active = 1", $service);
		self::assertStringContainsString('@unlink($this->_installerPath)', $service);
		self::assertStringContainsString('$installer->VerifyInstallation()', $installer);
		self::assertStringContainsString('$installer->RemoveInstaller()', $installer);
	}

	/** @brief Ensures the resumable state marker contains workflow state, not submitted credentials or passwords. */
	public function testInstallationStateContainsNoSecrets(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Installation/InstallationService.php');
		$beginStart = strpos($service, 'public function Begin(): void');
		$beginEnd = strpos($service, 'public function ClearState(): void');

		self::assertIsInt($beginStart);
		self::assertIsInt($beginEnd);
		$begin = substr($service, $beginStart, $beginEnd - $beginStart);
		self::assertStringContainsString("'database_ready' => false", $begin);
		self::assertStringContainsString("'administrator_ready' => false", $begin);
		self::assertStringNotContainsString('PASSWORD', $begin);
		self::assertStringNotContainsString('email', $begin);
		self::assertStringNotContainsString('username', $begin);
	}
}
