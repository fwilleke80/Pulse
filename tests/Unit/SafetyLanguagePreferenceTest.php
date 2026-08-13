<?php

/**
 * @file SafetyLanguagePreferenceTest.php
 * @brief Tests safety-page language overrides scoped to one response token.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\SafetyLanguagePreference;

class SafetyLanguagePreferenceTest extends TestCase
{
	public function testSafetyTokenIsExtractedFromConfirmationRedirect(): void
	{
		$token = str_repeat('a', 64);
		$redirect = '/safety/confirm?token=' . $token . '&lang=de';

		self::assertSame($token, SafetyLanguagePreference::TokenFromRedirect($redirect));
	}

	public function testUnrelatedRedirectDoesNotCreateSafetyPreference(): void
	{
		$token = str_repeat('b', 64);

		self::assertNull(SafetyLanguagePreference::TokenFromRedirect('/login?token=' . $token));
		self::assertNull(SafetyLanguagePreference::TokenFromRedirect('/safety/confirm?token=not-a-token'));
	}

	public function testDifferentSafetyTokensReceiveDifferentSessionKeys(): void
	{
		$left = SafetyLanguagePreference::SessionKey(str_repeat('c', 64));
		$right = SafetyLanguagePreference::SessionKey(str_repeat('d', 64));

		self::assertNotSame($left, $right);
		self::assertStringNotContainsString(str_repeat('c', 64), $left);
	}
}
