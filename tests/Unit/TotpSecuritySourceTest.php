<?php

/**
 * @file TotpSecuritySourceTest.php
 * @brief Static regression checks for optional TOTP authentication and recovery behavior.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TotpSecuritySourceTest extends TestCase
{
	/** @brief Ensures password sign-in is challenged only when the account has enabled TOTP. */
	public function testPasswordLoginUsesOptionalSecondFactor(): void
	{
		$root = dirname(__DIR__, 2);
		$auth = (string)file_get_contents($root . '/app/Controllers/AuthController.php');
		$routes = (string)file_get_contents($root . '/public/index.php');

		self::assertStringContainsString('$this->_totp->IsEnabled($userId)', $auth);
		self::assertStringContainsString('$this->_auth->VerifyPassword($email, $password)', $auth);
		self::assertStringContainsString("'/login/totp'", $routes);
		self::assertStringContainsString('VerifyAuthentication($userId, $code)', $auth);
	}

	/** @brief Ensures passkeys remain a complete authentication and cannot switch away from a pending password account. */
	public function testPasskeyCompletesAuthenticationWithoutTotp(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/SecurityController.php');
		$view = (string)file_get_contents($root . '/app/Views/auth/totp.php');

		self::assertStringContainsString('$pendingTotp = $this->_totp->PendingLogin()', $controller);
		self::assertStringContainsString('$this->_totp->CancelPendingLogin()', $controller);
		self::assertStringContainsString('data-passkey-login-form', $view);
		self::assertStringContainsString('security.totp.passkey_complete_hint', $view);
	}

	/** @brief Ensures secrets are encrypted, recovery codes are hashed, and TOTP counters cannot be replayed. */
	public function testStoredTotpMaterialIsProtectedAndConsumedAtomically(): void
	{
		$root = dirname(__DIR__, 2);
		$protector = (string)file_get_contents($root . '/app/Services/TotpSecretProtector.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/TotpCredentialRepository.php');
		$consumeStart = strpos($repository, 'public function ConsumeCounter(');
		$consumeEnd = strpos($repository, 'public function ConsumeRecoveryCode(');

		self::assertIsInt($consumeStart);
		self::assertIsInt($consumeEnd);
		$consume = substr($repository, $consumeStart, $consumeEnd - $consumeStart);
		self::assertStringContainsString("CIPHER = 'aes-256-gcm'", $protector);
		self::assertStringContainsString("hash_hmac('sha256'", $protector);
		self::assertStringContainsString('$connection->beginTransaction()', $consume);
		self::assertStringContainsString('SET utc.last_used_counter = :counter', $consume);
		self::assertStringContainsString('utc.last_used_counter < :counter_guard', $consume);
		self::assertStringContainsString('UPDATE user_security_methods', $consume);
		self::assertStringNotContainsString('SET utc.last_used_counter = :counter, usm.last_used_at', $consume);
		self::assertStringContainsString('$connection->commit()', $consume);
		self::assertStringContainsString('utrc.used_at IS NULL', $repository);
		self::assertStringContainsString('FOR UPDATE', $repository);
	}

	/** @brief Ensures enrollment proves the shared secret without consuming the first real login time step. */
	public function testEnrollmentCodeIsNotStoredAsAnAuthenticatedReplayCounter(): void
	{
		$root = dirname(__DIR__, 2);
		$service = (string)file_get_contents($root . '/app/Services/TotpService.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/TotpCredentialRepository.php');
		$enableStart = strpos($repository, 'public function Enable(');
		$enableEnd = strpos($repository, 'public function ConsumeCounter(');

		self::assertIsInt($enableStart);
		self::assertIsInt($enableEnd);
		self::assertStringContainsString('$counter = $this->_algorithm->Verify($secret, $code);', $service);
		self::assertStringNotContainsString('$confirmedCounter', substr($repository, $enableStart, $enableEnd - $enableStart));
		self::assertStringContainsString('(security_method_id, secret_ciphertext, enabled_at)', $repository);
	}

	/** @brief Ensures enrollment QR generation remains local and never loads a remote QR service. */
	public function testEnrollmentQrCodeIsRenderedLocally(): void
	{
		$root = dirname(__DIR__, 2);
		$layout = (string)file_get_contents($root . '/app/Views/layouts/main.php');
		$script = (string)file_get_contents($root . '/public/assets/app.js');
		$vendor = (string)file_get_contents($root . '/public/assets/vendor/qrcodegen-v1.8.0.js');

		self::assertStringContainsString('/assets/vendor/qrcodegen-v1.8.0.js', $layout);
		self::assertStringContainsString('qrcodegen.QrCode.encodeText', $script);
		self::assertStringContainsString('Project Nayuki. (MIT License)', $vendor);
		self::assertStringNotContainsString('api.qrserver.com', $layout . $script);
		self::assertStringNotContainsString('chart.googleapis.com', $layout . $script);
	}
}
