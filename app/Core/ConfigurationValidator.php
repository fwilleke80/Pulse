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
}
