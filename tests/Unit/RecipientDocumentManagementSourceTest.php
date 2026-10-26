<?php

/**
 * @file RecipientDocumentManagementSourceTest.php
 * @brief Source regressions for recipient-context document management.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RecipientDocumentManagementSourceTest extends TestCase
{
	/** @brief Recipient actions must use the shared monitor document routes. */
	public function testRecipientDocumentsProvideSharedEditingAndUpload(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/recipients/edit.php');

		self::assertStringContainsString('/monitors/documents/text/new?monitor_id=', $view);
		self::assertStringContainsString('/monitors/documents/edit?monitor_id=', $view);
		self::assertStringContainsString('/monitors/documents/upload', $view);
		self::assertStringContainsString('name="recipient_id"', $view);
		self::assertStringContainsString('enctype="multipart/form-data"', $view);
		self::assertStringContainsString('data-recipient-documents-assignment-form', $view);
		self::assertStringContainsString('data-recipient-document-navigate', $view);
		self::assertStringContainsString('data-recipient-document-upload-form', $view);
		self::assertStringContainsString('if (!$isArchivedMonitor)', $view);
	}

	/** @brief Document controllers must validate the recipient and preserve return context. */
	public function testSharedDocumentActionsKeepVerifiedRecipientContext(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/DocumentController.php');
		$editor = (string)file_get_contents($root . '/app/Views/documents/editor.php');
		$service = (string)file_get_contents($root . '/app/Services/DocumentService.php');

		self::assertStringContainsString('private function VerifiedRecipientId(', $controller);
		self::assertStringContainsString('FindMonitorContactsByMonitorIdForUser($monitorId, $userId)', $controller);
		self::assertStringContainsString('$recipientId > 0 ? [$recipientId] : []', $controller);
		self::assertStringContainsString('DocumentsReturnUrl($monitorId, $recipientId)', $controller);
		self::assertStringContainsString("'returnRecipientId' => $recipientId", $controller);
		self::assertStringContainsString('name="recipient_id"', $editor);
		self::assertStringContainsString('returnPath', $editor);
		self::assertStringContainsString('$this->FilterRecipientIds($userId, $monitorId, $recipientIds)', $service);
	}

	/** @brief Pending recipient checkbox selections must be guarded across document actions. */
	public function testDocumentManagementWarnsAboutUnsavedAssignments(): void
	{
		$script = (string)file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');
		self::assertStringContainsString("'[data-recipient-documents-assignment-form]'", $script);
		self::assertStringContainsString("'[data-recipient-document-navigate]'", $script);
		self::assertStringContainsString("'[data-recipient-document-upload-form]'", $script);
		self::assertStringContainsString('event.preventDefault()', $script);
		self::assertStringContainsString("window.addEventListener('beforeunload'", $script);
	}

	/** @brief Safety selection needs checked-address status, not the raw addresses. */
	public function testSafetyContactCardsStayCompact(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$start = strpos($view, 'name="safety_contact_ids[]"');
		$end = strpos($view, "monitors.escalation.timing.heading", (int)$start);
		self::assertNotFalse($start);
		self::assertNotFalse($end);
		$section = substr($view, (int)$start, (int)$end - (int)$start);
		self::assertStringContainsString('contacts.status.checked_available', $section);
		self::assertStringContainsString('contacts.status.none_checked', $section);
		self::assertStringNotContainsString('FromRow($contact)', $section);
		self::assertStringNotContainsString('notification_language_name', $section);
	}
}
