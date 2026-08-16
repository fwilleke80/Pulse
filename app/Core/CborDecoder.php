<?php

/**
 * @file CborDecoder.php
 * @brief Minimal definite-length CBOR decoder for WebAuthn attestation data.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Decodes the CBOR types required by WebAuthn attestation objects and COSE keys.
 */
final class CborDecoder
{
	/**
	 * @brief Decodes one complete CBOR value.
	 * @param string $data Binary CBOR input.
	 * @return mixed Decoded value.
	 */
	public function Decode(string $data): mixed
	{
		$offset = 0;
		$value = $this->DecodeAt($data, $offset);

		if ($offset !== strlen($data))
		{
			throw new RuntimeException('Unexpected trailing CBOR data.');
		}

		return $value;
	}

	/**
	 * @brief Decodes one CBOR value starting at an offset and advances the offset.
	 * @param string $data Binary CBOR input.
	 * @param int $offset Current byte offset, updated on return.
	 * @return mixed Decoded value.
	 */
	public function DecodeAt(string $data, int &$offset): mixed
	{
		if ($offset >= strlen($data))
		{
			throw new RuntimeException('Unexpected end of CBOR data.');
		}

		$initial = ord($data[$offset++]);
		$major = $initial >> 5;
		$additional = $initial & 0x1f;

		if ($additional === 31)
		{
			throw new RuntimeException('Indefinite-length CBOR is not supported.');
		}

		$length = $this->ReadLength($data, $offset, $additional);

		return match ($major)
		{
			0 => $length,
			1 => -1 - $length,
			2 => $this->ReadBytes($data, $offset, $length),
			3 => $this->ReadBytes($data, $offset, $length),
			4 => $this->ReadArray($data, $offset, $length),
			5 => $this->ReadMap($data, $offset, $length),
			6 => $this->DecodeAt($data, $offset),
			7 => $this->DecodeSimple($additional, $length),
			default => throw new RuntimeException('Unsupported CBOR major type.'),
		};
	}

	/** @brief Reads a CBOR additional-length value. */
	private function ReadLength(string $data, int &$offset, int $additional): int
	{
		if ($additional < 24)
		{
			return $additional;
		}

		$bytes = match ($additional)
		{
			24 => 1,
			25 => 2,
			26 => 4,
			27 => 8,
			default => throw new RuntimeException('Unsupported CBOR additional information.'),
		};
		$raw = $this->ReadBytes($data, $offset, $bytes);
		$value = 0;

		for ($index = 0; $index < $bytes; ++$index)
		{
			$value = ($value << 8) | ord($raw[$index]);
		}

		if ($value < 0)
		{
			throw new RuntimeException('CBOR integer exceeds the supported range.');
		}

		return $value;
	}

	/** @brief Reads an exact byte count and advances the offset. */
	private function ReadBytes(string $data, int &$offset, int $length): string
	{
		if ($length < 0 || $offset + $length > strlen($data))
		{
			throw new RuntimeException('Truncated CBOR value.');
		}

		$value = substr($data, $offset, $length);
		$offset += $length;
		return $value;
	}

	/** @return array<int, mixed> @brief Reads a definite-length CBOR array. */
	private function ReadArray(string $data, int &$offset, int $length): array
	{
		$result = [];

		for ($index = 0; $index < $length; ++$index)
		{
			$result[] = $this->DecodeAt($data, $offset);
		}

		return $result;
	}

	/** @return array<int|string, mixed> @brief Reads a definite-length CBOR map. */
	private function ReadMap(string $data, int &$offset, int $length): array
	{
		$result = [];

		for ($index = 0; $index < $length; ++$index)
		{
			$key = $this->DecodeAt($data, $offset);
			$value = $this->DecodeAt($data, $offset);

			if (!is_int($key) && !is_string($key))
			{
				throw new RuntimeException('Unsupported CBOR map key type.');
			}

			$result[$key] = $value;
		}

		return $result;
	}

	/** @brief Decodes the simple values needed by WebAuthn CBOR. */
	private function DecodeSimple(int $additional, int $value): mixed
	{
		return match ($additional)
		{
			20 => false,
			21 => true,
			22, 23 => null,
			default => throw new RuntimeException('Unsupported CBOR simple value.'),
		};
	}
}
