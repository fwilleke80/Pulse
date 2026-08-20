<?php

/**
 * @file CronDiagnosticsSourceTest.php
 * @brief Static regression checks for cron token-change and failure diagnostics.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CronDiagnosticsSourceTest extends TestCase
{
	/** @brief Ensures unsuccessful web-cron calls are recorded while invalid authentication keeps its opaque 404 response. */
	public function testFailedCronCallsAreRecordedWithBoundedTokenDiagnostics(): void
	{
		$root = dirname(__DIR__, 2);
		$cron = (string)file_get_contents($root . '/public/cron/cron.php');
		$authenticator = (string)file_get_contents($root . '/app/Core/WebCronAuthenticator.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/SystemStatusRepository.php');

		self::assertStringContainsString('DiagnosticToken($providedToken)', $cron);
		self::assertStringContainsString("'invalid_token'", $cron);
		self::assertStringContainsString("'mail_disabled'", $cron);
		self::assertStringContainsString("'execution_error'", $cron);
		self::assertStringContainsString('RecordFailedCronCall(', $cron);
		self::assertStringContainsString('http_response_code(404)', $cron);
		self::assertStringContainsString('MAXIMUM_DIAGNOSTIC_TOKEN_LENGTH = 512', $authenticator);
		self::assertStringContainsString('MAXIMUM_CRON_FAILURES = 50', $repository);
	}

	/** @brief Ensures the persisted cron token receives a change timestamp only after its value actually changes. */
	public function testCronTokenChangeTimestampUsesActualValueChange(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/AdministrationController.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/SystemStatusRepository.php');

		self::assertStringContainsString('!hash_equals($previousFileCronToken, $fileCronToken)', $controller);
		self::assertStringContainsString('RecordCronTokenChanged()', $controller);
		self::assertStringContainsString('CronTokenChangedAt()', $repository);
	}

	/** @brief Ensures Administration exposes cron change time and unsuccessful-call evidence to administrators. */
	public function testAdministrationShowsCronDiagnostics(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/AdministrationController.php');
		$view = (string)file_get_contents($root . '/app/Views/administration/index.php');
		$migration = (string)file_get_contents($root . '/database/migrations/007_cron_diagnostics.sql');

		self::assertStringContainsString('RecentFailedCronCalls(20)', $controller);
		self::assertStringContainsString('administration.cron.token_changed', $view);
		self::assertStringContainsString('administration.cron.failures_reason', $view);
		self::assertStringContainsString('administration.cron.failures_token', $view);
		self::assertStringContainsString('e((string)$failedToken)', $view);
		self::assertStringContainsString('CREATE TABLE cron_failures', $migration);
		self::assertStringContainsString('failure_code VARCHAR(32)', $migration);
		self::assertStringContainsString('cron_token_changed_at', $migration);
	}
}
