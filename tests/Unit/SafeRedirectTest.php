<?php

/**
 * @file SafeRedirectTest.php
 * @brief Tests local redirect validation.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pulse\Core\SafeRedirect;

class SafeRedirectTest extends TestCase
{
	/** @return array<string, array{string, string}> */
	public static function Targets(): array
	{
		return [
			'root' => ['/', '/'],
			'local path and query' => ['/monitors/edit?id=4', '/monitors/edit?id=4'],
			'absolute URL' => ['https://attacker.example/path', '/fallback'],
			'scheme relative URL' => ['//attacker.example/path', '/fallback'],
			'backslash confusion' => ['/\\attacker.example/path', '/fallback'],
			'header injection' => ["/safe\r\nX-Test: bad", '/fallback'],
		];
	}

	#[DataProvider('Targets')]
	public function testNormalize(string $target, string $expected): void
	{
		self::assertSame($expected, SafeRedirect::Normalize($target, '/fallback'));
	}
}
