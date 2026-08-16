<?php

/**
 * @file EmailAddressCollection.php
 * @brief Shared handling for Pulse's bounded owner and contact email-address slots.
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Normalizes and reads the four separately checked email-address slots.
 */
final class EmailAddressCollection
{
	public const MAX_ADDRESSES = 4;

	/**
	 * @brief Returns every populated address in display order.
	 * @param array<string, mixed> $row User or contact database row.
	 * @return array<int, array{email: string, checked: bool, checked_at: string|null}>
	 */
	public static function FromRow(array $row): array
	{
		$result = [];

		for ($slot = 1; $slot <= self::MAX_ADDRESSES; $slot++)
		{
			$emailField = self::EmailField($slot);
			$checkedField = self::CheckedField($slot);
			$email = trim((string)($row[$emailField] ?? ''));

			if ($email === '')
			{
				continue;
			}

			$checkedAt = isset($row[$checkedField]) && (string)$row[$checkedField] !== ''
				? (string)$row[$checkedField]
				: null;
			$result[] = [
				'email' => $email,
				'checked' => $checkedAt !== null,
				'checked_at' => $checkedAt,
			];
		}

		return $result;
	}

	/**
	 * @brief Returns the distinct checked addresses eligible for delivery.
	 * @param array<string, mixed> $row User or contact database row.
	 * @return array<int, string>
	 */
	public static function Checked(array $row): array
	{
		$result = [];
		$seen = [];

		foreach (self::FromRow($row) as $address)
		{
			$key = strtolower($address['email']);

			if (!$address['checked'] || isset($seen[$key]))
			{
				continue;
			}

			$seen[$key] = true;
			$result[] = $address['email'];
		}

		return $result;
	}

	/**
	 * @brief Returns whether at least one checked delivery address exists.
	 * @param array<string, mixed> $row User or contact database row.
	 */
	public static function HasChecked(array $row): bool
	{
		return self::Checked($row) !== [];
	}

	/**
	 * @brief Removes empty slots, rejects duplicates, and compacts submitted addresses.
	 * @param array<int, array{email: string, checked: bool}> $addresses Raw submitted slots.
	 * @return array<int, array{email: string, checked: bool}>
	 */
	public static function Normalize(array $addresses): array
	{
		$result = [];
		$seen = [];

		foreach (array_slice($addresses, 0, self::MAX_ADDRESSES) as $address)
		{
			$email = trim((string)($address['email'] ?? ''));

			if ($email === '')
			{
				continue;
			}

			$key = strtolower($email);

			if (isset($seen[$key]))
			{
				throw new \InvalidArgumentException('Duplicate email address.');
			}

			$seen[$key] = true;
			$result[] = [
				'email' => $email,
				'checked' => !empty($address['checked']),
			];
		}

		return $result;
	}

	/** @brief Returns the database field containing an address slot. */
	public static function EmailField(int $slot): string
	{
		return $slot === 1 ? 'email' : 'email_' . $slot;
	}

	/** @brief Returns the database field containing a slot's checked timestamp. */
	public static function CheckedField(int $slot): string
	{
		return $slot === 1 ? 'email_checked_at' : 'email_' . $slot . '_checked_at';
	}
}
