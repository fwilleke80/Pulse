<?php

/**
 * @file ApplicationLogReaderTest.php
 * @brief Regression tests for bounded, reverse-ordered log inspection.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Services\ApplicationLogReader;
use Pulse\Services\CronHealth;

/** @brief Exercises log filtering, paging, and cron-health classification. */
final class ApplicationLogReaderTest extends TestCase
{
	private string $_path;

	protected function setUp(): void
	{
		$this->_path = tempnam(sys_get_temp_dir(), 'pulse-log-test-') ?: '';
		self::assertNotSame('', $this->_path);
	}

	protected function tearDown(): void
	{
		if (is_file($this->_path))
		{
			unlink($this->_path);
		}
	}

	/** @brief Filter backward across chunk boundaries without loading the full log. */
	public function testNewestFirstSeverityAndPaging(): void
	{
		$lines = [];
		for ($index = 1; $index <= 800; ++$index)
		{
			$level = $index % 4 === 0 ? 'ERROR' : 'INFO';
			$lines[] = sprintf('2026-10-31T12:00:00+00:00 [%s] Entry %d %s', $level, $index, str_repeat('x', 20));
		}
		file_put_contents($this->_path, implode("\n", $lines) . "\n");
		$reader = new ApplicationLogReader($this->_path);
		$page = $reader->Read('ERROR', 'Entry', 1, 2);
		self::assertTrue($page['available']);
		self::assertTrue($page['has_more']);
		self::assertStringContainsString('Entry 800', $page['entries'][0]['message']);
		self::assertStringContainsString('Entry 796', $page['entries'][1]['message']);
		self::assertStringContainsString('Entry 792', $reader->Read('ERROR', 'Entry', 2, 2)['entries'][0]['message']);
		self::assertCount(0, $reader->Read('DEBUG')['entries']);
	}

	/** @brief Nonexistent logs are an expected empty state rather than a server error. */
	public function testMissingLogFileHasExplicitAvailability(): void
	{
		unlink($this->_path);
		self::assertSame(['entries' => [], 'has_more' => false, 'available' => false], (new ApplicationLogReader($this->_path))->Read());
	}

	/** @brief Even single-line logs without a final newline remain readable. */
	public function testLogWithoutNewlineAndSpecialContent(): void
	{
		file_put_contents($this->_path, "2026-10-31T12:00:00+00:00 [WARNING] <script>alert('x')</script>");
		$result = (new ApplicationLogReader($this->_path))->Read('WARNING');
		self::assertCount(1, $result['entries']);
		self::assertSame("<script>alert('x')</script>", $result['entries'][0]['message']);
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/administration/log-results.php');
		self::assertStringContainsString('e($entry[\'message\'])', $view);
	}

	/** @brief A stale or never-successful cron cannot be classified as healthy. */
	public function testCronHealthUsesSameThresholdAcrossSurfaces(): void
	{
		self::assertSame('never', CronHealth::Status(null));
		self::assertSame('stale', CronHealth::Status(gmdate('Y-m-d H:i:s', time() - 90000)));
		self::assertSame('recent', CronHealth::Status(gmdate('Y-m-d H:i:s', time() - 3600)));
	}
}
