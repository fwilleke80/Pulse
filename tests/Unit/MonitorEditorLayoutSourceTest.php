<?php

/**
 * @file MonitorEditorLayoutSourceTest.php
 * @brief Source-level regression checks for monitor editor layout.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MonitorEditorLayoutSourceTest extends TestCase
{
	/** @brief Ensures archived monitor editors do not render save controls. */
	public function testArchivedMonitorDoesNotRenderStickySaveBar(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');

		self::assertStringContainsString('<?php if (!$isArchived): ?>', $view);
		self::assertStringContainsString('class="editor-save-bar"', $view);
		self::assertStringContainsString('data-settings-tabs="details,schedule,escalation,messages,review"', $view);
	}

	/** @brief Ensures recipient overview metadata participates directly in the full-width card grid. */
	public function testRecipientOverviewUsesFullWidthGrid(): void
	{
		$root = dirname(__DIR__, 2);
		$style = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('grid-template-columns: minmax(220px, 1.25fr) minmax(0, 2fr);', $style);
		self::assertStringContainsString('.recipient-overview-documents', $style);
		self::assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) max-content;', $style);
	}
	/** @brief Ensures Messages & content participates in the shared Save changes flow. */
	public function testMessagesUseSharedSaveChangesFlow(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$script = (string)file_get_contents($root . '/public/assets/app.js');

		self::assertStringContainsString('data-monitor-messages-form', $view);
		self::assertStringContainsString('<noscript><button type="submit"', $view);
		self::assertStringContainsString('dirtyMessageSections', $script);
		self::assertStringContainsString("data.set('async_save', '1')", $script);
		self::assertStringContainsString('monitorSettingsForm.submit()', $script);
		self::assertStringContainsString('MessageSaveJson(false, false, $validationError)', (string)file_get_contents($root . '/app/Controllers/MonitorController.php'));
	}

}
