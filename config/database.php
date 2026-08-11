<?php

/**
 * @file database.php
 * @brief Environment-backed database configuration.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Core\Environment;

return [
	'host' => Environment::Get('PULSE_DB_HOST', 'localhost'),
	'port' => Environment::GetInt('PULSE_DB_PORT', 3306, 1, 65535),
	'database' => Environment::Get('PULSE_DB_DATABASE'),
	'username' => Environment::Get('PULSE_DB_USERNAME'),
	'password' => Environment::Get('PULSE_DB_PASSWORD'),
	'charset' => 'utf8mb4',
];
