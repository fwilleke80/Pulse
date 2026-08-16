<?php

/**
 * @file SecurityAttemptThrottleService.php
 * @brief Account- and network-scoped throttling for TOTP and recovery-code attempts.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Repositories\LoginThrottleRepository;

/**
 * @brief Reuses the opaque login-attempt store with security-action-specific keys.
 */
final class SecurityAttemptThrottleService
{
	private LoginThrottleRepository $_repository;

	/** @var array<string, mixed> */
	private array $_config;

	/** @brief Constructs the throttle. */
	public function __construct(LoginThrottleRepository $repository, array $config)
	{
		$this->_repository = $repository;
		$this->_config = $config;
	}

	/** @brief Returns whether either the account or network key is blocked for this security scope. */
	public function IsBlocked(string $scope, int $userId, string $clientIp): bool
	{
		foreach ($this->Keys($scope, $userId, $clientIp) as $key)
		{
			if ($this->_repository->IsBlocked($key))
			{
				return true;
			}
		}

		return false;
	}

	/** @brief Records a failed TOTP, recovery-code, or management re-authentication attempt. */
	public function RecordFailure(string $scope, int $userId, string $clientIp): void
	{
		foreach ($this->Keys($scope, $userId, $clientIp) as $key)
		{
			$this->_repository->RecordFailure(
				$key,
				(int)$this->_config['totp_max_attempts'],
				(int)$this->_config['totp_window_seconds'],
				(int)$this->_config['totp_block_seconds']
			);
		}
	}

	/** @brief Clears accumulated failures after the scoped verification succeeds. */
	public function Clear(string $scope, int $userId, string $clientIp): void
	{
		foreach ($this->Keys($scope, $userId, $clientIp) as $key)
		{
			$this->_repository->Clear($key);
		}
	}

	/** @return array<int, string> @brief Creates opaque account and network keys isolated by operation scope. */
	private function Keys(string $scope, int $userId, string $clientIp): array
	{
		$scope = preg_replace('/[^a-z0-9_.-]+/i', '', strtolower($scope)) ?? 'totp';

		return [
			hash('sha256', 'security:' . $scope . ':account:' . max(0, $userId)),
			hash('sha256', 'security:' . $scope . ':network:' . $clientIp),
		];
	}
}
