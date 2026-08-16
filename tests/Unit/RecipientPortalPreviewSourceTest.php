<?php

/**
 * @file RecipientPortalPreviewSourceTest.php
 * @brief Source-level owner-authorization and side-effect guards for recipient portal previews.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RecipientPortalPreviewSourceTest extends TestCase
{
	/** @brief Ensures both preview endpoints require the owner session and scoped repository lookups. */
	public function testPreviewRoutesRemainOwnerAuthenticatedAndScoped(): void
	{
		$root = dirname(__DIR__, 2);
		$routes = (string)file_get_contents($root . '/public/index.php');
		$controller = (string)file_get_contents($root . '/app/Controllers/RecipientController.php');
		$repository = (string)file_get_contents($root . '/app/Repositories/RecipientRepository.php');

		self::assertStringContainsString("\$router->Get('/monitors/recipients/portal-preview', [\$recipientController, 'PortalPreview']);", $routes);
		self::assertStringNotContainsString('/monitors/recipients/portal-preview/map', $routes);
		self::assertStringContainsString("\$router->Get('/monitors/recipients/portal-preview/asset', [\$recipientController, 'PortalPreviewAsset']);", $routes);
		self::assertGreaterThanOrEqual(2, substr_count($controller, '$this->RequireUser();'));
		self::assertStringContainsString('FindByIdForUser($monitorContactId, $userId)', $controller);
		self::assertStringContainsString('FindAssignedDocumentForUser($monitorContactId, $documentId, $userId)', $controller);
		self::assertStringContainsString('AND m.user_id = :user_id', $repository);
	}

	/** @brief Ensures preview rendering cannot create or operate a real recipient delivery. */
	public function testPreviewIsReadOnlyAndClearlyMarked(): void
	{
		$root = dirname(__DIR__, 2);
		$controller = (string)file_get_contents($root . '/app/Controllers/RecipientController.php');
		$recipientView = (string)file_get_contents($root . '/app/Views/recipients/edit.php');
		$portalView = (string)file_get_contents($root . '/app/Views/portal/access.php');
		$javascript = (string)file_get_contents($root . '/public/assets/app.js');

		$previewStart = strpos($controller, 'public function PortalPreview(): string');
		$assetStart = strpos($controller, 'public function PortalPreviewAsset(): void');
		self::assertIsInt($previewStart);
		self::assertIsInt($assetStart);
		$previewMethod = substr($controller, $previewStart, $assetStart - $previewStart);

		self::assertStringNotContainsString('RequestAccessCode', $previewMethod);
		self::assertStringNotContainsString('GrantSession', $previewMethod);
		self::assertStringNotContainsString('Enqueue', $previewMethod);
		self::assertStringContainsString("'previewMode' => true", $previewMethod);
		self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $recipientView);
		self::assertStringContainsString('if (!$previewMode && empty($delivery[\'portal_expires_at\']))', $portalView);
		self::assertStringContainsString("e__(\$previewMode ? 'recipients.portal_preview.actions_disabled'", $portalView);
		self::assertStringContainsString('data-map-toggle', $portalView);
		self::assertStringContainsString('data-window-close', $portalView);
		self::assertStringContainsString('window.close();', $javascript);
		self::assertStringNotContainsString('previewReturnUrl', $controller . $portalView);
	}

	/** @brief Ensures location preview follows the same explicit monitor sharing opt-in as a real release. */
	public function testPreviewLocationsRespectPortalSharingSetting(): void
	{
		$root = dirname(__DIR__, 2);
		$repository = (string)file_get_contents($root . '/app/Repositories/RecipientRepository.php');

		self::assertStringContainsString('FindPortalPreviewLocationsForUser', $repository);
		self::assertStringContainsString("empty(\$recipient['portal_location_sharing_enabled'])", $repository);
		self::assertStringContainsString("min(20, (int)\$recipient['portal_location_history_limit'])", $repository);
		self::assertStringContainsString('array_values(array_reverse($rows))', $repository);
	}
}
