<?php

/**
 * @file MaintenanceArchivesTest.php
 * @brief Regression coverage for Pulse backup archives, staged updates and private Markdown exports.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use Phar;
use PharData;
use PHPUnit\Framework\TestCase;
use Pulse\Services\BackupService;
use Pulse\Services\DocumentExcerptService;
use Pulse\Services\UpdateInstallerService;
use ReflectionClass;
use RuntimeException;

/** @brief Checks archive encryption, name validation, and conservative update-package policy. */
final class MaintenanceArchivesTest extends TestCase
{
	/** @brief Rejects path traversal and unknown backup members. */
	public function testBackupPathsCannotEscapePrivateStorage(): void
	{
		self::assertTrue(BackupService::AllowedEntry('database/pulse.sql'));
		self::assertTrue(BackupService::AllowedEntry('config/.env'));
		self::assertTrue(BackupService::AllowedEntry('storage/uploads/monitor-documents/abc.dat'));
		self::assertFalse(BackupService::AllowedEntry('../.env'));
		self::assertFalse(BackupService::AllowedEntry('storage/uploads/../../public/index.php'));
		self::assertFalse(BackupService::AllowedEntry('storage/uploads/../private'));
		self::assertFalse(BackupService::AllowedEntry('public/index.php'));
	}

	/** @brief Update packages never replace application-owned private storage or installation credentials. */
	public function testReleaseFilePolicyRejectsPrivateAndTraversalPaths(): void
	{
		self::assertTrue(UpdateInstallerService::AllowedPackageFile('app/Services/BackupService.php'));
		self::assertTrue(UpdateInstallerService::AllowedPackageFile('public/index.php'));
		self::assertFalse(UpdateInstallerService::AllowedPackageFile('storage/uploads/document.pdf'));
		self::assertFalse(UpdateInstallerService::AllowedPackageFile('.env'));
		self::assertFalse(UpdateInstallerService::AllowedPackageFile('app/../../.env'));
		self::assertFalse(UpdateInstallerService::AllowedPackageFile('vendor/autoload.php'));
	}

	/** @brief Excerpts use rendered plain text, preserve Unicode and avoid duplicating the full document. */
	public function testMarkdownExcerptShortensVisibleText(): void
	{
		$source = '# Personal note' . "\n\n" . str_repeat('Hello **Schöne** world. ', 30);
		$text = DocumentExcerptService::FromMarkdown($source, 90);
		self::assertStringStartsWith('Personal note Hello Schöne world.', $text);
		self::assertStringEndsWith('…', $text);
		self::assertLessThanOrEqual(95, count(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []));
		self::assertSame('Short <text>', DocumentExcerptService::FromMarkdown('Short <text>'));
	}

	/** @brief AES-GCM encryption authenticates an independently archived SQL/data payload. */
	public function testEncryptedBackupRoundTripAndWrongPassword(): void
	{
		$root = sys_get_temp_dir() . '/pulse-test-' . bin2hex(random_bytes(8));
		self::assertTrue(mkdir($root, 0700));
		try
		{
			$source = $root . '/original.zip';
			$archive = new PharData($source, 0, null, Phar::ZIP);
			$archive->addFromString('database/pulse.sql', 'SELECT 1;');
			$archive->addFromString('config/.env', 'PULSE_TOTP_ENCRYPTION_KEY=test');
			unset($archive);
			$service = (new ReflectionClass(BackupService::class))->newInstanceWithoutConstructor();
			$descriptor = (new ReflectionClass(BackupService::class))->getMethod('Encrypt')
				->invoke($service, $source, $root . '/secret.bin', 'strong-example-password');
			(new ReflectionClass(BackupService::class))->getMethod('Decrypt')->invoke(
				$service, $root . '/secret.bin', $root . '/opened.zip', 'strong-example-password', $descriptor
			);
			self::assertSame(hash_file('sha256', $source), hash_file('sha256', $root . '/opened.zip'));
			$this->expectException(RuntimeException::class);
			(new ReflectionClass(BackupService::class))->getMethod('Decrypt')->invoke(
				$service, $root . '/secret.bin', $root . '/bad.zip', 'wrong-password', $descriptor
			);
		}
		finally
		{
			foreach (glob($root . '/*') ?: [] as $file)
			{
				@unlink($file);
			}
			@rmdir($root);
		}
	}
}
