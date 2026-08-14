<?php

/**
 * @file CompleteConfigurationSourceTest.php
 * @brief Static regressions for the complete-configuration editor and document upload message.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CompleteConfigurationSourceTest extends TestCase
{
	public function testUploadSuccessUsesTheOriginalFilename(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/DocumentController.php');
		self::assertStringContainsString("['name' => \$originalFilename]", $controller);
		self::assertStringNotContainsString("['name' => \$this->_request->PostString('title'", $controller);
	}

	public function testEditorContainsAllFiveSections(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');

		foreach (['schedule', 'recipients', 'messages', 'escalation', 'review'] as $tab)
		{
			self::assertStringContainsString('data-tab-panel="' . $tab . '"', $view);
		}
	}

	public function testTabsHaveServerRenderedFallbacks(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$script = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');

		self::assertStringContainsString('class="monitor-tab-link', $view);
		self::assertStringContainsString("' hidden'", $view);
		self::assertStringContainsString('panel.hidden = !active;', $script);
	}

	public function testRecipientsHaveDedicatedConfigurationPages(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$recipientView = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/recipients/edit.php');

		self::assertStringContainsString('/monitors/recipients/edit?id=', $view);
		self::assertStringContainsString('data-message-override', $recipientView);
		self::assertStringContainsString('assignedDocumentIds', $recipientView);
		self::assertStringContainsString('deliveryHistory', $recipientView);
	}

	public function testMonitorTitlesLinkToTheEditorWithoutAnEditButton(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/index.php');
		$editTranslationKey = 'monitors.index.table.buttons.' . 'edit';

		self::assertStringContainsString('/monitors/edit?id=', $view);
		self::assertStringNotContainsString($editTranslationKey, $view);
	}

	public function testMonitorsPrecedeContactsInTheMainNavigation(): void
	{
		$layout = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/layouts/main.php');
		$monitorsPosition = strpos($layout, "e__('nav.monitors')");
		$contactsPosition = strpos($layout, "e__('nav.contacts')");

		self::assertNotFalse($monitorsPosition);
		self::assertNotFalse($contactsPosition);
		self::assertLessThan($contactsPosition, $monitorsPosition);
	}

	public function testLanguageSwitcherPreservesTheCompleteRequestTarget(): void
	{
		$bootstrap = (string)file_get_contents(dirname(__DIR__, 2) . '/bootstrap.php');
		$layout = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/layouts/main.php');

		self::assertStringContainsString("'currentTarget' => \$request->Target()", $bootstrap);
		self::assertStringContainsString('name="redirect" value="<?= e($currentTarget) ?>"', $layout);
		self::assertStringNotContainsString('name="redirect" value="<?= e($currentPath) ?>"', $layout);
	}

	public function testCancelButtonIsBesideTheMonitorSettingsSubmitButton(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$backTranslationKey = 'monitors.edit.' . 'back';

		self::assertStringContainsString('class="editor-save-actions"', $view);
		self::assertStringContainsString('class="button-link editor-cancel-button"', $view);
		self::assertStringContainsString("e__('monitors.edit.cancel')", $view);
		self::assertStringNotContainsString($backTranslationKey, $view);
	}

	public function testSavingMonitorSettingsPreservesTheActiveTab(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/MonitorController.php');
		$script = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');

		self::assertStringContainsString('data-active-tab-input', $view);
		self::assertStringContainsString("PostString('active_tab'", $controller);
		self::assertStringContainsString("'&tab=' . \$returnTab", $controller);
		self::assertStringContainsString('activeTabInput.value = name', $script);
	}

	public function testMissingGeneratedVersionHasAnExplicitNonFatalFallback(): void
	{
		$bootstrap = (string)file_get_contents(dirname(__DIR__, 2) . '/bootstrap.php');
		$layout = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/layouts/main.php');

		self::assertStringContainsString('$appVersion = \'\';', $bootstrap);
		self::assertStringContainsString('if (is_file($versionFile))', $bootstrap);
		self::assertStringContainsString("__('footer.version_unavailable')", $layout);
		self::assertStringContainsString("'unversioned'", $layout);
	}

	public function testDeploymentDocumentationRequiresTheVersionGeneratorBeforeUpload(): void
	{
		$readme = (string)file_get_contents(dirname(__DIR__, 2) . '/README.md');
		$upgrade = (string)file_get_contents(dirname(__DIR__, 2) . '/docs/INSTALLATION.md');

		self::assertStringContainsString('python3 tools/write_version.py', $readme);
		self::assertStringContainsString('before uploading', strtolower($readme));
		self::assertStringContainsString('config/version.php', $readme);
		self::assertStringContainsString('python3 tools/write_version.py', $upgrade);
		self::assertStringContainsString('before uploading', strtolower($upgrade));
		self::assertStringContainsString('config/version.php', $upgrade);
	}
}
