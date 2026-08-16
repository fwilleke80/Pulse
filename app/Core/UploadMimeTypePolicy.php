<?php

/**
 * @file UploadMimeTypePolicy.php
 * @brief Default and upgrade-safe MIME policy for monitor document uploads.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

/**
 * @brief Expands the former stock allowlist while preserving deliberately customized policies.
 */
final class UploadMimeTypePolicy
{
	/** @var array<int, string> */
	private const LEGACY_DEFAULTS = [
		'application/pdf',
		'application/rtf',
		'application/vnd.oasis.opendocument.text',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'image/jpeg',
		'image/png',
		'text/plain',
	];

	/** @var array<int, string> */
	private const DEFAULTS = [
		'application/pdf',
		'application/rtf',
		'application/vnd.oasis.opendocument.text',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/json',
		'application/csv',
		'application/ogg',
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/avif',
		'text/plain',
		'text/markdown',
		'text/x-markdown',
		'text/csv',
		'text/json',
		'audio/mpeg',
		'audio/mp4',
		'audio/x-m4a',
		'audio/aac',
		'audio/ogg',
		'audio/webm',
		'audio/wav',
		'audio/x-wav',
		'audio/flac',
		'audio/x-flac',
		'video/mp4',
		'video/webm',
		'video/quicktime',
		'video/ogg',
	];

	/** @return array<int, string> @brief Returns the current stock upload allowlist. */
	public static function Defaults(): array
	{
		return self::DEFAULTS;
	}

	/** @brief Returns the current stock allowlist in .env CSV form. */
	public static function DefaultsCsv(): string
	{
		return implode(',', self::DEFAULTS);
	}

	/**
	 * @brief Upgrades the untouched pre-1.2.6 stock list without widening a customized administrator policy.
	 * @param array<int, string> $configured Configured MIME values.
	 * @return array<int, string> Effective MIME values.
	 */
	public static function Resolve(array $configured): array
	{
		$normalized = self::Normalize($configured);

		if ($normalized === self::Normalize(self::LEGACY_DEFAULTS))
		{
			return self::DEFAULTS;
		}

		return array_values(array_unique(array_filter(array_map(
			static fn (string $value): string => strtolower(trim($value)),
			$configured
		))));
	}

	/** @param array<int, string> $values @return array<int, string> @brief Normalizes a MIME set for order-independent comparison. */
	private static function Normalize(array $values): array
	{
		$values = array_values(array_unique(array_filter(array_map(
			static fn (string $value): string => strtolower(trim($value)),
			$values
		))));
		sort($values);
		return $values;
	}
}
