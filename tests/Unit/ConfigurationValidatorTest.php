<?php

/**
 * @file ConfigurationValidatorTest.php
 * @brief Tests fail-closed production configuration validation.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\ConfigurationValidator;
use RuntimeException;

class ConfigurationValidatorTest extends TestCase
{
	public function testProductionRejectsInsecureBaseUrl(): void
	{
		$this->expectException(RuntimeException::class);
		ConfigurationValidator::Validate(
			[
				'env' => 'production',
				'base_url' => 'http://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
				'session' => ['cookie_secure' => true],
				'security' => ['trusted_hosts' => ['pulse.example.com']],
			],
			['database' => 'pulse', 'username' => 'pulse', 'password' => 'secret']
		);
	}

	public function testDevelopmentAcceptsLocalHttpConfiguration(): void
	{
		ConfigurationValidator::Validate(
			[
				'env' => 'development',
				'base_url' => 'http://localhost',
				'display_timezone' => 'Europe/Berlin',
			],
			['database' => 'pulse_test', 'username' => 'pulse', 'password' => '']
		);

		self::addToAssertionCount(1);
	}

	public function testProductionRejectsUnencryptedSmtp(): void
	{
		$this->expectException(RuntimeException::class);
		ConfigurationValidator::Validate(
			[
				'env' => 'production',
				'base_url' => 'https://pulse.example.com',
				'display_timezone' => 'Europe/Berlin',
				'session' => ['cookie_secure' => true],
				'security' => ['trusted_hosts' => ['pulse.example.com']],
				'mail' => [
					'enabled' => true,
					'host' => 'smtp.example.com',
					'encryption' => 'none',
					'from_address' => 'pulse@example.com',
					'from_name' => 'Pulse',
					'username' => '',
					'password' => '',
					'retry_delays_seconds' => [60],
				],
			],
			['database' => 'pulse', 'username' => 'pulse', 'password' => 'secret']
		);
	}

	public function testDisabledMailDoesNotRequireSmtpCredentials(): void
	{
		ConfigurationValidator::Validate(
			[
				'env' => 'development',
				'base_url' => 'http://localhost',
				'display_timezone' => 'Europe/Berlin',
				'mail' => ['enabled' => false],
			],
			['database' => 'pulse_test', 'username' => 'pulse', 'password' => '']
		);

		self::addToAssertionCount(1);
	}
}
