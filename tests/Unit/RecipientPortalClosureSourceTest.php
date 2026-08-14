<?php

/**
 * @file RecipientPortalClosureSourceTest.php
 * @brief Source-level regression checks for recipient-controlled permanent portal closure.
 * @author Frank Willeke
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RecipientPortalClosureSourceTest extends TestCase
{
	/** @brief Ensures public closure routes and the guarded confirmation UI remain wired. */
	public function testClosureRoutesAndConfirmationUiArePresent(): void
	{
		$root = dirname(__DIR__, 2);
		$routes = (string)file_get_contents($root . '/public/index.php');
		$view = (string)file_get_contents($root . '/app/Views/portal/close-confirm.php');
		$access = (string)file_get_contents($root . '/app/Views/portal/access.php');

		self::assertStringContainsString("/portal/close", $routes);
		self::assertStringContainsString("CloseConfirmation", $routes);
		self::assertStringContainsString("ClosePermanently", $routes);
		self::assertStringContainsString("/portal/closed", $routes);
		self::assertStringContainsString("confirm_downloaded", $view);
		self::assertStringContainsString("confirmation_code", $view);
		self::assertStringContainsString("portal.documents.download_all", $view);
		self::assertStringContainsString("empty(\$delivery['portal_expires_at'])", $access);
	}

	/** @brief Ensures closure is restricted to non-expiring token-scoped deliveries and tracked distinctly. */
	public function testRepositoryFailsClosedAndTracksRecipientClosure(): void
	{
		$root = dirname(__DIR__, 2);
		$repository = (string)file_get_contents($root . '/app/Repositories/RecipientPortalRepository.php');
		$migration = (string)file_get_contents($root . '/database/migrations/017_recipient_portal_closure.sql');

		self::assertStringContainsString('portal_token_hash = :token_hash', $repository);
		self::assertStringContainsString('portal_expires_at IS NULL', $repository);
		self::assertStringContainsString('portal_closed_by_recipient_at = UTC_TIMESTAMP()', $repository);
		self::assertStringContainsString('recipient.portal_closed_by_recipient', $repository);
		self::assertStringContainsString('portal_closed_by_recipient_at', $migration);
	}
}
