<?php

/** @file AccountService.php @brief Authorized account lifecycle and single-use mail links. */
declare(strict_types=1);

namespace Pulse\Services;

use InvalidArgumentException;
use Pulse\Repositories\AccountRepository;
use RuntimeException;

class AccountService
{
	/** @brief Config accepts app config plus registration_enabled / registration_requires_approval. */
	public function __construct(private AccountRepository $_repository, private array $_config = [])
	{
	}

	/** @brief Admin list. */
	public function AdminList(int $actorId): array
	{
		$this->RequireAdmin($actorId);
		return array_map($this->SafeAccount(...), $this->_repository->ListAll());
	}

	/** @brief Creates an approved but unverified account. Mail is sent only on explicit send_invite. */
	public function AdminCreate(int $actorId, array $data): array
	{
		return $this->_repository->Transaction(function () use ($actorId, $data): array
		{
			$this->RequireAdmin($actorId);
			$fields = $this->NewAccount($data);
			$fields['role'] = $this->Role((string)($data['role'] ?? 'user'));
			$fields['is_active'] = (int)$this->Flag($data['is_active'] ?? true);
			$fields['approved_at'] = $this->Flag($data['approved'] ?? true) ? gmdate('Y-m-d H:i:s') : null;
			$fields['totp_required'] = (int)$this->Flag($data['totp_required'] ?? false);
			$fields['password_change_required'] = 1;
			// An administrator can provision a usable account without sending an invitation.
			if ((string)($data['password'] ?? '') !== '')
			{
				$fields['email_verified_at'] = gmdate('Y-m-d H:i:s');
			}
			$id = $this->_repository->Create($fields);
			$this->_repository->Audit($actorId, $id, 'account.created');
			if ($this->Flag($data['send_invite'] ?? false))
			{
				$this->SendLink($this->Account($id), 'invite', $actorId);
			}
			return $this->SafeAccount($this->Account($id));
		});
	}

	/** @brief Editable fields: display_name, email, role, is_active, approved, totp_required. */
	public function AdminUpdate(int $actorId, int $userId, array $data): array
	{
		return $this->_repository->Transaction(function () use ($actorId, $userId, $data): array
		{
			$this->RequireAdmin($actorId);
			$user = $this->Account($userId);
			$this->ProtectOwner($actorId, $user);
			$fields = [];
			if (array_key_exists('display_name', $data))
			{
				$fields['display_name'] = $this->DisplayName((string)$data['display_name']);
			}
			if (array_key_exists('role', $data))
			{
				$fields['role'] = $this->Role((string)$data['role']);
			}
			foreach (['is_active', 'totp_required'] as $field)
			{
				if (array_key_exists($field, $data))
				{
					$fields[$field] = (int)$this->Flag($data[$field]);
				}
			}
			if (array_key_exists('approved', $data))
			{
				$fields['approved_at'] = $this->Flag($data['approved']) ? ($user['approved_at'] ?? gmdate('Y-m-d H:i:s')) : null;
			}
			if (!empty($user['is_super_user']) && (($fields['role'] ?? $user['role']) !== 'administrator' || ($fields['is_active'] ?? 1) !== 1 || (array_key_exists('approved_at', $fields) && $fields['approved_at'] === null)))
			{
				throw new RuntimeException('The superuser cannot be demoted, disabled or unapproved.');
			}
			if (isset($data['email']) && strtolower(trim((string)$data['email'])) !== strtolower((string)$user['email']))
			{
				$fields['email'] = $this->Email((string)$data['email'], $userId);
				$fields['email_verified_at'] = null;
				$fields['email_checked_at'] = null;
			}
			if ((string)($data['password'] ?? '') !== '')
			{
				$fields['password_hash'] = $this->PasswordHash((string)$data['password']);
				$fields['password_change_required'] = 1;
				$this->_repository->RevokePasskeys($userId);
			}
			$securityChanged = false;
			foreach (['role', 'is_active', 'totp_required', 'approved_at', 'email', 'password_hash'] as $field)
			{
				if (array_key_exists($field, $fields) && (string)$fields[$field] !== (string)($user[$field] ?? ''))
				{
					$securityChanged = true;
				}
			}
			if ($securityChanged)
			{
				$fields['auth_version'] = (int)$user['auth_version'] + 1;
				$this->_repository->RevokeTokens($userId);
			}
			$this->_repository->Update($userId, $fields);
			$this->_repository->Audit($actorId, $userId, 'account.updated');
			foreach (['role' => 'role_changed', 'password_hash' => 'password_reset', 'is_active' => 'login_access_changed', 'totp_required' => 'totp_requirement_changed', 'approved_at' => 'approval_changed', 'email' => 'email_changed'] as $field => $event)
			{
				if (array_key_exists($field, $fields) && (string)$fields[$field] !== (string)($user[$field] ?? ''))
				{
					$this->_repository->Audit($actorId, $userId, 'account.' . $event);
				}
			}

			if ($securityChanged)
			{
				$this->NotifySecurityChange($user);
			}
			return $this->SafeAccount($this->Account($userId));
		});
	}

