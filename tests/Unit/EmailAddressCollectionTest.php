<?php

/**
 * @file EmailAddressCollectionTest.php
 * @brief Tests the bounded separately checked email-address model.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pulse\Core\EmailAddressCollection;

final class EmailAddressCollectionTest extends TestCase
{
	/** @brief Checked extraction excludes unchecked and duplicate addresses. */
	public function testCheckedAddressesAreReturnedInSlotOrder(): void
	{
		$row = [
			'email' => 'one@example.org',
			'email_checked_at' => '2026-08-16 10:00:00',
			'email_2' => 'two@example.org',
			'email_2_checked_at' => null,
			'email_3' => 'three@example.org',
			'email_3_checked_at' => '2026-08-16 10:00:00',
			'email_4' => null,
			'email_4_checked_at' => null,
		];

		self::assertSame(['one@example.org', 'three@example.org'], EmailAddressCollection::Checked($row));
		self::assertTrue(EmailAddressCollection::HasChecked($row));
	}

	/** @brief Empty slots compact while each address retains its own checked state. */
	public function testNormalizeCompactsEmptySlots(): void
	{
		$normalized = EmailAddressCollection::Normalize([
			['email' => '', 'checked' => true],
			['email' => ' second@example.org ', 'checked' => false],
			['email' => 'third@example.org', 'checked' => true],
		]);

		self::assertSame([
			['email' => 'second@example.org', 'checked' => false],
			['email' => 'third@example.org', 'checked' => true],
		], $normalized);
	}

	/** @brief Duplicate destinations are rejected case-insensitively. */
	public function testNormalizeRejectsDuplicateAddresses(): void
	{
		$this->expectException(InvalidArgumentException::class);
		EmailAddressCollection::Normalize([
			['email' => 'Same@example.org', 'checked' => true],
			['email' => 'same@example.org', 'checked' => false],
		]);
	}
}
