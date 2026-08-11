<?php

/**
 * @file ContactRepository.php
 * @brief Repository for user contacts.
 * @author Frank Willeke
 */

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
				notification_locale,
				email_checked_at,
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
	 * @param string $notificationLocale Language for notifications sent to this contact.
	 * @param bool $emailChecked Whether the owner confirmed checking the address.
	 * @param string|null $cellPhone Optional cell phone.
	 * @param string|null $notes Optional notes.
	 */
	public function CreateForUser(
		int $userId,
		string $name,
		string $email,
		string $notificationLocale,
		bool $emailChecked,
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
				notification_locale,
				email_checked_at,
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
				:notification_locale,
				CASE WHEN :email_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
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
			'notification_locale' => $notificationLocale,
			'email_checked' => $emailChecked ? 1 : 0,
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

	/**
	 * @brief Finds a contact by ID for a specific user.
	 * @param int $contactId Contact ID.
	 * @param int $userId User ID.
	 * @return array<string, mixed>|null Contact row or null.
	 */
	public function FindByIdForUser(int $contactId, int $userId): ?array
	{
		$sql = '
			SELECT
				id,
				name,
				email,
				notification_locale,
				email_checked_at,
				cell_phone,
				notes,
				created_at,
				updated_at
			FROM contacts
			WHERE id = :id
			  AND user_id = :user_id
			LIMIT 1
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $contactId,
			'user_id' => $userId,
		]);

		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Updates a contact belonging to a user.
	 * @param int $contactId Contact ID.
	 * @param int $userId User ID.
	 * @param string $name Contact name.
	 * @param string $email Contact email.
	 * @param string $notificationLocale Language for notifications sent to this contact.
	 * @param bool $emailChecked Whether the owner confirmed checking the address.
	 * @param string|null $cellPhone Optional cell phone.
	 * @param string|null $notes Optional notes.
	 */
	public function UpdateForUser(
		int $contactId,
		int $userId,
		string $name,
		string $email,
		string $notificationLocale,
		bool $emailChecked,
		?string $cellPhone,
		?string $notes
	): void
	{
		$sql = '
			UPDATE contacts
			SET
				name = :name,
				email = :email,
				notification_locale = :notification_locale,
				email_checked_at = CASE WHEN :email_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				cell_phone = :cell_phone,
				notes = :notes,
				updated_at = NOW()
			WHERE id = :id
			  AND user_id = :user_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'id' => $contactId,
			'user_id' => $userId,
			'name' => $name,
			'email' => $email,
			'notification_locale' => $notificationLocale,
			'email_checked' => $emailChecked ? 1 : 0,
			'cell_phone' => $cellPhone,
			'notes' => $notes,
		]);
	}

	/**
	 * @brief Returns the number of contacts for a user.
	 * @param int $userId User ID.
	 * @return int
	 */
	public function CountByUserId(int $userId): int
	{
		$sql = '
			SELECT COUNT(*) AS contact_count
			FROM contacts
			WHERE user_id = :user_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute([
			'user_id' => $userId,
		]);

		$value = $statement->fetchColumn();

		return is_numeric($value) ? (int)$value : 0;
	}
}
