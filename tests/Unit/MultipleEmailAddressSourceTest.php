<?php

/**
 * @file MultipleEmailAddressSourceTest.php
 * @brief Guards the four-address interface, schema, and delivery fan-out.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MultipleEmailAddressSourceTest extends TestCase
{
	/** @brief The migration and reference schema preserve four checked slots and delivery snapshots. */
	public function testMigrationDefinesBoundedAddressStorage(): void
	{
		$root = dirname(__DIR__, 2);
		$migration = (string)file_get_contents($root . '/database/migrations/004_multiple_email_addresses.sql');
		$schema = (string)file_get_contents($root . '/database/schema.sql');

		self::assertStringContainsString('email_4_checked_at', $migration);
		self::assertStringContainsString('CREATE TABLE safety_contact_request_emails', $migration);
		self::assertStringContainsString('CREATE TABLE recipient_release_delivery_emails', $migration);
		self::assertStringContainsString('email_4_checked_at', $schema);
	}

	/** @brief Contact and owner editors share the responsive four-card interface. */
	public function testEditorsExposeFourIndependentAddressCards(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/ContactController.php');
		$contact = (string)file_get_contents($root . '/app/Views/contacts/edit.php');
		$profile = (string)file_get_contents($root . '/app/Views/profile/index.php');
		$css = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('EmailAddressCollection::Normalize', $controller);
		self::assertStringNotContainsString('email_not_checked', $controller);
		self::assertStringContainsString('EmailAddressCollection::MAX_ADDRESSES', $contact);
		self::assertStringContainsString('EmailAddressCollection::MAX_ADDRESSES', $profile);
		self::assertStringContainsString('data-email-address-card', $contact);
		self::assertStringContainsString('repeat(auto-fit', $css);
		self::assertStringContainsString('320px', $css);
	}

	/** @brief Every notification family fans out only through checked-address snapshots. */
	public function testDeliveryServicesFanOutCheckedAddresses(): void
	{
		$root = dirname(__DIR__, 2);
		$scheduler = (string)file_get_contents($root . '/app/Services/NotificationScheduler.php');
		$escalation = (string)file_get_contents($root . '/app/Services/EscalationService.php');
		$portal = (string)file_get_contents($root . '/app/Services/RecipientPortalService.php');

		self::assertStringContainsString('EmailAddressCollection::Checked($cycle)', $scheduler);
		self::assertStringContainsString('EmailAddressCollection::Checked($contact)', $escalation);
		self::assertStringContainsString('EmailAddressCollection::Checked($recipient)', $escalation);
		self::assertStringContainsString('FindEmailsForDelivery', $portal);
	}

	/** @brief Every owner address is a login alias while checked state controls mail only. */
	public function testOwnerAddressesAreLoginAliases(): void
	{
		$repository = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');

		self::assertStringContainsString('email_2 = :email_2', $repository);
		self::assertStringContainsString('email_4 = :email_4', $repository);
		self::assertStringContainsString('email_4_checked_at = CASE', $repository);
	}
}