	/** @brief Admin delete. */
	public function AdminDelete(int $actorId, int $userId): void
	{
		$files = $this->_repository->Transaction(function () use ($actorId, $userId): array
		{
			$this->RequireAdmin($actorId);
			$user = $this->Account($userId);
			if (!empty($user['is_super_user']) || $actorId === $userId)
			{
				throw new RuntimeException('The superuser and your own account cannot be deleted.');
			}
			$files = $this->_repository->StoredFiles($userId);
			$this->_repository->RevokeTokens($userId);
			$this->_repository->Delete($userId);
			$this->_repository->Audit($actorId, $userId, 'account.deleted');
			return $files;
		});
		$directory = dirname(__DIR__, 2) . '/storage/uploads/monitor-documents';
		foreach ($files as $file)
		{
			if (is_string($file) && preg_match('/\A[a-f0-9]{64}\.bin\z/D', $file))
			{
				@unlink($directory . '/' . $file);
			}
		}
	}

	/** @brief Invite. */
	public function Invite(int $actorId, int $userId): int
	{
		return $this->_repository->Transaction(function () use ($actorId, $userId): int
		{
			$this->RequireAdmin($actorId);
			$user = $this->Account($userId);
			$this->ProtectOwner($actorId, $user);
			return $this->SendLink($user, 'invite', $actorId);
		});
	}

	/** @brief Register. */
	public function Register(array $data): array
	{
		if (!$this->Flag($this->RegistrationSettings()['registration_enabled'] ?? false))
		{
			throw new RuntimeException('Registration is disabled.');
		}
		return $this->_repository->Transaction(function () use ($data): array
		{
			$fields = $this->NewAccount($data, true);
			$fields['approved_at'] = $this->Flag($this->RegistrationSettings()['registration_requires_approval'] ?? false) ? null : gmdate('Y-m-d H:i:s');
			$id = $this->_repository->Create($fields);
			$this->_repository->Audit($id, $id, 'account.registered');
			$this->SendLink($this->Account($id), 'verify', $id);
			return $this->SafeAccount($this->Account($id));
		});
	}

	/** @brief Confirms registration email only. Invitation links are consumed by SetPassword. */
	public function Confirm(string $token): array
	{
		return $this->_repository->Transaction(function () use ($token): array
		{
			[$link, $user] = $this->ResolveToken($token, ['verify']);
			$this->_repository->ConsumeToken((int)$link['id']);
			$this->_repository->Update((int)$user['id'], ['email_verified_at' => gmdate('Y-m-d H:i:s'), 'email_checked_at' => gmdate('Y-m-d H:i:s')]);
			$this->_repository->Audit((int)$user['id'], (int)$user['id'], 'account.email_verified');
			return $this->SafeAccount($this->Account((int)$user['id']));
		});
	}

	/** @brief Invitation acceptance also verifies the email. Reset never bypasses pending approval. */
	public function SetPassword(string $token, string $password): array
	{
		$hash = $this->PasswordHash($password);
		return $this->_repository->Transaction(function () use ($token, $hash): array
		{
			[$link, $user] = $this->ResolveToken($token, ['invite', 'password_reset']);
			$id = (int)$user['id'];
			$this->_repository->ConsumeToken((int)$link['id']);
			$this->_repository->RevokeTokens($id);
			$this->_repository->RevokePasskeys($id);
			$this->_repository->Update($id, ['password_hash' => $hash, 'password_change_required' => 0, 'email_verified_at' => gmdate('Y-m-d H:i:s'), 'email_checked_at' => gmdate('Y-m-d H:i:s'), 'auth_version' => (int)$user['auth_version'] + 1]);
			$this->_repository->Audit($id, $id, 'account.password_set');
			return $this->SafeAccount($this->Account($id));
		});
	}

