<?php

/**
 * @file PortalAuditRegressionSourceTest.php
 * @brief Source-level regression checks for recipient-portal audit fixes and clean schema baseline.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PortalAuditRegressionSourceTest extends TestCase
{
	/** @brief Ensures recipient document delivery no longer exposes obsolete milestone copy and text previews are useful. */
	public function testRecipientDocumentUiReflectsCurrentSecureDelivery(): void
	{
		$root = dirname(__DIR__, 2);
		$editor = (string)file_get_contents($root . '/app/Views/recipients/edit.php');
		$portal = (string)file_get_contents($root . '/app/Views/portal/access.php');

		self::assertStringNotContainsString('recipients.documents.gated_heading', $editor);
		self::assertStringNotContainsString('recipients.documents.gated_message', $editor);
		self::assertStringContainsString('portal-document-preview-text', $portal);
		self::assertStringContainsString("['text_content']", $portal);
		self::assertStringContainsString('<button type="submit" class="btn-danger">', $portal);
	}

	/** @brief Ensures inactive deliveries still receive styled, language-aware portal handling. */
	public function testUnavailablePortalUsesLanguageAwareStyledResponses(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/RecipientPortalController.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/RecipientPortalRepository.php');
		$language = (string)file_get_contents($root . '/app/Controllers/LanguageController.php');

		self::assertStringContainsString('FindLanguageMetadataByToken', $repository);
		self::assertStringContainsString('UseUnavailablePortalLanguage', $controller);
		self::assertStringContainsString("portal.document-unavailable", $controller);
		self::assertStringContainsString('IsClosedRecipientPortalRedirect', $language);
		self::assertStringNotContainsString("return Response::Text(__('portal.document.not_found')", $controller);
	}

	/** @brief Ensures significant portal activity is surfaced without treating a simple page open as human activity. */
	public function testRecipientHistoryUsesExistingPortalAuditEvents(): void
	{
		$root = dirname(__DIR__, 2);
		$repository = (string)file_get_contents($root . '/app/Repositories/RecipientRepository.php');
		$view = (string)file_get_contents($root . '/app/Views/recipients/edit.php');

		self::assertStringContainsString('FindPortalActivityForUser', $repository);
		self::assertStringContainsString('recipient.portal_code_requested', $repository);
		self::assertStringContainsString('recipient.portal_access_granted', $repository);
		self::assertStringContainsString('recipient.portal_document_downloaded', $repository);
		self::assertStringContainsString('recipient.portal_closed_by_recipient', $repository);
		self::assertStringContainsString('recipient-activity-timeline', $view);
		self::assertStringNotContainsString('recipient.portal_opened', $repository);
	}

	/** @brief Ensures mail/debug status presentation reflects real queue state. */
	public function testOperationalListsExposeUsefulQueueState(): void
	{
		$root = dirname(__DIR__, 2);
		$administration = (string)file_get_contents($root . '/app/Views/administration/index.php');
		$monitorRepository = (string)file_get_contents($root . '/app/Repositories/MonitorRepository.php');
		$actions = (string)file_get_contents($root . '/app/Views/monitors/partials/actions.php');

		self::assertStringContainsString("['created_at']", $administration);
		self::assertStringContainsString('due_notice_queue_status', $monitorRepository);
		self::assertStringContainsString('dueNoticeSatisfied', $actions);
		self::assertStringContainsString('monitors.send_due_notice.pending', $actions);
	}

	/** @brief Ensures the stable baseline contains only the current schema and no retired tables. */
	public function testInitialSchemaContainsOnlyCurrentTables(): void
	{
		$root = dirname(__DIR__, 2);
		$schema = (string)file_get_contents($root . '/database/migrations/001_initial_schema.sql');
		$migrations = glob($root . '/database/migrations/*.sql');

		self::assertIsArray($migrations);
		self::assertCount(3, $migrations);
		self::assertStringNotContainsString('CREATE TABLE access_tokens', $schema);
		self::assertStringNotContainsString('CREATE TABLE app_settings', $schema);
		self::assertStringContainsString('CREATE TABLE recipient_release_deliveries', $schema);
		self::assertStringContainsString('is_archived', $schema);
	}
}
