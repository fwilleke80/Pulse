<?php
/**
 * @file AdministrationSectionRoutingSourceTest.php
 * @brief Regression checks for task-oriented Administration routing and page boundaries.
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** @brief Prevent configuration actions leaking onto Maintenance and Information pages. */
final class AdministrationSectionRoutingSourceTest extends TestCase
{
    /** @brief Each major section has an independently routed administrator-only view. */
    public function testIndependentPagesAndAdminAuthorization(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string)file_get_contents($root . '/public/index.php');
        $controller = (string)file_get_contents($root . '/app/Controllers/AdministrationController.php');
        foreach (['configuration', 'maintenance/backups', 'maintenance/updates', 'system'] as $suffix)
        {
            self::assertStringContainsString("/administration/" . $suffix, $routes);
        }
        self::assertStringContainsString('private function RenderSection(string $section)', $controller);
        self::assertStringContainsString('$user = $this->RequireAdministrator()', $controller);
        self::assertStringNotContainsString('/administration?tab=', $controller);
    }

    /** @brief Configuration saves only from the configuration branch. */
    public function testSaveFormIsConfinedToConfiguration(): void
    {
        $view = (string)file_get_contents(dirname(__DIR__, 2) . '/app/Views/administration/index.php');
        $configuration = explode("<?php elseif (\$section === 'backups' || \$section === 'updates'): ?>", $view, 2)[0];
        $maintenance = explode("<?php elseif (\$section === 'backups' || \$section === 'updates'): ?>", $view, 2)[1] ?? '';
        self::assertStringContainsString('administration-settings-form', $configuration);
        self::assertStringContainsString('data-settings-save-bar', $configuration);
        self::assertStringNotContainsString('administration-settings-form', $maintenance);
        self::assertStringNotContainsString('data-settings-save-bar', $maintenance);
        self::assertStringContainsString('data-tab-target', $configuration);
    }
}
