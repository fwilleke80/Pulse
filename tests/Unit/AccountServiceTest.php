<?php

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Repositories\AccountRepository;
use Pulse\Services\AccountService;
use RuntimeException;

final class AccountServiceTest extends TestCase
{
	/** @brief Repository. */
	private function Repository(): AccountRepository
	{
		$repository = $this->getMockBuilder(AccountRepository::class)->disableOriginalConstructor()->getMock();
		$repository->method('Transaction')->willReturnCallback(static fn (callable $operation): mixed => $operation());
		return $repository;
	}

	/** @brief User. */
	private function User(int $id, bool $owner = false): array
	{
		return ['id' => $id, 'email' => 'owner@example.com', 'role' => 'administrator', 'is_active' => 1, 'is_super_user' => $owner, 'email_verified_at' => '2026-01-01 00:00:00', 'approved_at' => '2026-01-01 00:00:00', 'auth_version' => 4];
	}

	/** @brief Test other administrator cannot reset owner. */
	public function testOtherAdministratorCannotResetOwner(): void
	{
		$repository = $this->Repository();
		$repository->method('FindById')->willReturnMap([[2, $this->User(2)], [1, $this->User(1, true)]]);
		$repository->expects(self::never())->method('Update');
		$repository->expects(self::never())->method('RevokePasskeys');
		$this->expectException(RuntimeException::class);
		(new AccountService($repository))->RequestPasswordReset(2, 1);
	}

	/** @brief Test owner cannot demote self. */
	public function testOwnerCannotDemoteSelf(): void
	{
		$repository = $this->Repository();
		$repository->method('FindById')->willReturn($this->User(1, true));
		$repository->expects(self::never())->method('Update');
		$this->expectException(RuntimeException::class);
		(new AccountService($repository))->AdminUpdate(1, 1, ['role' => 'user']);
	}

	/** @brief Test reset revokes passkeys and invalidates session. */
	public function testResetRevokesPasskeysAndInvalidatesSession(): void
	{
		$repository = $this->Repository();
		$repository->method('FindById')->willReturnMap([[1, $this->User(1, true)], [2, $this->User(2)]]);
		$repository->expects(self::once())->method('RevokePasskeys')->with(2);
		$repository->expects(self::once())->method('Update')->with(2, self::callback(static fn (array $fields): bool => $fields['auth_version'] === 5 && $fields['password_change_required'] === 1 && password_get_info($fields['password_hash'])['algoName'] !== 'unknown'));
		$repository->expects(self::once())->method('CreateToken')->with(2, 'password_reset', self::matchesRegularExpression('/^[a-f0-9]{64}$/'), 'owner@example.com', 3600);
		$repository->method('QueueMail')->willReturn(37);
		self::assertSame(37, (new AccountService($repository, ['base_url' => 'https://pulse.example']))->RequestPasswordReset(1, 2));
	}

	/** @brief Test registration defaults closed. */
	public function testRegistrationDefaultsClosed(): void
	{
		$repository = $this->Repository();
		$repository->expects(self::never())->method('Create');
		$this->expectException(RuntimeException::class);
		(new AccountService($repository))->Register([]);
	}

	/** @brief Test expired or consumed token cannot confirm. */
	public function testExpiredOrConsumedTokenCannotConfirm(): void
	{
		$repository = $this->Repository();
		$repository->method('FindToken')->willReturn(null);
		$repository->expects(self::never())->method('ConsumeToken');
		$repository->expects(self::never())->method('Update');
		$this->expectException(RuntimeException::class);
		(new AccountService($repository))->Confirm(str_repeat('a', 64));
	}

	/** @brief Test role change invalidates sessions. */
	public function testRoleChangeInvalidatesSessions(): void
	{
		$repository = $this->Repository();
		$repository->method('FindById')->willReturnMap([[1, $this->User(1, true)], [2, $this->User(2)]]);
		$repository->expects(self::once())->method('RevokeTokens')->with(2);
		$repository->expects(self::once())->method('Update')->with(2, ['role' => 'user', 'auth_version' => 5]);
		(new AccountService($repository))->AdminUpdate(1, 2, ['role' => 'user']);
	}
}
