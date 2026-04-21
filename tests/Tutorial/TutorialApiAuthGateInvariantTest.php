<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Invariant: every `api/tutorial/*.php` endpoint that declares
 * NO_LOGIN MUST gate on $_SESSION['playerId'] itself (or delegate to
 * an admin-only check).
 *
 * Context. Endpoints that define `define('NO_LOGIN', true)` before
 * including config.php opt OUT of the default "redirect unauthenticated
 * users to login" behaviour in config.php. The opt-out exists for
 * JSON API endpoints that want to respond with 401 rather than a
 * login-page redirect. The endpoint MUST then check auth itself.
 *
 * If a file declares NO_LOGIN and forgets the check, every detail it
 * echoes (session ids, tutorial state, player ids) is reachable by
 * unauthenticated callers. `api/tutorial/test.php` and
 * `api/tutorial/check_session.php` were two such files — debug stubs
 * that leaked `session_id()` and `$_SESSION['playerId']` to anyone
 * who could guess their URL.
 *
 * The fix is simple for those two (delete the files — no caller uses
 * them) but the pattern must not resurface when a future dev copies
 * the NO_LOGIN skeleton to start a new API endpoint.
 */
class TutorialApiAuthGateInvariantTest extends TestCase
{
    private const API_DIR = __DIR__ . '/../../api/tutorial';

    #[Group('tutorial-api-auth-gate')]
    public function testEveryNoLoginFileGatesOnSessionPlayerId(): void
    {
        $offenders = [];

        foreach ($this->noLoginEndpoints() as $path) {
            $source = (string) file_get_contents($path);

            // Accepted gate shapes:
            //   if (!isset($_SESSION['playerId']))  → 401/exit
            //   AdminAuthorizationService::DoAdminCheck()
            //     (exit()s on missing session + wrong role)
            //   AdminAuthorizationService::DoSuperAdminCheck()
            $hasGate =
                   (bool) preg_match('/!\s*isset\s*\(\s*\$_SESSION\s*\[\s*[\'"]playerId[\'"]\s*\]\s*\)/', $source)
                || str_contains($source, 'AdminAuthorizationService::DoAdminCheck')
                || str_contains($source, 'AdminAuthorizationService::DoSuperAdminCheck');

            if (!$hasGate) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'api/tutorial/ files declaring NO_LOGIN must gate on $_SESSION[\'playerId\'] '
            . 'or delegate to AdminAuthorizationService. Offenders leak session state '
            . 'to unauthenticated callers: ' . implode(', ', $offenders)
        );
    }

    #[Group('tutorial-api-auth-gate')]
    public function testDebugStubsAreRemoved(): void
    {
        // Anchor the specific files the audit called out. Keeps
        // them from being reintroduced via copy-paste of older
        // branches.
        $this->assertFileDoesNotExist(
            self::API_DIR . '/test.php',
            'api/tutorial/test.php was an unauthenticated debug endpoint — '
            . 'it must not reappear. Use scripts/ for debug utilities.'
        );
        $this->assertFileDoesNotExist(
            self::API_DIR . '/check_session.php',
            'api/tutorial/check_session.php was an unauthenticated debug endpoint — '
            . 'it must not reappear. Use scripts/ for debug utilities.'
        );
        $this->assertFileDoesNotExist(
            self::API_DIR . '/exit_tutorial_mode.php',
            'api/tutorial/exit_tutorial_mode.php was an orphan, bootstrap-'
            . 'bypassing session-rewrite endpoint. It must not reappear — '
            . 'use TutorialHelper::exitTutorialMode() from an authenticated '
            . 'endpoint instead.'
        );
        $this->assertFileDoesNotExist(
            self::API_DIR . '/check_tutorial_character.php',
            'api/tutorial/check_tutorial_character.php was an orphan '
            . 'bootstrap-bypassing endpoint. It must not reappear — if a '
            . 'future UI needs this check, expose it from an authenticated '
            . 'endpoint that loads config.php.'
        );
    }

    #[Group('tutorial-api-auth-gate')]
    public function testEveryApiFileRequiresSharedBootstrap(): void
    {
        // Defence in depth: the NO_LOGIN gate above only covers
        // endpoints that declare NO_LOGIN. A file that skips
        // config.php entirely (hand-rolling its own session_start()
        // + bootstrap) dodges the whole config stack — auth,
        // constants, error handlers, CSRF session keys, etc. Require
        // every api/tutorial/*.php to include config.php so the
        // invariant cannot be sidestepped that way.
        $offenders = [];
        foreach (glob(self::API_DIR . '/*.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            if (!str_contains($source, '/../../config.php')) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'api/tutorial/ files must require_once the shared config.php '
            . '(optionally with NO_LOGIN beforehand) instead of hand-rolling '
            . 'their own session_start + bootstrap. Offenders: '
            . implode(', ', $offenders)
        );
    }

    /**
     * @return iterable<string>
     */
    private function noLoginEndpoints(): iterable
    {
        foreach (glob(self::API_DIR . '/*.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            if (str_contains($source, "define('NO_LOGIN'") || str_contains($source, 'define("NO_LOGIN"')) {
                yield $path;
            }
        }
    }
}
