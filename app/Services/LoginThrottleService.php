<?php

/**
 * @file LoginThrottleService.php
 * @brief Account- and network-scoped login throttling.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Repositories\LoginThrottleRepository;

/**
 * @brief Applies login limits without persisting plaintext email or IP values.
 */
class LoginThrottleService
{
	private LoginThrottleRepository $_repository;

	/** @var array<string, mixed> */
	private array $_config;

	/** @brief Constructs the service. @param LoginThrottleRepository $repository Repository. @param array<string, mixed> $config Security configuration. */
	public function __construct(LoginThrottleRepository $repository, array $config)
	{
		$this->_repository = $repository;
		$this->_config = $config;
	}

	/** @brief Returns whether either the account or client address is blocked. @param string $email Submitted email. @param string $clientIp Direct client IP. @return bool */
	public function IsBlocked(string $email, string $clientIp): bool
	{
		foreach ($this->Keys($email, $clientIp) as $key)
		{
			if ($this->_repository->IsBlocked($key))
			{
				return true;
			}
		}

		return false;
	}

	/** @brief Records a failed attempt for the account and client address. @param string $email Submitted email. @param string $clientIp Direct client IP. */
	public function RecordFailure(string $email, string $clientIp): void
	{
		foreach ($this->Keys($email, $clientIp) as $key)
		{
			$this->_repository->RecordFailure(
				$key,
				(int)$this->_config['login_max_attempts'],
				(int)$this->_config['login_window_seconds'],
				(int)$this->_config['login_block_seconds']
			);
		}
	}

	/** @brief Clears failures after successful authentication. @param string $email Submitted email. @param string $clientIp Direct client IP. */
	public function Clear(string $email, string $clientIp): void
	{
		foreach ($this->Keys($email, $clientIp) as $key)
		{
			$this->_repository->Clear($key);
		}
	}

	/** @brief Creates separate opaque account and network keys. @param string $email Submitted email. @param string $clientIp Direct client IP. @return array<int, string> */
	private function Keys(string $email, string $clientIp): array
	{
		return [
			hash('sha256', 'account:' . strtolower(trim($email))),
			hash('sha256', 'network:' . $clientIp),
		];
	}
}
