<?php

/**
 * @file ProfileTabsSourceTest.php
 * @brief Guards the three current tabbed Profile sections.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProfileTabsSourceTest extends TestCase
{
	/** @brief Uses only the current Profile data, Account security, and Change password tabs. */
	public function testProfileUsesThreeAccessibleTabs(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/profile/index.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/ProfileController.php');

		self::assertSame(3, substr_count($view, 'data-tab-target='));
		self::assertStringContainsString("e__('profile.tabs.profile_data')", $view);
		self::assertStringContainsString("e__('profile.tabs.account_security')", $view);
		self::assertStringContainsString("e__('profile.tabs.change_password')", $view);
		self::assertStringContainsString("['profile', 'security', 'password']", $controller);
	}
}
