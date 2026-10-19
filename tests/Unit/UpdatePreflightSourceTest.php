<?php

/**
 * @file UpdatePreflightSourceTest.php
 * @brief Regression tests for the Pulse 1.4.1 staged update and restore flows.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Services\UpdateInstallerService;
use Pulse\Services\UpdatePackageInspector;

/** @brief Verify manifest compatibility, selected changelog versions, and confirmation steps. */
final class UpdatePreflightSourceTest extends TestCase
{
	/** @brief Both manifest paths are allowed during the transition to a root manifest. */
	public function testManifestUsesLegacyCompatiblePath(): void
	{
		self::assertTrue(UpdateInstallerService::AllowedPackageFile('docs/update-manifest.json'));
		self::assertTrue(UpdateInstallerService::AllowedPackageFile('manifest.json'));
		self::assertFalse(UpdateInstallerService::AllowedPackageFile('storage/uploads/manifest.json'));
	}

	/** @brief Changelog rendering must exclude already-installed and future versions. */
	public function testReleaseNotesUseOnlyVersionInterval(): void
	{
		$notes = "## 1.4.4 - future\n\nFuture\n\n## 1.4.3 - included\n\nThree\n\n## 1.4.2 - included\n\nTwo\n\n## 1.4.1 - installed\n\nOne\n";
		$selected = UpdatePackageInspector::ReleaseNotes($notes, '1.4.1', '1.4.3');
		self::assertStringContainsString('Three', $selected);
		self::assertStringContainsString('Two', $selected);
		self::assertStringNotContainsString('Future', $selected);
		self::assertStringNotContainsString('One', $selected);
	}

	/** @brief UI uses preflight, not an immediately destructive upload form. */
	public function testAdministrationExposesTwoStageMaintenanceForms(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/administration/index.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/MaintenanceController.php');
		self::assertStringContainsString('administration/updates/preflight', $view);
		self::assertStringContainsString('administration/backup/preflight', $view);
		self::assertStringContainsString('name="confirm_restore"', $view);
		self::assertStringNotContainsString('name="trust_update"', $view);
		self::assertStringNotContainsString('name="update_confirmation"', $view);
		self::assertStringNotContainsString('name="restore_confirmation"', $view);
		self::assertStringContainsString('VerifyFreshPassword', $controller);
		self::assertStringContainsString('PendingArchive', $controller);
	}
}
