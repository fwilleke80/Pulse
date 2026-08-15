<?php

/**
 * @file ArchivedMonitorReadOnlySourceTest.php
 * @brief Source-level regression checks for Pulse 0.9.7 archived read-only behavior and compact list actions.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ArchivedMonitorReadOnlySourceTest extends TestCase
{
	/** @brief Ensures the monitor editor visibly disables archived configuration while keeping reset available. */
	public function testArchivedMonitorEditorIsReadOnly(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$actions = (string)file_get_contents($root . '/app/Views/monitors/partials/actions.php');

		self::assertStringContainsString('$isArchived = !empty($monitor[\'is_archived\'])', $view);
		self::assertStringContainsString('monitor-readonly-fieldset', $view);
		self::assertStringContainsString('$isArchived ? \' disabled\' : \'\'', $view);
		self::assertStringContainsString('$isArchived ? \'\' : \'details,schedule,escalation,review\'', $view);
		self::assertStringContainsString('monitors/reset-reactivate', $view);
		self::assertStringContainsString('$actionAllowDelete && $actionStatus !== \'archived\'', $actions);
	}

	/** @brief Ensures archived mutation attempts are rejected server-side. */
	public function testArchivedMutationEndpointsHaveServerSideGuards(): void
	{
		$root = dirname(__DIR__, 2);
		$monitorController = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$recipientController = (string)file_get_contents($root . '/app/Controllers/RecipientController.php');
		$documentService = (string)file_get_contents($root . '/app/Services/DocumentService.php');

		self::assertGreaterThanOrEqual(3, substr_count($monitorController, "monitors.archived.readonly.flash"));
		self::assertStringContainsString('monitor_is_archived', $recipientController);
		self::assertStringContainsString('RejectArchivedMonitor', $recipientController);
		self::assertStringContainsString('RequireEditableMonitor', $documentService);
		self::assertStringContainsString("monitors.archived.readonly.flash", $documentService);
	}

	/** @brief Ensures recipient configuration is read-only while released delivery management remains separate. */
	public function testArchivedRecipientConfigurationIsReadOnly(): void
	{
		$root = dirname(__DIR__, 2);
		$repository = (string)file_get_contents($root . '/app/Repositories/RecipientRepository.php');
		$view = (string)file_get_contents($root . '/app/Views/recipients/edit.php');

		self::assertStringContainsString('m.is_archived AS monitor_is_archived', $repository);
		self::assertStringContainsString('recipient-readonly-fieldset', $view);
		self::assertStringContainsString('recipients.archived.readonly.message', $view);
		self::assertStringContainsString('/monitors/recipients/delivery/portal/update', $view);
		self::assertStringContainsString('/monitors/recipients/portal/revoke', $view);
	}

	/** @brief Ensures list actions use compact menus and recipient overview no longer mixes delivery status into configuration. */
	public function testListLayoutsAreCompactAndConsistent(): void
	{
		$root = dirname(__DIR__, 2);
		$contacts = (string)file_get_contents($root . '/app/Views/contacts/index.php');
		$monitorView = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/MonitorRepository.php');
		$style = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('row-action-menu-toggle', $contacts);
		self::assertStringContainsString('compact-actions-cell', $contacts);
		self::assertStringContainsString('contacts.actions.open', $contacts);
		self::assertStringNotContainsString("latest_delivery_status", $monitorView);
		self::assertStringNotContainsString("latest_delivery_status", $repository);
		self::assertStringContainsString('width: 1%', $style);
	}
}
