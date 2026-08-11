<?php

/**
 * @file RequestTest.php
 * @brief Tests typed request input normalization.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\Request;

class RequestTest extends TestCase
{
	public function testTypedInputAndPath(): void
	{
		$request = new Request(
			['id' => '42'],
			['name' => '  Pulse  ', 'ids' => ['2', '2', '-1', '5'], 'enabled' => 'on'],
			[],
			['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/monitors/edit?id=42', 'REMOTE_ADDR' => '127.0.0.1']
		);

		self::assertSame('POST', $request->Method());
		self::assertSame('/monitors/edit', $request->Path());
		self::assertSame(42, $request->QueryInt('id'));
		self::assertSame('Pulse', $request->PostString('name'));
		self::assertSame([2, 5], $request->PostIntArray('ids'));
		self::assertTrue($request->PostBool('enabled'));
		self::assertSame('127.0.0.1', $request->ClientIp());
	}
}
