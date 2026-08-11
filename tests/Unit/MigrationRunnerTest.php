<?php

/**
 * @file MigrationRunnerTest.php
 * @brief Tests SQL migration script parsing.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\MigrationRunner;

class MigrationRunnerTest extends TestCase
{
	public function testBootstrapRunsMigrationsAutomatically(): void
	{
		$bootstrap = (string)file_get_contents(dirname(__DIR__, 2) . '/bootstrap.php');

		self::assertStringContainsString('new MigrationRunner(', $bootstrap);
		self::assertStringContainsString('$migrationRunner->Migrate();', $bootstrap);
	}

	public function testRunnerProtectsActualMigrationsWithAnAdvisoryLock(): void
	{
		$source = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Core/MigrationRunner.php');

		self::assertStringContainsString('GET_LOCK(', $source);
		self::assertStringContainsString('RELEASE_LOCK(', $source);
		self::assertStringContainsString('RequiresMigration(', $source);
	}

	public function testSplitStatementsIgnoresSemicolonsInStringsAndComments(): void
	{
		$sql = <<<'SQL'
			-- Comment with ;
			CREATE TABLE sample (value VARCHAR(50));
			INSERT INTO sample (value) VALUES ('one;two');
			/* block ; comment */
			UPDATE sample SET value = "three;four";
		SQL;

		$statements = MigrationRunner::SplitStatements($sql);

		self::assertCount(3, $statements);
		self::assertStringContainsString("'one;two'", $statements[1]);
		self::assertStringContainsString('"three;four"', $statements[2]);
	}
}
