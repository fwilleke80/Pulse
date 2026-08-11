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
		self::assertTrue(WebCronAuthenticator::IsAuthorized('GET', self::TOKEN, self::TOKEN));
		self::assertFalse(WebCronAuthenticator::IsAuthorized('POST', self::TOKEN, self::TOKEN));
		self::assertFalse(WebCronAuthenticator::IsAuthorized('GET', self::TOKEN . 'x', self::TOKEN));
		self::assertFalse(WebCronAuthenticator::IsAuthorized('GET', null, self::TOKEN));
	}

	public function testShortOrMultilineTokensAreNotConfigured(): void
	{
		self::assertFalse(WebCronAuthenticator::IsConfigured('too-short'));
		self::assertFalse(WebCronAuthenticator::IsConfigured(self::TOKEN . "\n"));
	}
}
