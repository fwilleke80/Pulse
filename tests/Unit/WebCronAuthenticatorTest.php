<?php

/**
 * @file WebCronAuthenticatorTest.php
 * @brief Tests the token-protected public notification runner.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\WebCronAuthenticator;

class WebCronAuthenticatorTest extends TestCase
{
	private const TOKEN = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

	public function testOnlyExactTokenOnGetIsAuthorized(): void
	{
		self::assertTrue(WebCronAuthenticator::IsTokenValid(self::TOKEN, self::TOKEN));
		self::assertTrue(WebCronAuthenticator::IsAuthorized('GET', self::TOKEN, self::TOKEN));
		self::assertFalse(WebCronAuthenticator::IsAuthorized('POST', self::TOKEN, self::TOKEN));
		self::assertFalse(WebCronAuthenticator::IsTokenValid(self::TOKEN . 'x', self::TOKEN));
		self::assertFalse(WebCronAuthenticator::IsAuthorized('GET', self::TOKEN . 'x', self::TOKEN));
		self::assertFalse(WebCronAuthenticator::IsAuthorized('GET', null, self::TOKEN));
	}

	public function testShortOrMultilineTokensAreNotConfigured(): void
	{
		self::assertFalse(WebCronAuthenticator::IsConfigured('too-short'));
		self::assertFalse(WebCronAuthenticator::IsConfigured(self::TOKEN . "\n"));
	}


	/** @brief Ensures diagnostic logging preserves normal tokens but bounds hostile oversized values. */
	public function testDiagnosticTokenIsBoundedAndHandlesNonScalarInput(): void
	{
		$normal = WebCronAuthenticator::DiagnosticToken('old-token-value');
		self::assertSame('old-token-value', $normal['token']);
		self::assertFalse($normal['truncated']);

		$oversized = WebCronAuthenticator::DiagnosticToken(str_repeat('x', 600));
		self::assertSame(512, strlen((string)$oversized['token']));
		self::assertTrue($oversized['truncated']);

		$nonScalar = WebCronAuthenticator::DiagnosticToken(['unexpected']);
		self::assertNull($nonScalar['token']);
		self::assertFalse($nonScalar['truncated']);
	}
}
