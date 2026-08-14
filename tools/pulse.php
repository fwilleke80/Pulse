<?php

/**
 * @file pulse.php
 * @brief Pulse notification scheduler and queue-worker command line interface.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Repositories\MailQueueRepository;
use Pulse\Repositories\UserRepository;
use Pulse\Services\MailQueueWorker;
use Pulse\Services\NotificationScheduler;
use Pulse\Services\TestNotificationService;

if (PHP_SAPI !== 'cli')
{
	http_response_code(404);
	exit(1);
}

if (is_file(dirname(__DIR__) . '/public/install.php'))
{
	fwrite(STDERR, "Pulse installation is not finalized. Remove public/install.php before running background commands.\n");
	exit(1);
}

$container = require dirname(__DIR__) . '/bootstrap.php';
$arguments = array_slice($argv, 1);
$command = array_shift($arguments) ?? 'help';
$mailEnabled = (bool)$container['config']['mail']['enabled'];
$defaultLimit = (int)$container['config']['mail']['worker_batch_size'];

try
{
	$result = match ($command)
	{
		'notifications:run' => RunNotifications($container['notificationScheduler'], $container['mailQueueWorker'], $mailEnabled, OptionInt($arguments, '--limit', $defaultLimit)),
		'notifications:schedule' => ScheduleNotifications($container['notificationScheduler'], $mailEnabled),
		'mail:work' => WorkQueue($container['mailQueueWorker'], $mailEnabled, OptionInt($arguments, '--limit', $defaultLimit)),
		'mail:test' => TestMail($container['userRepository'], $container['testNotificationService'], OptionInt($arguments, '--user-id', 0)),
		'mail:retry-failed' => RetryFailed($container['mailQueueRepository'], OptionInt($arguments, '--limit', 100)),
		'help', '--help', '-h' => ['help' => HelpText()],
		default => throw new InvalidArgumentException('Unknown command: ' . $command),
	};

	if (isset($result['help']))
	{
		echo $result['help'] . PHP_EOL;
		exit(0);
	}

	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
	exit(0);
}
catch (Throwable $throwable)
{
	fwrite(STDERR, 'Pulse command failed: ' . $throwable->getMessage() . PHP_EOL);
	exit(1);
}

/** @brief Runs one complete cron tick. @return array<string, mixed> */
function RunNotifications(NotificationScheduler $scheduler, MailQueueWorker $worker, bool $enabled, int $limit): array
{
	AssertMailEnabled($enabled);
	return [
		'schedule' => $scheduler->Run(),
		'worker' => $worker->Process($limit),
	];
}

/** @brief Runs only lifecycle synchronization and owner-notification enqueueing. @return array<string, mixed> */
function ScheduleNotifications(NotificationScheduler $scheduler, bool $enabled): array
{
	AssertMailEnabled($enabled);
	return $scheduler->Run();
}

/** @brief Runs only the concurrent-safe queue worker. @return array<string, mixed> */
function WorkQueue(MailQueueWorker $worker, bool $enabled, int $limit): array
{
	AssertMailEnabled($enabled);
	return $worker->Process($limit);
}

/** @brief Sends an immediate test through the queue to one owner. @return array<string, mixed> */
function TestMail(UserRepository $users, TestNotificationService $tests, int $userId): array
{
	if ($userId <= 0)
	{
		throw new InvalidArgumentException('mail:test requires --user-id=ID.');
	}

	$user = $users->FindById($userId);

	if (!is_array($user))
	{
		throw new RuntimeException('User not found.');
	}

	$status = $tests->SendForUser($user);

	if ($status === 'disabled')
	{
		throw new RuntimeException('Mail delivery is disabled.');
	}

	return ['user_id' => $userId, 'status' => $status];
}

/** @brief Explicitly requeues permanently failed messages. @return array<string, int> */
function RetryFailed(MailQueueRepository $queue, int $limit): array
{
	return ['retried' => $queue->RetryFailed($limit)];
}

/** @brief Rejects notification cron commands while mail is intentionally disabled. */
function AssertMailEnabled(bool $enabled): void
{
	if (!$enabled)
	{
		throw new RuntimeException('Mail delivery is disabled. Configure SMTP and set PULSE_MAIL_ENABLED=true before testing or running cron.');
	}
}

/** @brief Reads a positive integer option in --name=value or --name value form. */
function OptionInt(array $arguments, string $name, int $default): int
{
	foreach ($arguments as $index => $argument)
	{
		if ($argument === $name && isset($arguments[$index + 1]))
		{
			return max(1, (int)$arguments[$index + 1]);
		}

		if (str_starts_with($argument, $name . '='))
		{
			return max(1, (int)substr($argument, strlen($name) + 1));
		}
	}

	return $default > 0 ? $default : 0;
}

/** @brief Returns command usage. */
function HelpText(): string
{
	return implode(PHP_EOL, [
		'Pulse notification commands:',
		'  php tools/pulse.php notifications:run [--limit=25]',
		'  php tools/pulse.php notifications:schedule',
		'  php tools/pulse.php mail:work [--limit=25]',
		'  php tools/pulse.php mail:test --user-id=1',
		'  php tools/pulse.php mail:retry-failed [--limit=100]',
	]);
}
