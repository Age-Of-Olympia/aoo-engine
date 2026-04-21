<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: admin/tutorial-catalog.php must enforce CSRF on
 * every state-changing POST (create / update / delete tutorial) and
 * render a CSRF token field in every POST form.
 *
 * Before this guard, the endpoint was admin-gated (via layout.php's
 * DoAdminCheck) but accepted ANY POST body without token validation —
 * while sibling admin pages (tutorial.php, tutorial-settings.php,
 * tutorial-step-save.php, tutorial-sessions-api.php, local_maps.php,
 * screenshots.php, world_map.php) all enforce CsrfProtectionService.
 *
 * An attacker's page with
 *   <form action="/admin/tutorial-catalog.php" method="POST"
 *         id="f"><input name="action" value="delete"><input name="id"
 *         value="2"></form>
 *   <script>document.getElementById('f').submit()</script>
 * auto-submits to the admin's session and deletes a catalog entry,
 * or edits an existing tutorial via action=update.
 *
 * This test pins the fix shape rather than executing an HTTP
 * request: the file must import CsrfProtectionService, call
 * validateTokenOrFail (or equivalent), and render the token in both
 * the inline delete form and the main create/update form. The CSRF
 * service itself is covered by CsrfProtectionService unit tests.
 */
class TutorialCatalogAdminCsrfTest extends TestCase
{
    private const CATALOG_PATH = __DIR__ . '/../../admin/tutorial-catalog.php';

    #[Group('tutorial-catalog-csrf')]
    public function testCatalogImportsCsrfProtectionService(): void
    {
        $source = (string) file_get_contents(self::CATALOG_PATH);

        $this->assertStringContainsString(
            'CsrfProtectionService',
            $source,
            'admin/tutorial-catalog.php must import/use CsrfProtectionService '
            . '(sibling admin pages already do — see tutorial.php, '
            . 'tutorial-settings.php, local_maps.php).'
        );
    }

    #[Group('tutorial-catalog-csrf')]
    public function testCatalogValidatesTokenOnPost(): void
    {
        $source = (string) file_get_contents(self::CATALOG_PATH);

        $this->assertStringContainsString(
            'validateTokenOrFail',
            $source,
            'admin/tutorial-catalog.php POST branch must call '
            . '$csrf->validateTokenOrFail($_POST[\'csrf_token\'] ?? null) '
            . 'before dispatching to create/update/delete actions.'
        );
    }

    #[Group('tutorial-catalog-csrf')]
    public function testCatalogValidatesTokenBeforeActionDispatch(): void
    {
        $source = (string) file_get_contents(self::CATALOG_PATH);

        // The POST branch opens with `REQUEST_METHOD === 'POST'`; the
        // first action dispatch is `$action === 'create' || $action
        // === 'update'`. CSRF validation MUST sit between the two.
        $postStart = strpos($source, "REQUEST_METHOD'] === 'POST'");
        $validatePos = strpos($source, 'validateTokenOrFail');
        $actionDispatchPos = strpos($source, "=== 'create'");

        $this->assertNotFalse($postStart,           'POST branch marker missing');
        $this->assertNotFalse($validatePos,         'validateTokenOrFail call missing');
        $this->assertNotFalse($actionDispatchPos,   'action dispatch marker missing');

        $this->assertLessThan(
            $validatePos,
            $postStart,
            'CSRF validation must happen inside the POST branch (after its opening)'
        );
        $this->assertLessThan(
            $actionDispatchPos,
            $validatePos,
            'CSRF validation must run BEFORE the action dispatch — otherwise '
            . 'a forged POST hits the create/update/delete path regardless.'
        );
    }

    #[Group('tutorial-catalog-csrf')]
    public function testCatalogRendersCsrfTokenFieldInForms(): void
    {
        $source = (string) file_get_contents(self::CATALOG_PATH);

        // Two POST forms live in this file: the inline per-row delete
        // form, and the main create/update form. Both need the token.
        // Count the occurrences of either `renderTokenField()` or the
        // raw hidden input — at least two hits means both forms are
        // covered.
        $renderHits = substr_count($source, 'renderTokenField()');
        $rawHits    = substr_count($source, 'name="csrf_token"');

        $this->assertGreaterThanOrEqual(
            2,
            $renderHits + $rawHits,
            'Both POST forms (delete + create/update) must embed the CSRF '
            . 'token. Found ' . ($renderHits + $rawHits) . ' hidden-input '
            . 'occurrence(s) — expected at least 2.'
        );
    }
}
