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
use Pulse\Core\EmailAddressCollection;

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
				email_checked_at,
				email_2,
				email_2_checked_at,
				email_3,
				email_3_checked_at,
				email_4,
				email_4_checked_at,
				notification_locale,
				cell_phone,
				notes,
				created_at,
				updated_at,
				(
					SELECT COUNT(*)
					FROM monitor_contacts mc
					INNER JOIN monitors m ON m.id = mc.monitor_id
					WHERE mc.contact_id = contacts.id
					  AND m.user_id = contacts.user_id
				) + (
					SELECT COUNT(*)
					FROM monitor_safety_contacts msc
					INNER JOIN monitors sm ON sm.id = msc.monitor_id
					WHERE msc.contact_id = contacts.id
					  AND sm.user_id = contacts.user_id
				) AS monitor_reference_count
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
	 * @param array<int, array{email: string, checked: bool}> $addresses Contact email addresses.
	 * @param string $notificationLocale Language for notifications sent to this contact.
	 * @param string|null $cellPhone Optional cell phone.
	 * @param string|null $notes Optional notes.
	 */
	public function CreateForUser(
		int $userId,
		string $name,
		array $addresses,
		string $notificationLocale,
		?string $cellPhone,
		?string $notes
	): void
	{
		$bindings = $this->AddressBindings($addresses);
		$sql = '
			INSERT INTO contacts
			(
				user_id,
				name,
				email,
				email_checked_at,
				email_2,
				email_2_checked_at,
				email_3,
				email_3_checked_at,
				email_4,
				email_4_checked_at,
				notification_locale,
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
				CASE WHEN :email_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				:email_2,
				CASE WHEN :email_2_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				:email_3,
				CASE WHEN :email_3_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				:email_4,
				CASE WHEN :email_4_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				:notification_locale,
				:cell_phone,
				:notes,
				NOW(),
				NOW()
			)
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute(array_merge([
			'user_id' => $userId,
			'name' => $name,
			'notification_locale' => $notificationLocale,
			'cell_phone' => $cellPhone,
			'notes' => $notes,
		], $bindings));
	}

	/**
	 * @brief Returns whether a contact is still assigned to any owned monitor.
	 * @param int $contactId Contact ID.
	 * @param int $userId Owner user ID.
	 * @return bool True when the contact is a recipient or safety contact on a monitor.
	 */
	public function IsReferencedByMonitorForUser(int $contactId, int $userId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT 1
			FROM contacts c
			WHERE c.id = :contact_id
			  AND c.user_id = :user_id
			  AND (
				EXISTS (
					SELECT 1
					FROM monitor_contacts mc
					INNER JOIN monitors m ON m.id = mc.monitor_id
					WHERE mc.contact_id = c.id
					  AND m.user_id = c.user_id
				)
				OR EXISTS (
					SELECT 1
					FROM monitor_safety_contacts msc
					INNER JOIN monitors m ON m.id = msc.monitor_id
					WHERE msc.contact_id = c.id
					  AND m.user_id = c.user_id
				)
			  )
			LIMIT 1
		');
		$statement->execute([
			'contact_id' => $contactId,
			'user_id' => $userId,
		]);

		return $statement->fetchColumn() !== false;
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
				email_checked_at,
				email_2,
				email_2_checked_at,
				email_3,
				email_3_checked_at,
				email_4,
				email_4_checked_at,
				notification_locale,
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
	 * @param array<int, array{email: string, checked: bool}> $addresses Contact email addresses.
	 * @param string $notificationLocale Language for notifications sent to this contact.
	 * @param string|null $cellPhone Optional cell phone.
	 * @param string|null $notes Optional notes.
	 */
	public function UpdateForUser(
		int $contactId,
		int $userId,
		string $name,
		array $addresses,
		string $notificationLocale,
		?string $cellPhone,
		?string $notes
	): void
	{
		$bindings = $this->AddressBindings($addresses);
		$sql = '
			UPDATE contacts
			SET
				name = :name,
				email = :email,
				email_checked_at = CASE WHEN :email_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				email_2 = :email_2,
				email_2_checked_at = CASE WHEN :email_2_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				email_3 = :email_3,
				email_3_checked_at = CASE WHEN :email_3_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				email_4 = :email_4,
				email_4_checked_at = CASE WHEN :email_4_checked = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
				notification_locale = :notification_locale,
				cell_phone = :cell_phone,
				notes = :notes,
				updated_at = NOW()
			WHERE id = :id
			  AND user_id = :user_id
		';

		$statement = $this->_database->GetConnection()->prepare($sql);
		$statement->execute(array_merge([
			'id' => $contactId,
			'user_id' => $userId,
			'name' => $name,
			'notification_locale' => $notificationLocale,
			'cell_phone' => $cellPhone,
			'notes' => $notes,
		], $bindings));
	}

	/**
	 * @brief Builds fixed SQL bindings for the compacted four-address model.
	 * @param array<int, array{email: string, checked: bool}> $addresses Normalized addresses.
	 * @return array<string, string|int|null>
	 */
	private function AddressBindings(array $addresses): array
	{
		$bindings = [];

		for ($slot = 1; $slot <= EmailAddressCollection::MAX_ADDRESSES; $slot++)
		{
			$address = $addresses[$slot - 1] ?? null;
			$emailField = EmailAddressCollection::EmailField($slot);
			$checkedField = $slot === 1 ? 'email_checked' : 'email_' . $slot . '_checked';
			$bindings[$emailField] = is_array($address) ? (string)$address['email'] : null;
			$bindings[$checkedField] = is_array($address) && !empty($address['checked']) ? 1 : 0;
		}

		return $bindings;
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
