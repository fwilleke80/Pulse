<?php

/**
 * @file RecipientRepositorySourceTest.php
 * @brief Guards the recipient detail query's portal-template joins.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @brief Verifies that recipient portal columns are backed by their SQL joins.
 */
final class RecipientRepositorySourceTest extends TestCase
{
	/**
	 * @brief Ensures the recipient editor query joins both portal-content tables.
	 */
	public function testRecipientDetailQueryIncludesPortalContentJoins(): void
	{
		$source = file_get_contents(__DIR__ . '/../../app/Repositories/RecipientRepository.php');
		self::assertIsString($source);
		self::assertStringContainsString(
			'LEFT JOIN contact_portal_messages cpm ON cpm.monitor_contact_id = mc.id',
			$source
		);
		self::assertStringContainsString('LEFT JOIN monitor_portal_templates mpt', $source);
		self::assertStringContainsString('AND mpt.locale = c.notification_locale', $source);
		self::assertStringContainsString('mpt.intro_text AS default_portal_intro', $source);
		self::assertStringContainsString('COALESCE(cpm.is_enabled, 0) AS portal_override_enabled', $source);
		self::assertStringNotContainsString('mpt.message_text AS default_portal_message', $source);
	}
}
