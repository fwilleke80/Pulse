<?php

/**
 * @file EnvironmentFileTest.php
 * @brief Unit tests for atomic .env editing.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\EnvironmentFile;

final class EnvironmentFileTest extends TestCase
{
	private string $_directory;
	private string $_path;

	protected function setUp(): void
	{
		$this->_directory = sys_get_temp_dir() . '/pulse-env-' . bin2hex(random_bytes(8));
		mkdir($this->_directory, 0700, true);
		$this->_path = $this->_directory . '/.env';
	}

	protected function tearDown(): void
	{
		if (is_file($this->_path))
		{
			unlink($this->_path);
		}

		if (is_dir($this->_directory))
		{
			rmdir($this->_directory);
		}
	}

	/** @brief Ensures comments and unrelated keys survive targeted updates. */
	public function testUpdatePreservesCommentsAndUnknownKeys(): void
	{
		file_put_contents($this->_path, "# keep this\nPULSE_APP_NAME=Pulse\nUNKNOWN_KEY=keep-me\n");
		$file = new EnvironmentFile($this->_path);
		$file->Update(['PULSE_APP_NAME' => 'Pulse Test', 'PULSE_CRON_TOKEN' => '12345678901234567890123456789012']);
		$content = (string)file_get_contents($this->_path);

		self::assertStringContainsString('# keep this', $content);
		self::assertStringContainsString('UNKNOWN_KEY=keep-me', $content);
		self::assertStringContainsString('PULSE_APP_NAME="Pulse Test"', $content);
		self::assertStringContainsString('PULSE_CRON_TOKEN=12345678901234567890123456789012', $content);
	}

	/** @brief Ensures complex secret values are decoded back to their original form. */
	public function testComplexSecretRoundTripsThroughFileReader(): void
	{
		file_put_contents($this->_path, "PULSE_SMTP_PASSWORD=old\n");
		$file = new EnvironmentFile($this->_path);
		$secret = 'a\\b"c # d';
		$file->Update(['PULSE_SMTP_PASSWORD' => $secret]);
		$values = $file->ReadValues();

		self::assertSame($secret, $values['PULSE_SMTP_PASSWORD']);
	}
}
