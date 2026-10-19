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

/** @brief Verify root-only manifest support, selected changelog versions and safe confirmation steps. */
final class UpdatePreflightSourceTest extends TestCase
{
	/** @brief Only the root manifest is acceptable to the 1.4.3 inspector. */
	public function testOnlyRootManifestIsSupported(): void
	{
		self::assertFalse(UpdateInstallerService::AllowedPackageFile('docs/update-manifest.json'));
		$inspector = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Services/UpdatePackageInspector.php');
		self::assertStringContainsString('Legacy docs/update-manifest.json is no longer supported', $inspector);
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
		self::assertStringContainsString('administration/updates/change', $view);
		self::assertStringContainsString('function ChangeUpdatePackage()', $controller);
		self::assertStringNotContainsString('administration.updates.final_question', $view);
	}
}
