<?php

/**
 * @file ConfigurationValidator.php
 * @brief Fail-closed validation for Pulse runtime configuration.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use DateTimeZone;
use RuntimeException;

/**
 * @brief Rejects incomplete or unsafe deployment configuration before request handling.
 */
final class ConfigurationValidator
{
	/**
	 * @brief Validates application and database configuration.
	 * @param array<string, mixed> $appConfig Application configuration.
	 * @param array<string, mixed> $databaseConfig Database configuration.
	 */
	public static function Validate(array $appConfig, array $databaseConfig): void
	{
		$environment = (string)($appConfig['env'] ?? '');

		if (!in_array($environment, ['production', 'development', 'testing'], true))
		{
			throw new RuntimeException('PULSE_ENV must be production, development, or testing.');
		}

		if ((string)($databaseConfig['database'] ?? '') === '' || (string)($databaseConfig['username'] ?? '') === '')
		{
			throw new RuntimeException('Database name and username must be configured.');
		}

		$displayTimezone = (string)($appConfig['display_timezone'] ?? '');

		try
		{
			new DateTimeZone($displayTimezone);
		}
		catch (\Throwable $throwable)
		{
			throw new RuntimeException('PULSE_DISPLAY_TIMEZONE is invalid.', 0, $throwable);
		}

		$baseUrl = (string)($appConfig['base_url'] ?? '');

		if ($baseUrl !== '')
		{
			$parts = parse_url($baseUrl);

			if (
				$parts === false
				|| !isset($parts['scheme'], $parts['host'])
				|| isset($parts['user'])
				|| isset($parts['pass'])
				|| isset($parts['query'])
				|| isset($parts['fragment'])
				|| (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')
			)
			{
				throw new RuntimeException('PULSE_BASE_URL must be an origin without a path, query, or credentials.');
			}
		}

		self::ValidateMail((array)($appConfig['mail'] ?? []), $environment);

		if ($environment !== 'production')
		{
			return;
		}

		$trustedHosts = (array)($appConfig['security']['trusted_hosts'] ?? []);
		$baseUrlScheme = strtolower((string)(parse_url($baseUrl, PHP_URL_SCHEME) ?? ''));
		$baseUrlHost = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?? ''));

		if ($baseUrl === '' || $baseUrlScheme !== 'https')
		{
			throw new RuntimeException('Production requires an HTTPS PULSE_BASE_URL.');
		}

		if ($trustedHosts === [] || !in_array($baseUrlHost, $trustedHosts, true))
		{
			throw new RuntimeException('Production PULSE_TRUSTED_HOSTS must include the PULSE_BASE_URL host.');
		}

		if (!(bool)($appConfig['session']['cookie_secure'] ?? false))
		{
			throw new RuntimeException('Production session cookies must be Secure.');
		}

		if ((string)($databaseConfig['password'] ?? '') === '')
		{
			throw new RuntimeException('Production requires a database password.');
		}
	}

	/**
	 * @brief Validates SMTP and queue configuration when mail delivery is enabled.
	 * @param array<string, mixed> $mail Mail configuration.
	 */
	private static function ValidateMail(array $mail, string $environment): void
	{
		if (!(bool)($mail['enabled'] ?? false))
		{
			return;
		}

		if (trim((string)($mail['host'] ?? '')) === '')
		{
			throw new RuntimeException('PULSE_SMTP_HOST is required when mail is enabled.');
		}

		if (!in_array((string)($mail['encryption'] ?? ''), ['starttls', 'tls', 'none'], true))
		{
			throw new RuntimeException('PULSE_SMTP_ENCRYPTION must be starttls, tls, or none.');
		}

		if ($environment === 'production' && (string)$mail['encryption'] === 'none')
		{
			throw new RuntimeException('Production SMTP delivery requires TLS or STARTTLS.');
		}

		$host = (string)$mail['host'];

		if (
			filter_var($host, FILTER_VALIDATE_IP) === false
			&& preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host) !== 1
		)
		{
			throw new RuntimeException('PULSE_SMTP_HOST must be a valid hostname or IP address.');
		}

		$fromAddress = (string)($mail['from_address'] ?? '');

		if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false)
		{
			throw new RuntimeException('PULSE_MAIL_FROM_ADDRESS must be a valid email address.');
		}

		if (strpbrk((string)($mail['from_name'] ?? ''), "\r\n") !== false)
		{
			throw new RuntimeException('PULSE_MAIL_FROM_NAME must not contain line breaks.');
		}

		$username = (string)($mail['username'] ?? '');
		$password = (string)($mail['password'] ?? '');

		if (($username === '') !== ($password === ''))
		{
			throw new RuntimeException('PULSE_SMTP_USERNAME and PULSE_SMTP_PASSWORD must either both be set or both be empty.');
		}

		$retryDelays = (array)($mail['retry_delays_seconds'] ?? []);

		if ($retryDelays === [])
		{
			throw new RuntimeException('PULSE_MAIL_RETRY_DELAYS_SECONDS must contain at least one delay.');
		}
	}
}
