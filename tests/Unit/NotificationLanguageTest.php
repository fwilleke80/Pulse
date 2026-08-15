<?php

/**
 * @file NotificationLanguageTest.php
 * @brief Tests recipient-specific notification language validation and fallback.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\NotificationLanguage;

class NotificationLanguageTest extends TestCase
{
	public function testStoredRecipientLanguageWins(): void
	{
		$languages = new NotificationLanguage(['en', 'de'], 'de');
		self::assertSame('en', $languages->Resolve('en'));
	}

	public function testUnsupportedValueFallsBackToDeploymentDefault(): void
	{
		$languages = new NotificationLanguage(['en', 'de'], 'de');
		self::assertSame('de', $languages->Resolve(null));
		self::assertSame('de', $languages->Resolve('fr'));
	}
}
