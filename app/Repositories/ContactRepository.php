<?php

declare(strict_types=1);

namespace Pulse\Repositories;

use PDO;
use Pulse\Core\Database;

/**
 * @brief Repository for user contacts.
 */
class ContactRepository
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
	 * @brief Returns all contacts for a user.
	 * @param int $userId User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindAllByUserId(int $userId): array
	{
		$sql = '
			SELECT
				id,
				name,
				email,
				cell_phone,
				notes,
				created_at,
				updated_at
			FROM contacts
			WHERE user_id = :user_id
			ORDER BY name ASC
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'user_id' => $userId,
		]);

		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Creates a contact for a user.
	 * @param int $userId User ID.
	 * @param string $name Contact name.
	 * @param string $email Contact email.
	 * @param string|null $cellPhone Optional cell phone.
	 * @param string|null $notes Optional notes.
	 */
	public function CreateForUser(
		int $userId,
		string $name,
		string $email,
		?string $cellPhone,
		?string $notes
	): void
	{
		$sql = '
			INSERT INTO contacts
			(
				user_id,
				name,
				email,
				cell_phone,
				notes,
				created_at,
				updated_at
			)
			VALUES
			(
				:user_id,
				:name,
				:email,
				:cell_phone,
				:notes,
				NOW(),
				NOW()
			)
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'user_id' => $userId,
			'name' => $name,
			'email' => $email,
			'cell_phone' => $cellPhone,
			'notes' => $notes,
		]);
	}

	/**
	 * @brief Deletes a contact belonging to a user.
	 * @param int $contactId Contact ID.
	 * @param int $userId User ID.
	 */
	public function DeleteForUser(int $contactId, int $userId): void
	{
		$sql = '
			DELETE FROM contacts
			WHERE id = :id
			  AND user_id = :user_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $contactId,
			'user_id' => $userId,
		]);
	}
}