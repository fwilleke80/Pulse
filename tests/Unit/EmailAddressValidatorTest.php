<?php

/**
 * @file EmailAddressValidatorTest.php
 * @brief Tests silent recipient address validation and typo suggestions.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\EmailAddressValidator;

class EmailAddressValidatorTest extends TestCase
{
	public function testValidAddressDoesNotRequireContactingItsOwner(): void
	{
		self::assertTrue(EmailAddressValidator::IsValid('recipient@example.org'));
		self::assertNull(EmailAddressValidator::Suggestion('recipient@example.org'));
	}

	public function testCommonDomainTypoProducesConservativeSuggestion(): void
	{
		self::assertTrue(EmailAddressValidator::IsValid('recipient@gamil.com'));
		self::assertSame('recipient@gmail.com', EmailAddressValidator::Suggestion('recipient@gamil.com'));
	}

	public function testInvalidAddressIsRejected(): void
	{
		self::assertFalse(EmailAddressValidator::IsValid('not-an-address'));
	}
}
