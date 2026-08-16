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
	'name' => 'Pulse',
	'env' => $environment,
	'debug' => !$isProduction && Environment::GetBool('PULSE_DEBUG', false),
	'base_url' => rtrim(Environment::Get('PULSE_BASE_URL'), '/'),
	'timezone' => 'UTC',
	'display_timezone' => Environment::Get('PULSE_DISPLAY_TIMEZONE', 'Europe/Berlin'),
	'locale' => Environment::Get('PULSE_DEFAULT_LOCALE', 'de'),
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
		'passkey_quick_checkin_enabled' => Environment::GetBool('PULSE_PASSKEY_QUICK_CHECKIN_ENABLED', false),
	],
	'uploads' => [
		'maximum_bytes' => Environment::GetInt('PULSE_UPLOAD_MAXIMUM_BYTES', 26214400, 1024),
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
	'mail' => [
		'enabled' => Environment::GetBool('PULSE_MAIL_ENABLED', false),
		'host' => Environment::Get('PULSE_SMTP_HOST'),
		'port' => Environment::GetInt('PULSE_SMTP_PORT', 587, 1, 65535),
		'encryption' => strtolower(Environment::Get('PULSE_SMTP_ENCRYPTION', 'starttls')),
		'username' => Environment::Get('PULSE_SMTP_USERNAME'),
		'password' => Environment::Get('PULSE_SMTP_PASSWORD'),
		'from_address' => Environment::Get('PULSE_MAIL_FROM_ADDRESS'),
		'from_name' => Environment::Get('PULSE_MAIL_FROM_NAME', 'Pulse'),
		'timeout_seconds' => Environment::GetInt('PULSE_SMTP_TIMEOUT_SECONDS', 15, 2, 120),
		'max_attempts' => Environment::GetInt('PULSE_MAIL_MAX_ATTEMPTS', 5, 1, 20),
		'retry_delays_seconds' => Environment::GetIntList('PULSE_MAIL_RETRY_DELAYS_SECONDS', [60, 300, 1800, 7200]),
		'lease_seconds' => Environment::GetInt('PULSE_MAIL_LEASE_SECONDS', 120, 30, 1800),
		'worker_batch_size' => Environment::GetInt('PULSE_MAIL_WORKER_BATCH_SIZE', 25, 1, 250),
	],
	'cron' => [
		'token' => Environment::Get('PULSE_CRON_TOKEN'),
	],
];
