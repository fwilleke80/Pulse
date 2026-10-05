<?php

/**
 * @file PortalInlineDocumentsSourceTest.php
 * @brief Source guards for inline-first portal documents and per-document dirty indicators.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PortalInlineDocumentsSourceTest extends TestCase
{
	/** @brief Ensures portal media and framed readers retain explicit downloads and lazy expansion. */
	public function testPortalUsesInlineMediaAndOnDemandFrames(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/portal/access.php');
		$javascript = (string)file_get_contents($root . '/public/assets/app.js');

		self::assertStringContainsString('<audio controls preload="metadata"', $view);
		self::assertStringContainsString('<video controls preload="metadata" playsinline', $view);
		self::assertStringContainsString('data-preview-src=', $view);
		self::assertStringContainsString('data-document-preview-toggle', $view);
		self::assertStringContainsString('portal.documents.download', $view);
		self::assertStringContainsString("frame.src = frame.dataset.previewSrc", $javascript);
		self::assertStringNotContainsString('docs.google.com', $view . $javascript);
		self::assertStringNotContainsString('view.officeapps.live.com', $view . $javascript);
	}

	/** @brief Ensures private preview responses remain authorized, isolated, and range-capable. */
	public function testPreviewEndpointsUseAuthorizedPrivateStreaming(): void
	{
		$root = dirname(__DIR__, 2);
		$portalController = (string)file_get_contents($root . '/app/Controllers/RecipientPortalController.php');
		$ownerController = (string)file_get_contents($root . '/app/Controllers/RecipientController.php');
		$streamer = (string)file_get_contents($root . '/app/Core/PrivateFileStreamer.php');
		$headers = (string)file_get_contents($root . '/app/Core/SecurityHeaders.php');

		self::assertStringContainsString('RequireAuthenticatedDelivery()', $portalController);
		self::assertStringContainsString('DocumentForDelivery(', $portalController);
		self::assertStringContainsString('$this->RequireUser();', $ownerController);
		self::assertStringContainsString('FindAssignedDocumentForUser(', $ownerController);
		self::assertStringContainsString("header('Accept-Ranges: '", $streamer);
		self::assertStringContainsString("header('Content-Range: bytes '", $streamer);
		self::assertStringContainsString("frame-src 'self'", $headers);
		self::assertStringContainsString("media-src 'self'", $headers);
	}

	/** @brief Ensures monitor documents use a compact list and dedicated editors. */
	public function testMonitorDocumentsUseDedicatedEditors(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$editor = (string)file_get_contents($root . '/app/Views/documents/editor.php');
		$javascript = (string)file_get_contents($root . '/public/assets/app.js');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('document-library-list', $view);
		self::assertStringContainsString('/monitors/documents/text/new?monitor_id=', $view);
		self::assertStringContainsString('/monitors/documents/edit?monitor_id=', $view);
		self::assertStringContainsString('document_editor_content', $editor);
		self::assertStringNotContainsString('data-document-editor', $view);
		self::assertStringNotContainsString('data-document-tab-unsaved', $view);
		self::assertStringNotContainsString('updateDocumentTabDirtyState', $javascript);
		self::assertStringContainsString('.document-library-item', $styles);
		self::assertStringContainsString('.document-editor-form', $styles);
	}

}
