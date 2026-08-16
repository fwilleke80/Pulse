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

	/** @brief Ensures changed document details are marked until their own form reloads after save. */
	public function testDocumentEditorMarksUnsavedChanges(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$javascript = (string)file_get_contents($root . '/public/assets/app.js');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('data-document-editor', $view);
		self::assertStringContainsString('data-document-unsaved-indicator', $view);
		self::assertStringContainsString('data-document-tab-unsaved', $view);
		self::assertStringContainsString('data-document-save-button', $view);
		self::assertStringContainsString('formSignature() !== initialSignature', $javascript);
		self::assertStringContainsString("card.classList.toggle('is-dirty', isDirty)", $javascript);
		self::assertStringContainsString('updateDocumentTabDirtyState()', $javascript);
		self::assertStringContainsString('.monitor-document-card.is-dirty', $styles);
		self::assertStringContainsString('.document-unsaved-indicator', $styles);
	}
}
