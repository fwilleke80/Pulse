<?php

/**
 * @file RecipientPortalLanguagePreferenceTest.php
 * @brief Tests recipient-portal language overrides scoped to one invitation token.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\RecipientPortalLanguagePreference;

class RecipientPortalLanguagePreferenceTest extends TestCase
{
	public function testPortalTokenIsExtractedFromPortalRedirect(): void
	{
		$token = str_repeat('a', 64);
		self::assertSame($token, RecipientPortalLanguagePreference::TokenFromRedirect('/portal?token=' . $token . '&lang=de'));
		self::assertSame($token, RecipientPortalLanguagePreference::TokenFromRedirect('/portal/access?token=' . $token));
	}

	public function testUnrelatedOrMalformedRedirectIsRejected(): void
	{
		$token = str_repeat('b', 64);
		self::assertNull(RecipientPortalLanguagePreference::TokenFromRedirect('/login?token=' . $token));
		self::assertNull(RecipientPortalLanguagePreference::TokenFromRedirect('/portal?token=bad'));
	}

	public function testPortalSessionKeyDoesNotContainRawToken(): void
	{
		$token = str_repeat('c', 64);
		$key = RecipientPortalLanguagePreference::SessionKey($token);
		self::assertStringNotContainsString($token, $key);
	}
}
