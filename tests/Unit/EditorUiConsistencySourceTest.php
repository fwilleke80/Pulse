<?php

/**
 * @file EditorUiConsistencySourceTest.php
 * @brief Guards shared responsive editor tabs and dashboard monitor actions.
 * @author Frank Willeke
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EditorUiConsistencySourceTest extends TestCase
{
	/** @brief Ensures editor tabs no longer use the old horizontal-scroll mobile treatment. */
	public function testEditorTabsUseWrappedResponsiveGrid(): void
	{
		$css = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');
		$this->assertStringContainsString('grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));', $css);
		$this->assertStringNotContainsString(".monitor-tabs\n\t{\n\t\tdisplay: flex;\n\t\toverflow-x: auto;", $css);
	}

	/** @brief Ensures nested recipient navigation reuses the main tab component. */
	public function testRecipientEditorUsesStyledTabComponent(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/recipients/edit.php');
		$this->assertStringContainsString('monitor-tabs editor-subtab-list', $view);
		$this->assertStringContainsString('monitor-tab-link editor-subtab-link', $view);
	}

	/** @brief Ensures Dashboard and Monitors page share the same action renderer. */
	public function testDashboardAndMonitorListShareActions(): void
	{
		$dashboard = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/home/dashboard.php');
		$index = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/index.php');
		$partial = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/partials/actions.php');

		$this->assertStringContainsString("monitors/partials/actions.php", $dashboard);
		$this->assertStringContainsString("partials/actions.php", $index);
		$this->assertStringContainsString('/monitors/force-due', $partial);
	}
	/** @brief Ensures the recipient editor distinguishes raw default templates from rendered previews. */
	public function testRecipientEditorShowsRawDefaultTemplateSeparatelyFromPreview(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/RecipientController.php');
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/recipients/edit.php');

		$this->assertStringContainsString("BuiltInTemplate('recipient_default'", $controller);
		$this->assertStringContainsString('$defaultTemplate[\'body_text\']', $view);
		$this->assertStringContainsString('$preview[\'body_text\']', $view);
	}

	/** @brief Ensures the inherited recipient notification template uses the shared disclosure pattern. */
	public function testRecipientDefaultNotificationTemplateIsCollapsible(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/recipients/edit.php');
		$notificationSection = strstr($view, 'data-subtab-panel="notification"');
		$this->assertNotFalse($notificationSection);
		$this->assertStringContainsString('<details class="mail-default-disclosure">', (string)$notificationSection);
		$this->assertStringContainsString("e__('recipients.message.default_preview.heading')", (string)$notificationSection);
	}

	/** @brief Ensures safety-contact placeholder help follows each message body field. */
	public function testSafetyPlaceholderHelpAppearsBelowBodyFields(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$invitationBody = strpos($view, 'name="safety_invitation_body_');
		$reminderBody = strpos($view, 'name="safety_reminder_body_');
		$this->assertNotFalse($invitationBody);
		$this->assertNotFalse($reminderBody);

		$invitationHelp = strpos($view, 'class="form-hint placeholder-help"', (int)$invitationBody);
		$reminderHelp = strpos($view, 'class="form-hint placeholder-help"', (int)$reminderBody);
		$this->assertNotFalse($invitationHelp);
		$this->assertNotFalse($reminderHelp);
		$this->assertGreaterThan($invitationBody, $invitationHelp);
		$this->assertGreaterThan($reminderBody, $reminderHelp);
	}

	/** @brief Ensures monitor-wide portal content contains only the page introduction. */
	public function testMonitorPortalContentContainsOnlyPageIntroduction(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$portalSection = strstr($view, 'data-subtab-panel="portal"');
		$this->assertNotFalse($portalSection);
		$this->assertStringNotContainsString('id="portal_message_', (string)$portalSection);
		$this->assertStringNotContainsString('$portalDefault[\'message_text\']', (string)$portalSection);
		$this->assertStringNotContainsString("PostString('portal_message_'", $controller);
		$this->assertStringContainsString('id="portal_intro_', (string)$portalSection);
		$this->assertStringContainsString('$portalBuiltIn[\'intro_text\']', (string)$portalSection);
	}

}
