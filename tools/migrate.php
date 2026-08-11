<?php

/**
 * @file migrate.php
 * @brief Optional command-line report for Pulse's automatic database migrations.
 * @author Frank Willeke
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli')
{
	http_response_code(404);
	exit;
}

$container = require dirname(__DIR__) . '/bootstrap.php';
$applied = $container['appliedMigrations'];

if ($applied === [])
{
	echo "Database is already up to date.\n";
	exit(0);
}

foreach ($applied as $migration)
{
	echo 'Applied ' . $migration . "\n";
}
