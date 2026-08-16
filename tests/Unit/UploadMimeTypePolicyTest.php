<?php

/**
 * @file UploadMimeTypePolicyTest.php
 * @brief Upgrade-safety checks for the expanded stock upload policy.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\UploadMimeTypePolicy;

final class UploadMimeTypePolicyTest extends TestCase
{
	/** @brief Expands only the exact former stock policy used by older installations. */
	public function testLegacyStockPolicyExpands(): void
	{
		$legacy = [
			'application/pdf',
			'application/rtf',
			'application/vnd.oasis.opendocument.text',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'image/jpeg',
			'image/png',
			'text/plain',
		];

		$resolved = UploadMimeTypePolicy::Resolve(array_reverse($legacy));

		self::assertContains('audio/mpeg', $resolved);
		self::assertContains('video/mp4', $resolved);
		self::assertContains('text/csv', $resolved);
		self::assertContains('application/json', $resolved);
	}

	/** @brief Preserves a deliberately narrow or otherwise customized administrator policy. */
	public function testCustomPolicyIsPreserved(): void
	{
		$custom = ['application/pdf', 'image/jpeg'];

		self::assertSame($custom, UploadMimeTypePolicy::Resolve($custom));
	}
}
