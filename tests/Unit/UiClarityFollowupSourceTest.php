<?php

/**
 * @file UiClarityFollowupSourceTest.php
 * @brief Source regressions for the Pulse 1.3.0 interface follow-up.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UiClarityFollowupSourceTest extends TestCase
{
	/** @brief Keeps the silent-addition note with the recipient action it explains. */
	public function testRecipientSilenceNoteLivesInsideAddRecipientBlock(): void
	{
		$view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/monitors/edit.php');
		$blockPosition = strpos($view, 'configuration-block recipient-add-block');
		$notePosition = strpos($view, 'privacy-note recipient-add-note');

		self::assertNotFalse($blockPosition);
		self::assertNotFalse($notePosition);
		self::assertGreaterThan($blockPosition, $notePosition);
	}

	/** @brief Keeps all safety timing help visible and treats an incomplete quorum as advisory. */
	public function testSafetyTimingUsesHelpAndAdvisoryQuorumWarning(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/MonitorController.php');
		$script = (string)file_get_contents($root . '/public/assets/app.js');

		foreach ([
			'response_window_hint',
			'reminder_interval_hint',
			'max_reminders_hint',
			'required_confirmations_hint',
			'confirmation_days_hint',
		] as $key)
		{
			self::assertStringContainsString('monitors.escalation.' . $key, $view);
		}

		self::assertStringContainsString('data-safety-contact-eligible', $view);
		self::assertStringContainsString('data-safety-confirmation-warning', $view);
		self::assertStringContainsString('updateSafetyConfirmationWarning', $script);
		self::assertStringNotContainsString('ValidateSafetyConfiguration', $controller);
		self::assertStringNotContainsString('monitors.escalation.flash.invalid_contacts', $controller);
	}

	/** @brief Keeps the first owner address mandatory without removing the other login aliases. */
	public function testProfileRequiresAVisibleMainEmail(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/profile/index.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/ProfileController.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/UserRepository.php');
		$script = (string)file_get_contents($root . '/public/assets/app.js');

		self::assertStringContainsString("\$slot === 1 ? 'profile.data.main_email' : 'profile.data.email'", $view);
		self::assertStringContainsString('data-main-email aria-describedby="profile-main-email-error" required', $view);
		self::assertStringContainsString("\$mainEmail = \$this->_request->PostString('email', 255);", $controller);
		self::assertStringContainsString("if (\$mainEmail === '' || \$addresses === [])", $controller);
		self::assertStringContainsString('data-main-email-error', $script);
		self::assertStringContainsString('email_4 = :email_4', $repository);
	}

	/** @brief Keeps the browser-generated cron-token guidance on its own line. */
	public function testCronTokenGeneratorHelpIsBlockLevel(): void
	{
		$root = dirname(__DIR__, 2);
		$view = (string)file_get_contents($root . '/app/Views/administration/index.php');
		$styles = (string)file_get_contents($root . '/public/assets/style.css');

		self::assertStringContainsString('class="cron-token-generator"', $view);
		self::assertStringContainsString('.cron-token-generator .form-hint', $styles);
		self::assertStringContainsString('display: block;', $styles);
	}
}
