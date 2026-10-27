<?php

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pulse\Tests\Support\AuthenticationRegressionCases;

require_once dirname(__DIR__) . '/Support/AuthenticationRegressionCases.php';

final class AuthenticationRegressionTest extends TestCase
{
	/** @brief Behavior cases. */
	public static function behaviorCases(): iterable
	{
		foreach (AuthenticationRegressionCases::cases() as $name => $case)
		{
			yield $name => [$case];
		}
	}

	/** @dataProvider behaviorCases */
	#[DataProvider('behaviorCases')]
	public function testAuthenticationBehavior(callable $case): void
	{
		$case();
		$this->addToAssertionCount(1);
	}
}
