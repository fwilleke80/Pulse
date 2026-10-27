<?php
/** @file AccountLifecycleTest.php @brief Behavioral coverage of verification, approval and optional invitations. */
declare(strict_types=1);
namespace Pulse\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Pulse\Repositories\AccountRepository;
use Pulse\Services\AccountService;
use RuntimeException;

/** @brief Transactional in-memory account fixture; token expiry is covered separately by repository SQL. */
class LifecycleAccounts extends AccountRepository
{
	public array $users = [];
	public array $tokens = [];
	public array $mail = [];
	public array $settings = ['registration_enabled' => true, 'registration_requires_approval' => true];
	/** @brief Constructs this account component. */
	public function __construct()
	{
		$this->users[1] = ['id' => 1, 'email' => 'owner@example.test', 'display_name' => 'Owner', 'role' => 'administrator', 'is_super_user' => 1, 'is_active' => 1, 'email_verified_at' => '2026-01-01', 'approved_at' => '2026-01-01', 'auth_version' => 1];
	}
	/** @brief Transaction. */
	public function Transaction(callable $operation): mixed
	{
		$before = [$this->users, $this->tokens, $this->mail];
		try
		{
			return $operation();
		}
		catch (\Throwable $error)
		{
			[$this->users, $this->tokens, $this->mail] = $before;
			throw $error;
		}
	}
	/** @brief Registration settings. */
	public function RegistrationSettings(): array
	{
		return $this->settings;
	}
	/** @brief Find by id. */
	public function FindById(int $id): ?array
	{
		return $this->users[$id] ?? null;
	}
	/** @brief Email exists. */
	public function EmailExists(string $email, int $excludeId = 0): bool
	{
		foreach ($this->users as $id => $user)
		{
			if ($id !== $excludeId && $user['email'] === $email)
			{
				return true;
			}
		}
		return false;
	}
	/** @brief Create. */
	public function Create(array $fields): int
	{
		$id = count($this->users) + 1;
		$this->users[$id] = $fields + ['id' => $id, 'is_super_user' => 0, 'auth_version' => 1, 'approved_at' => null, 'password_change_required' => 0, 'totp_required' => 0];
		return $id;
	}
	/** @brief Update. */
	public function Update(int $id, array $fields): void
	{
		$this->users[$id] = array_replace($this->users[$id], $fields);
	}
	/** @brief Create token. */
	public function CreateToken(int $id, string $purpose, string $hash, string $email, int $ttl): void
	{
		$this->tokens[$hash] = ['id' => count($this->tokens) + 1, 'user_id' => $id, 'purpose' => $purpose, 'email' => $email];
	}
	/** @brief Find token. */
	public function FindToken(string $hash): ?array
	{
		return $this->tokens[$hash] ?? null;
	}
	/** @brief Consume token. */
	public function ConsumeToken(int $id): void
	{
		foreach ($this->tokens as $hash => $token)
		{
			if ($token['id'] === $id)
			{
				unset($this->tokens[$hash]); return;
			}
		}
		throw new RuntimeException('Consumed token');
	}
	/** @brief Revoke tokens. */
	public function RevokeTokens(int $id, ?string $purpose = null): void
	{
		foreach ($this->tokens as $hash => $token)
		{
			if ($token['user_id'] === $id && ($purpose === null || $token['purpose'] === $purpose))
			{
				unset($this->tokens[$hash]);
			}
		}
	}
	/** @brief Revoke passkeys. */
	public function RevokePasskeys(int $id): void
	{
	}
	/** @brief Audit. */
	public function Audit(int $actorId, int $targetId, string $event): void
	{
	}
	/** @brief Queue mail. */
	public function QueueMail(int $id, string $purpose, string $email, string $body, string $key): int
	{
		$this->mail[] = compact('id', 'purpose', 'email', 'body');
		return count($this->mail);
	}
}

/** @brief Exercises account flows through the real service rather than duplicating its implementation. */
final class AccountLifecycleTest extends TestCase
{
	/** @brief Service. */
	private function Service(LifecycleAccounts $repository): AccountService
	{
		return new AccountService($repository, ['base_url' => 'https://pulse.example.test']);
	}
	/** @brief Input. */
	private function Input(): array
	{
		return ['email' => 'person@example.test', 'display_name' => '<img src=x onerror=alert(1)>', 'password' => 'Initial-password-123', 'role' => 'administrator', 'is_super_user' => true, 'approved' => true];
	}
	/** @brief Token. */
	private function Token(LifecycleAccounts $repository): string
	{
		preg_match('/token=([a-f0-9]{64})/', end($repository->mail)['body'], $matches);
		return $matches[1];
	}
	/** @brief Test public role cannot be elevated and both activation conditions are required. */
	public function testPublicRoleCannotBeElevatedAndBothActivationConditionsAreRequired(): void
	{
		$r = new LifecycleAccounts(); $s = $this->Service($r);
		$user = $s->Register($this->Input());
		self::assertSame('user', $user['role']);
		self::assertSame(0, $user['is_super_user']);
		self::assertNull($user['approved_at']);
		self::assertNull($user['email_verified_at']);
		$token = $this->Token($r);
		$verified = $s->Confirm($token);
		self::assertNotEmpty($verified['email_verified_at']);
		self::assertNull($verified['approved_at']);
		$approved = $s->AdminUpdate(1, (int)$user['id'], ['approved' => true]);
		self::assertNotEmpty($approved['approved_at']);
		$this->expectException(RuntimeException::class);
		$s->Confirm($token);
	}
	/** @brief Test admin password provisioning does not send invitation. */
	public function testAdminPasswordProvisioningDoesNotSendInvitation(): void
	{
		$r = new LifecycleAccounts(); $s = $this->Service($r);
		$user = $s->AdminCreate(1, $this->Input());
		self::assertSame([], $r->mail);
		self::assertNotEmpty($user['email_verified_at']);
		self::assertSame(1, $user['password_change_required']);
		self::assertArrayNotHasKey('password_hash', $user);
		$s->Invite(1, (int)$user['id']);
		self::assertCount(1, $r->mail);
		$accepted = $s->SetPassword($this->Token($r), 'User-chosen-password-456');
		self::assertSame(0, $accepted['password_change_required']);
		self::assertSame(2, $accepted['auth_version']);
	}
	/** @brief Test invitation does not bypass approval and replacement revokes old link. */
	public function testInvitationDoesNotBypassApprovalAndReplacementRevokesOldLink(): void
	{
		$r = new LifecycleAccounts(); $s = $this->Service($r);
		$user = $s->AdminCreate(1, array_replace($this->Input(), ['password' => '', 'approved' => false]));
		self::assertNull($user['email_verified_at']);
		$s->Invite(1, (int)$user['id']); $old = $this->Token($r);
		$s->Invite(1, (int)$user['id']); $new = $this->Token($r);
		self::assertNotSame($old, $new);
		$accepted = $s->SetPassword($new, 'User-chosen-password-456');
		self::assertNull($accepted['approved_at']);
		$this->expectException(RuntimeException::class);
		$s->SetPassword($old, 'User-chosen-password-789');
	}
}
