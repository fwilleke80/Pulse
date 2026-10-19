<?php

/**
 * @file InactiveRecipientConfigurationSourceTest.php
 * @brief Guards non-destructive recipient assignment and future delivery filtering.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InactiveRecipientConfigurationSourceTest extends TestCase
{
	/** @brief Inactive profiles retain their ID, personalization, and documents. */
	public function testAssignmentSyncIsNonDestructive(): void
	{
		$root = dirname(__DIR__, 2);
		$source = (string)file_get_contents($root . '/app/Repositories/MonitorRepository.php');
		$recipient = (string)file_get_contents($root . '/app/Repositories/RecipientRepository.php');
		self::assertStringContainsString('UPDATE monitor_contacts SET is_assigned = 0', $source);
		self::assertStringContainsString('SET sort_order = :sort_order, is_assigned = 1', $source);
		self::assertStringContainsString('WHERE monitor_id = :monitor_id AND is_assigned = 1', $source);
		self::assertStringNotContainsString('DELETE FROM monitor_contacts', $source);
		self::assertStringContainsString('SET mc.is_assigned = 0', $recipient);
		self::assertStringContainsString('bool $assign = true', $recipient);
	}

	/** @brief Inactive recipients must not appear in future release staging. */
	public function testReleaseRecipientsMustBeAssigned(): void
	{
		$root = dirname(__DIR__, 2);
		$source = (string)file_get_contents($root . '/app/Services/EscalationService.php');
		self::assertStringContainsString('WHERE mc.monitor_id = :monitor_id AND mc.is_assigned = 1', $source);
	}

	/** @brief Both active and inactive recipients remain individually configurable. */
	public function testDraftRecipientEditorAndUnifiedSave(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		self::assertStringContainsString('name="configure_only" value="1"', $view);
		self::assertStringContainsString('name="recipient_assignments_present" value="1"', $view);
		self::assertStringContainsString('form="monitor-settings-form"', $view);
		self::assertStringContainsString('ReplaceContactsForMonitor($monitorId, ', $controller);
		self::assertStringNotContainsString("e__('recipients.assignments.save')", $view);
	}
}
