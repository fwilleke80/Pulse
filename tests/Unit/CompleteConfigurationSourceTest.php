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

	public function testEditorContainsAllFourSections(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');

		foreach (['schedule', 'recipients', 'messages', 'review'] as $tab)
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

	public function testUncheckedRecipientsHaveAVisibleCheckAction(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');

		self::assertStringContainsString('return_monitor_id=', $view);
		self::assertStringContainsString("e__('monitors.contacts.check_address')", $view);
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
}
