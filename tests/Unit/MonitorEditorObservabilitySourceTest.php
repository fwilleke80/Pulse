<?php

/**
 * @file MonitorEditorObservabilitySourceTest.php
 * @brief Source regressions for the Pulse 1.3.5 monitor editor status cleanup pass.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MonitorEditorObservabilitySourceTest extends TestCase
{
	/** @brief Keeps the monitor tabs stable, unwrapped, and named Basic instead of Details. */
	public function testPrimaryMonitorTabsUseStableRowsAndBasicLabel(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');
		$english = (string)file_get_contents($root . '/app/Lang/en.php');

		self::assertStringContainsString('monitor-tabs monitor-primary-tabs', $view);
		self::assertStringContainsString('.monitor-primary-tabs', $styles);
		self::assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr));', $styles);
		self::assertStringContainsString('white-space: nowrap;', $styles);
		self::assertStringContainsString("'monitors.tabs.details' => 'Basic'", $english);
	}

	/** @brief Keeps Status monitor-specific, compact, and centred on lifecycle events. */
	public function testStatusShowsLastEventNextActionAndTypedHistoryWithoutSystemHealthCards(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringNotContainsString('monitorSystemHealth', $view);
		self::assertStringNotContainsString('monitorSystemHealth', $controller);
		self::assertStringNotContainsString('monitor-health-check-grid', $view);
		self::assertStringNotContainsString('.monitor-health-check-grid', $styles);
		self::assertStringContainsString('monitors.status.last_event', $view);
		self::assertStringContainsString('monitors.status.next_action', $view);
		self::assertStringContainsString('.monitor-status-last-event strong,', $styles);
		self::assertStringContainsString('font-size: 1rem;', $styles);
		self::assertStringContainsString('monitor-event-badge', $view);
		self::assertStringNotContainsString("monitor['last_confirmed_at']", $view);
	}

	/** @brief Keeps Review & activation silent when the configuration is valid. */
	public function testReviewShowsWarningsOnlyAndNoPositiveReadyNote(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$english = (string)file_get_contents($root . '/app/Lang/en.php');

		self::assertStringContainsString('review-warning', $view);
		self::assertStringNotContainsString('review-ready', $view);
		self::assertStringNotContainsString('monitors.review.ready', $view);
		self::assertStringNotContainsString('The core delivery configuration is complete.', $english);
	}

	/** @brief Keeps the document tab compact and routes content editing to its own page. */
	public function testDocumentsUseCompactListAndDedicatedEditor(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$editor = (string)file_get_contents($root . '/app/Views/documents/editor.php');
		$routes = (string)file_get_contents($root . '/public/index.php');

		self::assertStringContainsString('document-library-list', $view);
		self::assertStringContainsString('monitors.documents.list.modified', $view);
		self::assertStringContainsString('monitors.documents.list.text_size', $view);
		self::assertStringContainsString("Get('/monitors/documents/text/new'", $routes);
		self::assertStringContainsString("Get('/monitors/documents/edit'", $routes);
		self::assertStringContainsString('document-editor-form', $editor);
		self::assertStringNotContainsString("markdown_editor(\$base_url, 'text_document_content'", $view);
	}

	/** @brief Removes the old encryption notice and keeps Messages & content visually compact. */
	public function testMessagesNoLongerShowUnencryptedNotice(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringNotContainsString('monitors.storage.warning.heading', $view);
		self::assertStringNotContainsString('monitors.storage.warning.message', $view);
		self::assertStringContainsString('monitor-messages-panel', $view);
		self::assertStringContainsString('.monitor-messages-panel .editor-subtabs', $styles);
	}
}
