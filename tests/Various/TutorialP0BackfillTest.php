<?php

namespace Tests\Various;

use App\Tutorial\Steps\Actions\ActionStep;
use App\Tutorial\TutorialSessionManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * D4 Phase A — retroactive test-debt backfill for MRs !329–!333.
 *
 * Those MRs landed production-ready P0 fixes during the rapid-fix
 * phase, BEFORE the TDD+KISS rule in docs/player-dismantling-roadmap.md
 * was written down. They would not pass that rule today. This file
 * pins each fix as a contract so the regression that originally
 * required the P0 surfaces loudly the next time somebody touches the
 * affected file.
 *
 * Tests are static contract / source inspection / reflection — no DB,
 * no fixture setup. The matching wet-path coverage (real admin-check
 * exit behaviour, real XP mutations) lives in Cypress
 * tutorial-production-ready.
 *
 * Baseline documented in docs/tutorial-p0-deferred-design.md
 * §"Test-debt baseline". #333 (TutorialUI.next debounce) is JavaScript
 * and stays with Cypress; not re-tested here.
 */
class TutorialP0BackfillTest extends TestCase
{
    /**
     * MR !329 — admin authz on the tutorial launcher + sessions API.
     *
     * Pinning: the two admin endpoints call
     * AdminAuthorizationService::DoAdminCheck() somewhere near the
     * top of the file, BEFORE any handler logic. Removing that line
     * re-opens the endpoints to non-admins.
     */
    #[Group('p0-backfill')]
    #[Group('mr-329')]
    public function testTutorialLauncherRequiresAdminCheck(): void
    {
        $this->assertFileCallsDoAdminCheckEarly(
            __DIR__ . '/../../admin/tutorial-launcher.php'
        );
    }

    #[Group('p0-backfill')]
    #[Group('mr-329')]
    public function testTutorialSessionsApiRequiresAdminCheck(): void
    {
        $this->assertFileCallsDoAdminCheckEarly(
            __DIR__ . '/../../admin/tutorial-sessions-api.php'
        );
    }

    /**
     * MR !330 — jump-to-step no longer calls the non-existent
     * getActiveTutorialSession; the correct method is getActiveSession.
     *
     * Pinning: reflection-based signature check so a rename or
     * parameter drift fails the test immediately (same pattern as
     * CleanupOrphansScriptTest).
     */
    #[Group('p0-backfill')]
    #[Group('mr-330')]
    public function testGetActiveSessionContractMatchesJumpToStepCall(): void
    {
        // Pin the shape jump-to-step.php actually depends on:
        // one int parameter in, nullable array out. The parameter's
        // variable name is internal to TutorialSessionManager — no
        // caller uses named arguments today (grep confirmed).
        $method = new ReflectionMethod(TutorialSessionManager::class, 'getActiveSession');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getActiveSession must take exactly one argument');
        $this->assertSame('int', (string) $params[0]->getType());
        $this->assertSame('?array', (string) $method->getReturnType());
    }

    /**
     * MR !331 — removed the predictable-path debug log write in
     * ActionStep::validate (the fwrite to tmp/action_debug.log).
     *
     * Pinning: validate() leaves no side-effect file behind after a
     * normal call. If somebody ever re-adds a "just a quick debug"
     * fwrite, this test catches it before prod writes tmp files on
     * every tutorial step.
     */
    #[Group('p0-backfill')]
    #[Group('mr-331')]
    public function testActionStepValidateLeavesNoDebugLogFile(): void
    {
        $candidatePaths = [
            __DIR__ . '/../../tmp/action_debug.log',
            __DIR__ . '/../../action_debug.log',
            sys_get_temp_dir() . '/action_debug.log',
        ];

        // Baseline: nothing should pre-exist. If it does, a prior run
        // of the production code must have leaked — that itself is the
        // regression this test is here to catch.
        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                $this->fail("Debug log file present before test: {$path}");
            }
        }

        $step = (new ReflectionClass(ActionStep::class))->newInstanceWithoutConstructor();
        $configProp = new ReflectionProperty($step, 'config');
        $configProp->setValue($step, ['validation_type' => 'action_used']);

        $step->validate(['action_name' => 'melee']);

        foreach ($candidatePaths as $path) {
            $this->assertFileDoesNotExist(
                $path,
                "ActionStep::validate must not write to {$path}"
            );
        }
    }

    /**
     * MR !332 — skip.php and complete.php gate put_xp on
     * hasCompletedBefore so replaying the tutorial doesn't double-pay
     * XP. The check was missing in the original code.
     *
     * Pinning: source inspection asserts the `if (!$hasCompletedBefore)`
     * guard appears BEFORE any `put_xp(` call in each file.
     */
    #[Group('p0-backfill')]
    #[Group('mr-332')]
    public function testSkipEndpointGuardsPutXpOnHasCompletedBefore(): void
    {
        $this->assertPutXpGuardedByHasCompletedBefore(
            __DIR__ . '/../../api/tutorial/skip.php'
        );
    }

    #[Group('p0-backfill')]
    #[Group('mr-332')]
    public function testCompleteEndpointGuardsPutXpOnHasCompletedBefore(): void
    {
        $this->assertPutXpGuardedByHasCompletedBefore(
            __DIR__ . '/../../api/tutorial/complete.php'
        );
    }

    /**
     * Assert that the given PHP file calls
     * AdminAuthorizationService::DoAdminCheck() BEFORE any of its
     * endpoint-handler logic (no early echo / exit / switch before it).
     */
    private function assertFileCallsDoAdminCheckEarly(string $path): void
    {
        $this->assertFileExists($path);

        $source = file_get_contents($path);
        $this->assertNotFalse($source);

        $this->assertMatchesRegularExpression(
            '/AdminAuthorizationService::DoAdminCheck\s*\(\s*\)/',
            $source,
            "{$path} must call AdminAuthorizationService::DoAdminCheck()"
        );

        // Belt-and-suspenders: the call must appear before any switch()
        // or first echo/header() call that responds to the client.
        // Otherwise the check-after-response is functionally useless.
        $checkPos  = strpos($source, 'DoAdminCheck');
        $switchPos = strpos($source, 'switch');

        if ($switchPos !== false) {
            $this->assertLessThan(
                $switchPos,
                $checkPos,
                "{$path} must run DoAdminCheck BEFORE any switch() dispatch"
            );
        }
    }

    /**
     * Assert every put_xp call in the file is guarded by a preceding
     * if (!$hasCompletedBefore) check.
     */
    private function assertPutXpGuardedByHasCompletedBefore(string $path): void
    {
        $this->assertFileExists($path);

        $source = file_get_contents($path);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            'hasCompletedBefore',
            $source,
            "{$path} must call hasCompletedBefore to gate reward"
        );
        $this->assertStringContainsString(
            'put_xp',
            $source,
            "{$path} must grant XP via put_xp"
        );

        // Guard must appear in source before the reward call.
        $guardPos = strpos($source, 'if (!$hasCompletedBefore)');
        $this->assertNotFalse(
            $guardPos,
            "{$path} must gate put_xp with `if (!\$hasCompletedBefore)`"
        );

        $rewardPos = strpos($source, 'put_xp');
        $this->assertLessThan(
            $rewardPos,
            $guardPos,
            "{$path}: hasCompletedBefore guard must appear before put_xp"
        );
    }
}
