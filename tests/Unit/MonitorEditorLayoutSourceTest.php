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
		self::assertStringContainsString('data-settings-tabs="details,schedule,escalation,review"', $view);
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
}
