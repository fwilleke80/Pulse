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

	/** @brief Truncation retains the same file and allows subsequent log writes. */
	public function testClearingLogKeepsItAvailableAndReusable(): void
	{
		file_put_contents($this->_path, "2026-10-31T12:00:00+00:00 [ERROR] Previous failure\n");
		$before = stat($this->_path);
		$reader = new ApplicationLogReader($this->_path);

		self::assertTrue($reader->Clear());
		clearstatcache(true, $this->_path);
		self::assertSame($before['ino'], stat($this->_path)['ino']);
		self::assertSame('', file_get_contents($this->_path));
		self::assertSame([], $reader->Read()['entries']);

		file_put_contents($this->_path, "2026-10-31T13:00:00+00:00 [INFO] Cron healthy\n", FILE_APPEND | LOCK_EX);
		self::assertCount(1, $reader->Read('INFO')['entries']);
	}

	/** @brief Missing logs and symbolic links must never be cleared via this utility. */
	public function testClearingMissingLogIsHarmlessAndSymlinksAreRejected(): void
	{
		$reader = new ApplicationLogReader($this->_path);
		unlink($this->_path);
		self::assertFalse($reader->Clear());

		$other = tempnam(sys_get_temp_dir(), 'pulse-target-');
		self::assertIsString($other);
		try
		{
			file_put_contents($other, 'Important unrelated file');
			if (!symlink($other, $this->_path))
			{
				self::markTestSkipped('Symbolic links are unavailable on this filesystem.');
			}
			try
			{
				$reader->Clear();
				self::fail('Clearing a symbolic link must be rejected.');
			}
			catch (\RuntimeException $exception)
			{
				self::assertStringContainsString('symbolic-link', $exception->getMessage());
			}
			self::assertSame('Important unrelated file', file_get_contents($other));
		}
		finally
		{
			if (is_link($this->_path))
			{
				unlink($this->_path);
			}
			unlink($other);
		}
	}

	/** @brief A stale or never-successful cron cannot be classified as healthy. */
	public function testCronHealthUsesSameThresholdAcrossSurfaces(): void
	{
		self::assertSame('never', CronHealth::Status(null));
		self::assertSame('stale', CronHealth::Status(gmdate('Y-m-d H:i:s', time() - 90000)));
		self::assertSame('recent', CronHealth::Status(gmdate('Y-m-d H:i:s', time() - 3600)));
	}
}
