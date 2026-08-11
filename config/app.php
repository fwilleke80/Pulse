<?php

/**
 * @file app.php
 * @brief Environment-backed application configuration.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Core\Environment;

$environment = strtolower(Environment::Get('PULSE_ENV', 'production'));
$isProduction = $environment === 'production';
$trustedHosts = array_map('strtolower', Environment::GetList('PULSE_TRUSTED_HOSTS'));

return [
	'name' => Environment::Get('PULSE_APP_NAME', 'Pulse'),
	'env' => $environment,
	'debug' => !$isProduction && Environment::GetBool('PULSE_DEBUG', false),
	'base_url' => rtrim(Environment::Get('PULSE_BASE_URL'), '/'),
	'timezone' => 'UTC',
	'display_timezone' => Environment::Get('PULSE_DISPLAY_TIMEZONE', 'Europe/Berlin'),
	'locale' => Environment::Get('PULSE_DEFAULT_LOCALE', 'de'),
	'available_locales' => ['en', 'de'],
	'session' => [
		'name' => Environment::Get('PULSE_SESSION_NAME', 'pulse_session'),
		'cookie_secure' => Environment::GetBool('PULSE_COOKIE_SECURE', $isProduction),
		'cookie_samesite' => Environment::Get('PULSE_COOKIE_SAMESITE', 'Strict'),
		'idle_timeout_seconds' => Environment::GetInt('PULSE_SESSION_IDLE_TIMEOUT', 1800, 300),
		'absolute_timeout_seconds' => Environment::GetInt('PULSE_SESSION_ABSOLUTE_TIMEOUT', 43200, 1800),
		'regeneration_interval_seconds' => Environment::GetInt('PULSE_SESSION_REGENERATION_INTERVAL', 900, 60),
	],
	'security' => [
		'trusted_hosts' => $trustedHosts,
		'hsts_enabled' => Environment::GetBool('PULSE_HSTS_ENABLED', $isProduction),
		'login_max_attempts' => Environment::GetInt('PULSE_LOGIN_MAX_ATTEMPTS', 5, 2, 50),
		'login_window_seconds' => Environment::GetInt('PULSE_LOGIN_WINDOW_SECONDS', 900, 60),
		'login_block_seconds' => Environment::GetInt('PULSE_LOGIN_BLOCK_SECONDS', 900, 60),
		'password_minimum_length' => Environment::GetInt('PULSE_PASSWORD_MINIMUM_LENGTH', 12, 8, 128),
	],
	'uploads' => [
		'maximum_bytes' => Environment::GetInt('PULSE_UPLOAD_MAXIMUM_BYTES', 26214400, 1024, 104857600),
		'allowed_mime_types' => Environment::GetList('PULSE_UPLOAD_ALLOWED_MIME_TYPES', [
			'application/pdf',
			'application/rtf',
			'application/vnd.oasis.opendocument.text',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'image/jpeg',
			'image/png',
			'text/plain',
		]),
	],
	'development' => [
		'allow_force_due' => !$isProduction && Environment::GetBool('PULSE_ALLOW_FORCE_DUE', false),
	],
];
