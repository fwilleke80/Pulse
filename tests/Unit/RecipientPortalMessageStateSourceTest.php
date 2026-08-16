<?php

/**
 * @file RecipientPortalMessageStateSourceTest.php
 * @brief Guards reusable disabled recipient portal-message drafts.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RecipientPortalMessageStateSourceTest extends TestCase
{
	/** @brief Disabled portal messages remain stored while releases obey the enabled state. */
	public function testDisabledDraftIsPreservedSeparatelyFromReleaseState(): void
	{
		$root = dirname(__DIR__, 2);
		$repository = (string)file_get_contents($root . '/app/Repositories/RecipientRepository.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/RecipientController.php');
		$escalation = (string)file_get_contents($root . '/app/Services/EscalationService.php');
		$view = (string)file_get_contents($root . '/app/Views/recipients/edit.php');
		$javascript = (string)file_get_contents($root . '/public/assets/app.js');

		self::assertStringContainsString('is_enabled = VALUES(is_enabled)', $repository);
		self::assertStringContainsString("'is_enabled' => \$usePortalOverride ? 1 : 0", $repository);
		self::assertStringContainsString("\$recipient['portal_override_enabled']", $controller);
		self::assertStringContainsString('portal_message_override_enabled', $escalation);
		self::assertStringContainsString('data-preserve-disabled-fields', $view);
		self::assertStringContainsString('preserveDisabledFields', $javascript);
	}

	/** @brief The application-name placeholder is absent from all visible message editors. */
	public function testEditorsDoNotAdvertiseAppPlaceholder(): void
	{
		$root = dirname(__DIR__, 2);
		$monitorView = (string)file_get_contents($root . '/app/Views/monitors/edit.php');
		$recipientView = (string)file_get_contents($root . '/app/Views/recipients/edit.php');

		self::assertStringNotContainsString('{app}', $monitorView);
		self::assertStringNotContainsString('{app}', $recipientView);
		self::assertStringNotContainsString('mail.placeholders.app', $monitorView . $recipientView);
	}

	/** @brief Built-in localized templates use the fixed Pulse name. */
	public function testLocalizedDefaultsUseLiteralPulseName(): void
	{
		$root = dirname(__DIR__, 2);
		foreach (['en', 'de', 'fr', 'it'] as $locale)
		{
			$languageSource = (string)file_get_contents($root . '/app/Lang/' . $locale . '.php');
			self::assertStringNotContainsString("'mail.placeholders.app'", $languageSource);
			self::assertStringNotContainsString('[{app}]', $languageSource);
			self::assertStringContainsString('[Pulse]', $languageSource);
		}
	}
}
