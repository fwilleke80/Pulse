<?php

/**
 * @file SecurityFoundationTest.php
 * @brief Static regression checks for the security foundation.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SecurityFoundationTest extends TestCase
{
	public function testEveryPostFormContainsCsrfField(): void
	{
		foreach ($this->PhpFiles(dirname(__DIR__, 2) . '/app/Views') as $path)
		{
			$content = (string)file_get_contents($path);
			preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/si', $content, $matches);

			foreach ($matches[1] as $formBody)
			{
				self::assertStringContainsString('csrf_field()', $formBody, 'Missing CSRF field in ' . $path);
			}
		}
	}

	public function testControllersDoNotReadInputGlobalsDirectly(): void
	{
		foreach ($this->PhpFiles(dirname(__DIR__, 2) . '/app/Controllers') as $path)
		{
			$content = (string)file_get_contents($path);
			self::assertDoesNotMatchRegularExpression('/\$_(?:GET|POST|FILES|SERVER)/', $content, $path);
		}
	}

	public function testSourceContainsNoCommittedCredentialValuesOrInlineHandlers(): void
	{
		$projectRoot = dirname(__DIR__, 2);
		$databaseConfig = (string)file_get_contents($projectRoot . '/config/database.php');
		self::assertStringContainsString("Environment::Get('PULSE_DB_PASSWORD')", $databaseConfig);
		self::assertDoesNotMatchRegularExpression("/'password'\s*=>\s*'[^']+'/", $databaseConfig);

		foreach ($this->PhpFiles($projectRoot . '/app/Views') as $path)
		{
			$content = (string)file_get_contents($path);
			self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=|<script\b(?![^>]*src=)/i', $content, $path);
		}
	}

	/** @return array<int, string> */
	private function PhpFiles(string $directory): array
	{
		$result = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

		foreach ($iterator as $file)
		{
			if ($file->isFile() && $file->getExtension() === 'php')
			{
				$result[] = $file->getPathname();
			}
		}

		return $result;
	}
}
