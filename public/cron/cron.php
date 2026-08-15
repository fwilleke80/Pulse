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

if (!WebCronAuthenticator::IsConfigured($configuredToken))
{
	http_response_code(503);
	echo "Pulse web cron is not configured.\n";
	exit;
}

if (!WebCronAuthenticator::IsAuthorized((string)($_SERVER['REQUEST_METHOD'] ?? ''), $_GET['token'] ?? null, $configuredToken))
{
	http_response_code(404);
	echo "Not found.\n";
	exit;
}

define('PULSE_BACKGROUND_REQUEST', true);
$container = require $projectRoot . '/bootstrap.php';

if (!(bool)$container['config']['mail']['enabled'])
{
	http_response_code(503);
	echo "Pulse mail delivery is disabled.\n";
	exit;
}

$scheduleResult = $container['notificationScheduler']->Run();
$mailResult = $container['mailQueueWorker']->Process((int)$container['config']['mail']['worker_batch_size']);
$container['systemStatusRepository']->RecordSuccessfulCronRun();
$container['logger']->Info('Web cron notification run completed', [
	'schedule' => $scheduleResult,
	'mail_queue' => $mailResult,
]);

echo "OK\n";
