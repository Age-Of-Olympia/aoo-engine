<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: admin/tutorial-launcher.php must reject
 * state-changing GET requests and enforce CSRF on POST.
 *
 * The launcher (a) clears the admin's active tutorial session
 * (`UPDATE tutorial_progress SET completed = 1 …`) and (b) starts a
 * new tutorial run with a caller-supplied version. Both are
 * state-changing. The original shape accepted `$_GET['version']`
 * with no CSRF token — classic CSRF-via-image-tag:
 *
 *   <img src="/admin/tutorial-launcher.php?version=crafting">
 *
 * embedded in any page the admin might load. The admin's cookie
 * rides along, their current tutorial state is wiped, and a new
 * tutorial run is started without their consent.
 *
 * Fix shape (pinned by this test):
 *
 *   1. The endpoint rejects non-POST requests (405). No more
 *      $_GET['version'] fallback.
 *   2. The endpoint imports CsrfProtectionService and calls
 *      validateTokenOrFail() BEFORE any UPDATE / tutorial start.
 *   3. The catalog page's JS fetch uses method='POST' and posts
 *      csrf_token in the body (so the existing launch-button UX
 *      keeps working).
 */
class TutorialLauncherAdminCsrfTest extends TestCase
{
    private const LAUNCHER_PATH = __DIR__ . '/../../admin/tutorial-launcher.php';
    private const CATALOG_PATH  = __DIR__ . '/../../admin/tutorial-catalog.php';

    #[Group('tutorial-launcher-csrf')]
    public function testLauncherRejectsNonPostRequests(): void
    {
        $source = (string) file_get_contents(self::LAUNCHER_PATH);

        $this->assertMatchesRegularExpression(
            '/REQUEST_METHOD[\'"\]]+\s*!==?\s*[\'"]POST[\'"]/',
            $source,
            'tutorial-launcher.php must reject non-POST requests before '
            . 'doing any work — state-changing GET is the CSRF vector.'
        );
    }

    #[Group('tutorial-launcher-csrf')]
    public function testLauncherDoesNotTakeVersionFromGet(): void
    {
        $source = (string) file_get_contents(self::LAUNCHER_PATH);

        $this->assertStringNotContainsString(
            "\$_GET['version']",
            $source,
            'tutorial-launcher.php must not accept the version parameter via '
            . '$_GET — keep the endpoint POST-only to close the CSRF vector.'
        );
    }

    #[Group('tutorial-launcher-csrf')]
    public function testLauncherValidatesCsrfBeforeStateChange(): void
    {
        $source = (string) file_get_contents(self::LAUNCHER_PATH);

        $this->assertStringContainsString(
            'CsrfProtectionService',
            $source,
            'tutorial-launcher.php must import CsrfProtectionService.'
        );
        $this->assertStringContainsString(
            'validateTokenOrFail',
            $source,
            'tutorial-launcher.php must call validateTokenOrFail on POST.'
        );

        // Ordering pin: CSRF check must precede the UPDATE
        // tutorial_progress SET completed=1 that resets prior session
        // state. Any forged POST that bypasses the check loses the
        // admin's active tutorial.
        $validatePos = strpos($source, 'validateTokenOrFail');
        $updatePos   = strpos($source, 'tutorial_progress SET completed');

        $this->assertNotFalse($validatePos, 'validateTokenOrFail call missing');
        $this->assertNotFalse($updatePos,   'tutorial_progress UPDATE marker missing');
        $this->assertLessThan(
            $updatePos,
            $validatePos,
            'CSRF validation must run BEFORE the UPDATE tutorial_progress — '
            . 'otherwise a forged POST wipes the admin\'s active tutorial '
            . 'regardless of the token.'
        );
    }

    #[Group('tutorial-launcher-csrf')]
    public function testCatalogLaunchButtonUsesPostWithCsrfToken(): void
    {
        $source = (string) file_get_contents(self::CATALOG_PATH);

        // Count the full fetch shape — method must be POST and the
        // body must carry csrf_token. A GET fetch has neither.
        $this->assertMatchesRegularExpression(
            "/fetch\\s*\\(\\s*['\"]\\/admin\\/tutorial-launcher\\.php['\"]/",
            $source,
            'The catalog page must fetch tutorial-launcher.php with an explicit '
            . 'URL and options object (no query-string version).'
        );

        $this->assertMatchesRegularExpression(
            "/method\\s*:\\s*['\"]POST['\"]/i",
            $source,
            'The fetch to tutorial-launcher.php must specify method: "POST".'
        );

        $this->assertStringContainsString(
            'csrf_token',
            $source,
            'The fetch to tutorial-launcher.php must send the csrf_token in the body.'
        );
    }
}
