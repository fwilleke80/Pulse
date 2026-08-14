<?php

/**
 * @file UserRepository.php
 * @brief Repository for user accounts.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;

/**
 * @brief Repository for user accounts.
 */
class UserRepository
{
	private Database $_database;

	/**
	 * @brief Constructs the repository.
	 * @param Database $database Database service.
	 */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Finds a user by email address.
	 * @param string $email Email address.
	 * @return array<string, mixed>|null User row or null.
	 */
	public function FindByEmail(string $email): ?array
	{
		$sql = 'SELECT * FROM users WHERE email = :email LIMIT 1';
		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'email' => $email,
		]);

		$user = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($user) ? $user : null;
	}

	/**
	 * @brief Finds a user by ID.
	 * @param int $userId User ID.
	 * @return array<string, mixed>|null User row or null.
	 */
	public function FindById(int $userId): ?array
	{
		$sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $userId,
		]);

		$user = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($user) ? $user : null;
	}

	/**
	 * @brief Finds a different user with the given email address.
	 * @param int $excludeUserId User ID to exclude.
	 * @param string $email Email address.
	 * @return array<string, mixed>|null User row or null.
	 */
	public function FindByEmailExcludingUserId(int $excludeUserId, string $email): ?array
	{
		$sql = 'SELECT * FROM users WHERE email = :email AND id <> :id LIMIT 1';
		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'email' => $email,
			'id' => $excludeUserId,
		]);

		$user = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($user) ? $user : null;
	}

	/**
	 * @brief Updates the profile data of a user.
	 * @param int $userId User ID.
	 * @param string $displayName Display name.
	 * @param string $email Email address.
	 * @param string $notificationLocale Language for notifications sent to this user.
	 * @param string $websiteLocale Persistent website/UI language.
	 */
	public function UpdateProfile(int $userId, string $displayName, string $email, string $notificationLocale, string $websiteLocale): void
	{
		$sql = 'UPDATE users
			SET display_name = :display_name, email = :email, notification_locale = :notification_locale, website_locale = :website_locale
			WHERE id = :id';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'display_name' => $displayName,
			'email' => $email,
			'notification_locale' => $notificationLocale,
			'website_locale' => $websiteLocale,
			'id' => $userId,
		]);
	}

	/**
	 * @brief Updates only the persistent website/UI locale.
	 * @param int $userId User ID.
	 * @param string $websiteLocale Locale code.
	 */
	public function UpdateWebsiteLocale(int $userId, string $websiteLocale): void
	{
		$statement = $this->_database->GetConnection()->prepare(
			'UPDATE users SET website_locale = :website_locale WHERE id = :id'
		);
		$statement->execute([
			'website_locale' => $websiteLocale,
			'id' => $userId,
		]);
	}

	/**
	 * @brief Updates the password hash of a user.
	 * @param int $userId User ID.
	 * @param string $passwordHash Password hash.
	 */
	public function UpdatePasswordHash(int $userId, string $passwordHash): void
	{
		$sql = 'UPDATE users
			SET password_hash = :password_hash
			WHERE id = :id';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'password_hash' => $passwordHash,
			'id' => $userId,
		]);
	}

	/**
	 * @brief Updates the last login timestamp of a user.
	 * @param int $userId User ID.
	 */
	public function UpdateLastLoginAt(int $userId): void
	{
		$sql = 'UPDATE users SET last_login_at = NOW() WHERE id = :id';
		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $userId,
		]);
	}
}
