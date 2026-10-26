<?php

/**
 * @file TextDocumentDraftSourceTest.php
 * @brief Source regressions for local text-document draft recovery.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TextDocumentDraftSourceTest extends TestCase
{
	/** @brief Drafts are only active for editable text documents and scoped to user/monitor/document. */
	public function testEditableTextDocumentHasLocallyScopedRecoveryUi(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/documents/editor.php');
		self::assertStringContainsString('$isText && !$isArchived', $view);
		self::assertStringContainsString('data-document-draft-key=', $view);
		self::assertStringContainsString("(int)\$user['id']", $view);
		self::assertStringContainsString('data-document-draft-restore', $view);
		self::assertStringContainsString('data-document-draft-discard', $view);
		self::assertStringContainsString('data-document-draft-status', $view);
		self::assertStringContainsString('aria-live="polite"', $view);
	}

	/** @brief A failed or incomplete HTTP save must not remove the browser draft. */
	public function testClientClearsOnlyAfterServerAcknowledgesSuccessfulSave(): void
	{
		$script = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');
		self::assertStringContainsString("'X-Pulse-Document-Save': '1'", $script);
		self::assertStringContainsString('response.ok || payload.saved !== true', $script);
		self::assertStringContainsString('removeDraft();', $script);
		self::assertStringContainsString('window.location.assign(payload.redirect)', $script);
		self::assertStringContainsString('window.localStorage.setItem(storageKey', $script);
		self::assertStringContainsString("window.addEventListener('pagehide'", $script);
	}

	/** @brief Only the text-document write endpoints return JSON save acknowledgements. */
	public function testTextDocumentActionsReportConfirmedSaves(): void
	{
		$controller = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/DocumentController.php');
		self::assertStringContainsString('private function FinishTextSave(', $controller);
		self::assertStringContainsString("\$_SERVER['HTTP_X_PULSE_DOCUMENT_SAVE']", $controller);
		self::assertStringContainsString("'saved' => \$saved", $controller);
		self::assertStringContainsString('FinishTextSave(true,', $controller);
		self::assertStringContainsString('FinishTextSave(false,', $controller);
	}
}