	/** @brief Immediately invalidates old credentials and queues a single-use reset link. */
	public function RequestPasswordReset(int $actorId, int $userId): int
	{
		return $this->_repository->Transaction(function () use ($actorId, $userId): int
		{
			$this->RequireAdmin($actorId);
			$user = $this->Account($userId);
			$this->ProtectOwner($actorId, $user);
			$this->_repository->RevokeTokens($userId);
			$this->_repository->RevokePasskeys($userId);
			$this->_repository->Update($userId, ['password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), 'password_change_required' => 1, 'auth_version' => (int)$user['auth_version'] + 1]);
			return $this->SendLink($user, 'password_reset', $actorId);
		});
	}

	/** @brief Reset totp. */
	public function ResetTotp(int $actorId, int $userId, bool $required = false): void
	{
		$this->_repository->Transaction(function () use ($actorId, $userId, $required): void
		{
			$this->RequireAdmin($actorId);
			$user = $this->Account($userId);
			$this->ProtectOwner($actorId, $user);
			$this->_repository->ResetTotp($userId);
			$this->_repository->RevokeTokens($userId);
			$this->_repository->Update($userId, ['totp_required' => (int)$required, 'auth_version' => (int)$user['auth_version'] + 1]);
			$this->_repository->Audit($actorId, $userId, 'account.totp_reset');
			$this->NotifySecurityChange($user);
		});
	}

	/** @brief Returns current registration settings. */
	public function RegistrationSettings(): array
	{
		return $this->_repository->RegistrationSettings();
	}

	/** @brief Changes registration policy with an actor-attributed audit record. */
	public function UpdateRegistrationSettings(int $actorId, bool $enabled, bool $approval): void
	{
		$this->_repository->Transaction(function () use ($actorId, $enabled, $approval): void
		{
			$this->RequireAdmin($actorId);
			$this->_repository->UpdateRegistrationSettings($enabled, $approval);
			$this->_repository->Audit($actorId, $actorId, 'account.registration_settings_updated');
		});
	}

	/** @brief Notifies the previous address about administrative security changes without secrets. */
	private function NotifySecurityChange(array $user): void
	{
		$german = ($user['notification_locale'] ?? '') === 'de';
		$this->_repository->QueueMail((int)$user['id'], 'security_changed', (string)$user['email'],
			$german ? 'Die Sicherheitseinstellungen Ihres Pulse-Kontos wurden von einem Administrator geändert. Falls dies unerwartet ist, wenden Sie sich an den Betreiber.'
			: 'An administrator changed your Pulse account security settings. If this was unexpected, contact the installation operator.', bin2hex(random_bytes(32)));
	}

	/** @brief Require admin. */
	private function RequireAdmin(int $id): array
	{
		$user = $this->Account($id);
		if ($user['role'] !== 'administrator' || empty($user['is_active']) || empty($user['email_verified_at']) || empty($user['approved_at']))
		{
			throw new RuntimeException('Administrator access required.');
		}
		return $user;
	}

	/** @brief Protect owner. */
	private function ProtectOwner(int $actorId, array $user): void
	{
		if (!empty($user['is_super_user']) && $actorId !== (int)$user['id'])
		{
			throw new RuntimeException('Only the superuser can change their own account.');
		}
	}

	/** @brief Account. */
	private function Account(int $id): array
	{
		return $this->_repository->FindById($id) ?? throw new RuntimeException('Account not found.');
	}

	/** @brief Safe account. */
	private function SafeAccount(array $user): array
	{
		unset($user['password_hash'], $user['super_user_slot']);
		return $user;
	}

	/** @brief New account. */
	private function NewAccount(array $data, bool $passwordRequired = false): array
	{
		$password = (string)($data['password'] ?? '');
		return [
			'email' => $this->Email((string)($data['email'] ?? '')),
			'display_name' => $this->DisplayName((string)($data['display_name'] ?? '')),
			'password_hash' => $passwordRequired || $password !== '' ? $this->PasswordHash($password) : password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
			'role' => 'user', 'is_active' => 1, 'email_checked_at' => null, 'email_verified_at' => null,
			'notification_locale' => (string)($this->_config['locale'] ?? 'en'),
			'website_locale' => (string)($this->_config['locale'] ?? 'en'),
		];
	}

	/** @brief Email. */
	private function Email(string $email, int $excludeId = 0): string
	{
		$email = strtolower(trim($email));
		if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $this->_repository->EmailExists($email, $excludeId))
		{
			throw new InvalidArgumentException('A valid, unused email address is required.');
		}
		return $email;
	}

	/** @brief Display name. */
	private function DisplayName(string $name): string
	{
		$name = trim($name);
		if ($name === '' || strlen($name) > 255)
		{
			throw new InvalidArgumentException('A display name of 1–255 bytes is required.');
		}
		return $name;
	}

	/** @brief Role. */
	private function Role(string $role): string
	{
		if (!in_array($role, ['user', 'administrator'], true))
		{
			throw new InvalidArgumentException('Invalid account role.');
		}
		return $role;
	}

	/** @brief Flag. */
	private function Flag(mixed $value): bool
	{
		$result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		return $result ?? throw new InvalidArgumentException('Invalid boolean value.');
	}

	/** @brief Password hash. */
	private function PasswordHash(string $password): string
	{
		$minimum = max(8, (int)($this->_config['security']['password_minimum_length'] ?? 12));
		if (strlen($password) < $minimum || strlen($password) > 72 || str_contains($password, "\0"))
		{
			throw new InvalidArgumentException('Password must contain ' . $minimum . '–72 bytes and no NUL characters.');
		}
		return password_hash($password, PASSWORD_DEFAULT);
	}

	/** @brief Resolve token. */
	private function ResolveToken(string $token, array $purposes): array
	{
		if (!preg_match('/\A[a-f0-9]{64}\z/D', $token))
		{
			throw new RuntimeException('Invalid or expired account link.');
		}
		$link = $this->_repository->FindToken(hash('sha256', $token));
		if ($link === null || !in_array($link['purpose'], $purposes, true))
		{
			throw new RuntimeException('Invalid or expired account link.');
		}
		$user = $this->Account((int)$link['user_id']);
		if (empty($user['is_active']) || !hash_equals((string)$user['email'], (string)$link['email']))
		{
			throw new RuntimeException('Invalid or expired account link.');
		}
		return [$link, $user];
	}

	/** @brief Send link. */
	private function SendLink(array $user, string $purpose, int $actorId): int
	{
		if (empty($user['is_active']))
		{
			throw new RuntimeException('Cannot send an account link to a disabled account.');
		}
		$baseUrl = rtrim((string)($this->_config['base_url'] ?? ''), '/');
		if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['https', 'http'], true))
		{
			throw new RuntimeException('A configured absolute base_url is required for account mail.');
		}
		$id = (int)$user['id'];
		$this->_repository->RevokeTokens($id, $purpose);
		$token = bin2hex(random_bytes(32));
		$hash = hash('sha256', $token);
		$ttl = $purpose === 'password_reset' ? 3600 : 86400;
		$this->_repository->CreateToken($id, $purpose, $hash, (string)$user['email'], $ttl);
		$path = $purpose === 'verify' ? '/register/confirm' : '/account/set-password';
		$url = $baseUrl . $path . '?token=' . rawurlencode($token);
		$queueId = $this->_repository->QueueMail($id, $purpose, (string)$user['email'], (($user['notification_locale'] ?? '') === 'de' ? "Bestätigen Sie Ihre Pulse-Kontoanfrage über diesen einmaligen Link:\n\n" . $url . "\n\nGültig für " . ($ttl / 3600) . " Stunde(n)." : "Complete your Pulse account request using this single-use link:\n\n" . $url . "\n\nExpires in " . ($ttl / 3600) . " hour(s)."), $hash);
		$this->_repository->Audit($actorId, $id, 'account.' . $purpose . '_requested');
		return $queueId;
	}
}
