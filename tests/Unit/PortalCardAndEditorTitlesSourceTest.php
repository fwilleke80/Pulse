<?php

/**
 * @file PortalCardAndEditorTitlesSourceTest.php
 * @brief Protects document card reading order and descriptive editor browser titles.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** @brief Regression guards for the Pulse 1.4.6 presentation changes. */
final class PortalCardAndEditorTitlesSourceTest extends TestCase
{
	/** @brief Checks card content order and in-place framed document expansion. */
	public function testDocumentCardsKeepTheViewerBetweenMetadataAndButtons(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/portal/access.php');
		$css = (string)file_get_contents($root . '/public/assets/style.css');
		$script = (string)file_get_contents($root . '/public/assets/app.js');

		$start = strpos($view, '<article class="portal-document-card"');
		self::assertNotFalse($start);
		$card = substr($view, $start, strpos($view, '</article>', $start) - $start);
		self::assertNotFalse(strpos($card, 'portal-document-content'));
		self::assertNotFalse(strpos($card, 'portal-document-meta'));
		self::assertNotFalse(strpos($card, 'portal-document-preview-region'));
		self::assertNotFalse(strpos($card, 'portal-document-actions'));
		self::assertLessThan(strpos($card, 'portal-document-meta'), strpos($card, 'portal-document-content'));
		self::assertLessThan(strpos($card, 'portal-document-preview-region'), strpos($card, 'portal-document-meta'));
		self::assertLessThan(strpos($card, 'portal-document-actions'), strpos($card, 'portal-document-preview-region'));
		self::assertLessThan(strpos($card, 'portal-document-actions'), strpos($card, 'data-document-preview-panel'));
		self::assertStringContainsString("e__('portal.documents.open')", $card);
		self::assertStringContainsString("e__('portal.documents.close')", $card);
		self::assertStringContainsString('.portal-document-preview-region.is-framed > .portal-document-preview', $css);
		self::assertStringContainsString('panel.hidden = !expanded;', $script);
		self::assertStringContainsString('frame.src = frame.dataset.previewSrc', $script);
	}

	/** @brief Checks all editor types display their owning record's name in the tab title. */
	public function testEditorBrowserTitlesUseLocalizedRecordNames(): void
	{
		$root = dirname(__DIR__, 2);
		$monitor = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$recipient = (string)file_get_contents($root . '/app/Views/recipients/edit.php');
		$document = (string)file_get_contents($root . '/app/Views/documents/editor.php');

		self::assertStringContainsString("__('monitors.edit.browser_title', ['name' => (string)\$monitor['name']])", $monitor);
		self::assertStringContainsString("__('recipients.edit.browser_title', ['name' => (string)\$recipient['name']])", $recipient);
		self::assertStringContainsString("__('monitors.documents.editor.browser_title', ['name' => (string)\$document['title']])", $document);
		self::assertStringContainsString("__('monitors.documents.editor.new_title')", $document);
	}

	/** @brief Ensures a normal section gap after the release grace-period card. */
	public function testGracePeriodCardHasStandardSpaceBeforeEscalationPolicy(): void
	{
		$css = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');
		self::assertMatchesRegularExpression(
			'/\.escalation-policy-grid\s*\{[^}]*margin-top:\s*1\.25rem\s*;/s',
			$css
		);
	}

	/** @brief Ensures release grace settings precede the escalation-mode selector. */
	public function testGracePeriodComesBeforeEscalationPolicy(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		self::assertLessThan(
			strpos($view, 'class="escalation-policy-grid"'),
			strpos($view, 'name="release_grace_hours"')
		);
	}
}
