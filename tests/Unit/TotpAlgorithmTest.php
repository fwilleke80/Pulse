<?php

/**
 * @file TotpAlgorithmTest.php
 * @brief RFC 6238 interoperability checks for Pulse's local TOTP implementation.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pulse\Core\TotpAlgorithm;

final class TotpAlgorithmTest extends TestCase
{
	/** @return array<int, array{int,string}> @brief Returns the HMAC-SHA1 vectors published in RFC 6238 Appendix B. */
	public static function RfcVectors(): array
	{
		return [
			[59, '94287082'],
			[1111111109, '07081804'],
			[1111111111, '14050471'],
			[1234567890, '89005924'],
			[2000000000, '69279037'],
			[20000000000, '65353130'],
		];
	}

	#[DataProvider('RfcVectors')]
	public function testMatchesRfc6238Sha1Vectors(int $timestamp, string $expected): void
	{
		$algorithm = new TotpAlgorithm();
		$secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

		self::assertSame($expected, $algorithm->CodeAtCounter($secret, intdiv($timestamp, 30), 8));
	}

	public function testVerificationAcceptsOnlyConfiguredAdjacentTimeSteps(): void
	{
		$algorithm = new TotpAlgorithm();
		$secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
		$timestamp = 1234567890;
		$counter = intdiv($timestamp, TotpAlgorithm::PERIOD_SECONDS);
		$previousCode = $algorithm->CodeAtCounter($secret, $counter - 1);

		self::assertSame($counter - 1, $algorithm->Verify($secret, $previousCode, $timestamp));
		self::assertNull($algorithm->Verify($secret, $previousCode, $timestamp, 0));
		self::assertNull($algorithm->Verify($secret, 'not-a-code', $timestamp));
	}

	public function testGeneratedSecretsAndRecoveryCodesUseStandardBase32EntropyLengths(): void
	{
		$algorithm = new TotpAlgorithm();

		self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $algorithm->GenerateSecret());
		self::assertMatchesRegularExpression('/^[A-Z2-7]{16}$/', $algorithm->GenerateRecoveryCode());
	}
}
