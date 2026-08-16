<?php

/**
 * @file PrivateFileStreamerTest.php
 * @brief Unit checks for authenticated private-file byte-range parsing.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\PrivateFileStreamer;

final class PrivateFileStreamerTest extends TestCase
{
	/** @brief Accepts closed, open-ended, and suffix ranges with inclusive bounds. */
	public function testParsesSingleRanges(): void
	{
		$streamer = new PrivateFileStreamer();

		self::assertSame(['start' => 0, 'end' => 99], $streamer->ParseRange('bytes=0-99', 1000));
		self::assertSame(['start' => 900, 'end' => 999], $streamer->ParseRange('bytes=900-', 1000));
		self::assertSame(['start' => 900, 'end' => 999], $streamer->ParseRange('bytes=-100', 1000));
		self::assertSame(['start' => 950, 'end' => 999], $streamer->ParseRange('bytes=950-1200', 1000));
	}

	/** @brief Rejects unsatisfiable and multi-range requests. */
	public function testRejectsInvalidRanges(): void
	{
		$streamer = new PrivateFileStreamer();

		self::assertNull($streamer->ParseRange('bytes=1000-', 1000));
		self::assertNull($streamer->ParseRange('bytes=50-20', 1000));
		self::assertNull($streamer->ParseRange('bytes=0-10,20-30', 1000));
		self::assertNull($streamer->ParseRange('items=0-10', 1000));
		self::assertNull($streamer->ParseRange('bytes=-0', 1000));
		self::assertNull($streamer->ParseRange('bytes=0-1', 0));
	}
}
