<?php

/**
 * @file PasskeyQuickCheckInSourceTest.php
 * @brief Static regression checks for passkeys, quick check-in, and owner reminder templates.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PasskeyQuickCheckInSourceTest extends TestCase
{
	/** @brief Ensures passkeys are implemented on the generic account-security storage layer. */
	public function testPasskeysUseExtensibleSecurityMethodStorage(): void
	{
		$root = dirname(__DIR__, 2);
		$migration = (string)file_get_contents($root . '/database/migrations/002_security_methods_and_owner_mail.sql');
		$repository = (string)file_get_contents($root . '/app/Repositories/SecurityCredentialRepository.php');
		$challenge = (string)file_get_contents($root . '/app/Services/SecurityChallengeService.php');

		self::assertStringContainsString('CREATE TABLE user_security_methods', $migration);
		self::assertStringContainsString('method VARCHAR(32) NOT NULL', $migration);
		self::assertStringContainsString('CREATE TABLE user_passkey_credentials', $migration);
		self::assertStringContainsString("METHOD_PASSKEY = 'passkey'", $repository);
		self::assertStringContainsString('Provides a reusable challenge mechanism', $challenge);
	}

	/** @brief Ensures quick check-in is one global Administration option rather than a monitor setting. */
	public function testQuickCheckInIsConfiguredGlobally(): void
	{
		$root = dirname(__DIR__, 2);
		$administration = (string)file_get_contents($root . '/app/Views/administration/index.php');
		$monitor = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$installer = (string)file_get_contents($root . '/app/Installation/InstallationService.php');
		$example = (string)file_get_contents($root . '/.env.example');

		self::assertStringContainsString('name="PULSE_PASSKEY_QUICK_CHECKIN_ENABLED"', $administration);
		self::assertStringNotContainsString('name="PULSE_PASSKEY_QUICK_CHECKIN_ENABLED"', $monitor);
		self::assertStringContainsString("'PULSE_PASSKEY_QUICK_CHECKIN_ENABLED' => 'false'", $installer);
		self::assertStringContainsString('PULSE_PASSKEY_QUICK_CHECKIN_ENABLED=false', $example);
	}

	/** @brief Ensures email quick links never perform the check-in without authentication. */
	public function testQuickEmailLinkRequiresAuthenticationAndChecksInAllActiveMonitors(): void
	{
		$root = dirname(__DIR__, 2);
		$quickController = (string)file_get_contents($root . '/app/Controllers/QuickCheckInController.php');
		$authController = (string)file_get_contents($root . '/app/Controllers/AuthController.php');
		$service = (string)file_get_contents($root . '/app/Services/QuickCheckInService.php');

		self::assertStringContainsString('ResolveRawToken($rawToken)', $quickController);
		$openStart = strpos($quickController, 'public function Open(): string');
		$openEnd = strpos($quickController, 'public function PasskeyOptions(): string');
		self::assertIsInt($openStart);
		self::assertIsInt($openEnd);
		self::assertStringNotContainsString('CheckInAllActiveForUser', substr($quickController, $openStart, $openEnd - $openStart));
		self::assertStringContainsString("CheckInAllActiveForUser(\$userId, 'quick_passkey')", $quickController);
		self::assertStringContainsString("CheckInAllActiveForUser(\$userId, 'quick_password')", $authController);
		self::assertStringContainsString('token_hash', $service);
		self::assertStringContainsString('used_at IS NULL', $service);
		self::assertStringContainsString('expires_at > UTC_TIMESTAMP()', $service);
	}

	/** @brief Ensures normal login and reminder quick check-in both expose passkey authentication with password fallback. */
	public function testPasskeysAreAvailableForLoginAndQuickCheckIn(): void
	{
		$root = dirname(__DIR__, 2);
		$routes = (string)file_get_contents($root . '/public/index.php');
		$login = (string)file_get_contents($root . '/app/Views/auth/login.php');
		$quick = (string)file_get_contents($root . '/app/Views/security/quick-checkin.php');

		self::assertStringContainsString('/login/passkey/options', $routes);
		self::assertStringContainsString('/login/passkey/verify', $routes);
		self::assertStringContainsString('data-passkey-login', $login);
		self::assertGreaterThan(strpos($login, 'id="password"'), strpos($login, '<button type="button" class="btn-primary" data-passkey-login>'));
		self::assertStringContainsString('data-quick-passkey', $quick);
		self::assertStringContainsString('/login?quick=1', $quick);
		$securityController = (string)file_get_contents($root . '/app/Controllers/SecurityController.php');
		self::assertStringContainsString("BeginAuthentication('login', \$targetUserId)", $securityController);
	}

	/** @brief Ensures discoverable login verifies the account handle and WebAuthn user-verification state. */
	public function testPasskeyVerificationIncludesCoreWebAuthnBindings(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/PasskeyService.php');

		self::assertStringContainsString('Discoverable passkey authentication did not identify a user handle.', $service);
		self::assertStringContainsString('hash_equals($this->_origin', $service);
		self::assertStringContainsString("hash('sha256', \$this->_rpId, true)", $service);
		self::assertStringContainsString('FLAG_USER_VERIFIED', $service);
		self::assertStringContainsString('openssl_verify(', $service);
		self::assertStringContainsString('FLAG_BACKUP_STATE', $service);
		self::assertStringContainsString('FLAG_BACKUP_ELIGIBLE', $service);
	}

	/** @brief Ensures owner reminder overrides are single templates with localized collapsible defaults. */
	public function testOwnerReminderTemplatesAreSingleOverridesWithLocalizedDefaults(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/MessageRepository.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$ownerStart = strpos($view, 'data-subtab-panel="owner"');
		$recipientStart = strpos($view, 'data-subtab-panel="recipient"');
		self::assertIsInt($ownerStart);
		self::assertIsInt($recipientStart);
		$ownerSection = substr($view, $ownerStart, $recipientStart - $ownerStart);
		self::assertStringContainsString("'owner' => \$template", $repository);
		self::assertStringContainsString('BuiltInTemplate(', $controller);
		self::assertStringContainsString('$ownerNotificationLocale', $controller);
		self::assertStringContainsString('name="owner_due_notice_subject"', $ownerSection);
		self::assertStringContainsString('name="owner_reminder_subject"', $ownerSection);
		self::assertStringContainsString('<code>{url}</code>', $ownerSection);
		self::assertStringContainsString('<code>{quickcheckin}</code>', $ownerSection);
		self::assertStringContainsString('<code>{quickurl}</code>', $ownerSection);
		self::assertGreaterThanOrEqual(2, substr_count($ownerSection, 'mail-default-disclosure'));
		self::assertStringNotContainsString('data-language-tabs', $ownerSection);
	}
	/** @brief Ensures a newly registered passkey is visible immediately after the ceremony completes. */
	public function testPasskeyRegistrationRefreshesTheProfileList(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/SecurityController.php');
		$script = (string)file_get_contents($root . '/public/assets/app.js');

		self::assertStringContainsString("'reload' => true", $controller);
		self::assertStringContainsString('window.location.reload()', $script);
	}

	/** @brief Ensures quick-check-in documentation explains passkey availability and synchronization. */
	public function testQuickCheckInDocumentationCoversDevicePreparation(): void
	{
		$root = dirname(__DIR__, 2);
		$readme = (string)file_get_contents($root . '/README.md');
		$userGuide = (string)file_get_contents($root . '/docs/USER_GUIDE.md');
		$profile = (string)file_get_contents($root . '/app/Views/profile/index.php');

		self::assertStringContainsString('lowest-effort way to perform routine check-ins', $readme);
		self::assertStringContainsString('may be synchronized automatically', $userGuide);
		self::assertStringContainsString("security.passkeys.device_hint", $profile);
	}

	/** @brief Ensures duplicate passkey registration gets a friendly InvalidStateError message. */
	public function testDuplicatePasskeyRegistrationUsesFriendlyMessage(): void
	{
		$root = dirname(__DIR__, 2);
		$profile = (string)file_get_contents($root . '/app/Views/profile/index.php');
		$script = (string)file_get_contents($root . '/public/assets/app.js');

		self::assertStringContainsString('data-passkey-already-available', $profile);
		self::assertStringContainsString("error.name === 'InvalidStateError'", $script);
		self::assertStringContainsString('form.dataset.passkeyAlreadyAvailable', $script);
	}

}
