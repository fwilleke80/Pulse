<?php

/**
 * @file TotpAlgorithm.php
 * @brief RFC 6238-compatible six-digit time-based one-time passwords.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use InvalidArgumentException;

/**
 * @brief Generates and verifies standard Base32/HMAC-SHA1 TOTP values.
 */
final class TotpAlgorithm
{
	public const DIGITS = 6;
	public const PERIOD_SECONDS = 30;
	public const CLOCK_WINDOW_STEPS = 1;

	private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/** @brief Generates a 160-bit authenticator secret in unpadded Base32 form. */
	public function GenerateSecret(): string
	{
		return $this->Base32Encode(random_bytes(20));
	}

	/** @brief Generates an 80-bit one-time recovery code in unpadded Base32 form. */
	public function GenerateRecoveryCode(): string
	{
		return $this->Base32Encode(random_bytes(10));
	}

	/**
	 * @brief Returns the accepted counter for a code, or null when it is invalid.
	 * @param string $secret Base32 authenticator secret.
	 * @param string $code Submitted six-digit code.
	 * @param int|null $timestamp Unix timestamp, or now when omitted.
	 * @param int $window Number of adjacent time steps accepted on either side.
	 */
	public function Verify(string $secret, string $code, ?int $timestamp = null, int $window = self::CLOCK_WINDOW_STEPS): ?int
	{
		$code = preg_replace('/[\s-]+/', '', trim($code)) ?? '';

		if (preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1)
		{
			return null;
		}

		$currentCounter = intdiv($timestamp ?? time(), self::PERIOD_SECONDS);
		$window = max(0, min(10, $window));

		for ($offset = -$window; $offset <= $window; $offset++)
		{
			$counter = $currentCounter + $offset;

			if ($counter >= 0 && hash_equals($this->CodeAtCounter($secret, $counter), $code))
			{
				return $counter;
			}
		}

		return null;
	}

	/**
	 * @brief Generates an HOTP value for a moving counter.
	 * @param string $secret Base32 authenticator secret.
	 * @param int $counter Non-negative moving counter.
	 * @param int $digits Output digit count, primarily exposed for RFC test vectors.
	 */
	public function CodeAtCounter(string $secret, int $counter, int $digits = self::DIGITS): string
	{
		if ($counter < 0 || $digits < 6 || $digits > 8)
		{
			throw new InvalidArgumentException('Invalid TOTP counter or digit count.');
		}

		$key = $this->Base32Decode($secret);
		$high = intdiv($counter, 4294967296);
		$low = $counter % 4294967296;
		$hash = hash_hmac('sha1', pack('N2', $high, $low), $key, true);
		$offset = ord($hash[strlen($hash) - 1]) & 0x0F;
		$value = ((ord($hash[$offset]) & 0x7F) << 24)
			| ((ord($hash[$offset + 1]) & 0xFF) << 16)
			| ((ord($hash[$offset + 2]) & 0xFF) << 8)
			| (ord($hash[$offset + 3]) & 0xFF);
		$modulus = 10 ** $digits;

		return str_pad((string)($value % $modulus), $digits, '0', STR_PAD_LEFT);
	}

	/** @brief Encodes bytes using the RFC 4648 Base32 alphabet without padding. */
	private function Base32Encode(string $value): string
	{
		$buffer = 0;
		$bits = 0;
		$result = '';

		for ($index = 0; $index < strlen($value); $index++)
		{
			$buffer = ($buffer << 8) | ord($value[$index]);
			$bits += 8;

			while ($bits >= 5)
			{
				$bits -= 5;
				$result .= self::BASE32_ALPHABET[($buffer >> $bits) & 0x1F];
			}

			$buffer = $bits > 0 ? $buffer & ((1 << $bits) - 1) : 0;
		}

		if ($bits > 0)
		{
			$result .= self::BASE32_ALPHABET[($buffer << (5 - $bits)) & 0x1F];
		}

		return $result;
	}

	/** @brief Decodes an unpadded RFC 4648 Base32 value. */
	private function Base32Decode(string $value): string
	{
		$value = strtoupper(preg_replace('/[\s=-]+/', '', trim($value)) ?? '');

		if ($value === '')
		{
			throw new InvalidArgumentException('The TOTP secret is empty.');
		}

		$buffer = 0;
		$bits = 0;
		$result = '';

		for ($index = 0; $index < strlen($value); $index++)
		{
			$position = strpos(self::BASE32_ALPHABET, $value[$index]);

			if ($position === false)
			{
				throw new InvalidArgumentException('The TOTP secret is not valid Base32.');
			}

			$buffer = ($buffer << 5) | $position;
			$bits += 5;

			if ($bits >= 8)
			{
				$bits -= 8;
				$result .= chr(($buffer >> $bits) & 0xFF);
			}

			$buffer = $bits > 0 ? $buffer & ((1 << $bits) - 1) : 0;
		}

		return $result;
	}
}
