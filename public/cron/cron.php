<?php

/**
 * @file cron.php
 * @brief Token-protected web entry point for one complete notification run.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Core\Environment;
use Pulse\Core\WebCronAuthenticator;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$projectRoot = dirname(__DIR__, 2);

if (is_file(dirname(__DIR__) . '/install.php'))
{
	http_response_code(503);
	echo "Pulse installation is not finalized.\n";
	exit;
}

require_once $projectRoot . '/app/Core/Environment.php';
require_once $projectRoot . '/app/Core/WebCronAuthenticator.php';

Environment::Load($projectRoot . '/.env');
$configuredToken = Environment::Get('PULSE_CRON_TOKEN');
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? '');
$providedToken = $_GET['token'] ?? null;

define('PULSE_BACKGROUND_REQUEST', true);

/**
 * @brief Persists one cron failure without allowing diagnostics to change the endpoint response.
 * @param array<string, mixed> $container Pulse service container.
 * @param string $failureCode Stable failure code.
 * @param string $method HTTP request method.
 * @param string|null $token Supplied invalid token excerpt, when relevant.
 * @param bool $tokenTruncated Whether the stored token was truncated.
 */
$recordFailure = static function (array $container, string $failureCode, string $method, ?string $token, bool $tokenTruncated): void
{
	try
	{
		$container['systemStatusRepository']->RecordFailedCronCall($failureCode, $method, $token, $tokenTruncated);
	}
	catch (\Throwable $exception)
	{
		$container['logger']->Warning('Could not record failed web cron call', [
			'failure_code' => $failureCode,
			'error' => $exception->getMessage(),
		]);
	}
};

if (!WebCronAuthenticator::IsConfigured($configuredToken))
{
	$diagnosticToken = WebCronAuthenticator::DiagnosticToken($providedToken);

	try
	{
		$container = require $projectRoot . '/bootstrap.php';
		$recordFailure(
			$container,
			'token_not_configured',
			$requestMethod,
			$diagnosticToken['token'],
			$diagnosticToken['truncated']
		);
	}
	catch (\Throwable)
	{
		// Keep the public response minimal even when diagnostics cannot be persisted.
	}

	http_response_code(503);
	echo "Pulse web cron is not configured.\n";
	exit;
}

$tokenValid = WebCronAuthenticator::IsTokenValid($providedToken, $configuredToken);

if ($requestMethod !== 'GET' || !$tokenValid)
{
	$diagnosticToken = !$tokenValid
		? WebCronAuthenticator::DiagnosticToken($providedToken)
		: ['token' => null, 'truncated' => false];
	$failureCode = !$tokenValid ? 'invalid_token' : 'invalid_method';

	try
	{
		$container = require $projectRoot . '/bootstrap.php';
		$recordFailure(
			$container,
			$failureCode,
			$requestMethod,
			$diagnosticToken['token'],
			$diagnosticToken['truncated']
		);
		$container['logger']->Warning('Rejected web cron request', [
			'failure_code' => $failureCode,
			'method' => $requestMethod,
			'token_truncated' => $diagnosticToken['truncated'],
		]);
	}
	catch (\Throwable)
	{
		// Authentication failures deliberately retain the same opaque response.
	}

	http_response_code(404);
	echo "Not found.\n";
	exit;
}

$container = require $projectRoot . '/bootstrap.php';

if (!(bool)$container['config']['mail']['enabled'])
{
	$recordFailure($container, 'mail_disabled', $requestMethod, null, false);
	http_response_code(503);
	echo "Pulse mail delivery is disabled.\n";
	exit;
}

try
{
	$scheduleResult = $container['notificationScheduler']->Run();
	$mailResult = $container['mailQueueWorker']->Process((int)$container['config']['mail']['worker_batch_size']);
	$container['systemStatusRepository']->RecordSuccessfulCronRun();
	$container['logger']->Info('Web cron notification run completed', [
		'schedule' => $scheduleResult,
		'mail_queue' => $mailResult,
	]);
}
catch (\Throwable $exception)
{
	$recordFailure($container, 'execution_error', $requestMethod, null, false);
	$container['logger']->Error('Web cron notification run failed', [
		'error' => $exception->getMessage(),
	]);
	http_response_code(500);
	echo "Pulse cron run failed.\n";
	exit;
}

echo "OK\n";
