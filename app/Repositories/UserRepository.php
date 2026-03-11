<?php

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