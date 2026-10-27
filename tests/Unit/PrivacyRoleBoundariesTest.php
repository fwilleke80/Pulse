<?php

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PrivacyRoleBoundariesTest extends TestCase
{
	/** Execute in isolation because the behavioral harness supplies authentication and rendering doubles. */
	public function testRoleAndControllerBoundaries(): void
	{
		$command = [PHP_BINARY];
		$ini = php_ini_loaded_file();
		if (is_string($ini))
		{
			$command[] = '-c';
			$command[] = $ini;
		}
		$command[] = dirname(__DIR__) . '/security/privacy_boundaries.php';
		$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		self::assertIsResource($process);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		self::assertSame(0, proc_close($process), (string)$error);
		self::assertStringContainsString('PASS: 144 behavioral role and controller boundary checks', (string)$output);
	}
}
