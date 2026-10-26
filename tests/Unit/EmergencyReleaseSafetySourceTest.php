<?php

/**
 * @file EmergencyReleaseSafetySourceTest.php
 * @brief Release barrier and emergency-link source safety regression assertions.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @brief Checks that the critical safety wiring is preserved between releases. */
final class EmergencyReleaseSafetySourceTest extends TestCase
{
	/** @brief Ensures email GET cannot cause a stop and the owner warning gates release. */
	public function testWarningAndStopProtection(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/EmergencyStopController.php');
		$release = (string)file_get_contents($root . '/app/Services/EscalationService.php');
		$queue = (string)file_get_contents($root . '/app/Repositories/MailQueueRepository.php');
		$stop = (string)file_get_contents($root . '/app/Services/EmergencyStopService.php');
		$schema = (string)file_get_contents($root . '/database/migrations/010_release_emergency_safeguards.sql');
		$css = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString("function Show(): string", $controller);
		self::assertStringContainsString("function Confirm(): string", $controller);
		self::assertStringNotContainsString('StopByToken($token);' . "\n" . '\t\treturn', $controller);
		self::assertStringContainsString('EnsureReleaseWarning($connection, $cycle)', $release);
		self::assertStringContainsString("'owner_warning_blocked'", $release);
		self::assertStringContainsString("mail_type = \\'owner_release_warning\\'", $queue);
		self::assertStringContainsString('warning_sent_at IS NULL', $queue);
		self::assertStringContainsString('StopByToken(string $token)', $stop);
		self::assertStringContainsString('StopLocked($connection, $userId, \'email_link\', $monitorId)', $stop);
		self::assertStringContainsString('StopLocked($connection, $userId, \'dashboard\')', $stop);
		self::assertStringContainsString("AND monitor_id = :monitor_id", $stop);
		self::assertStringContainsString("CASE WHEN mail_type = \'owner_release_warning\' THEN 0 ELSE 1 END", $queue);
		self::assertStringContainsString("status = \\'cancelled\\'", $stop);
		self::assertStringContainsString('release_grace_hours', $schema);
		self::assertStringContainsString('@container (max-width: 40rem)', $css);
		self::assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $css);
	}
}
