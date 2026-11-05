<?php

namespace Tests\Tutorial;

use App\Module\Module;
use App\Module\Modules;
use App\Tutorial\TutorialFeatureFlag;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * D4 Phase B — first deferred tutorial test that exercises real
 * fallback-chain logic.
 *
 * The flag has four ordered sources:
 *   1. $_ENV['TUTORIAL_V2_ENABLED']
 *   2. PHP constant TUTORIAL_V2_ENABLED (defined in config.php)
 *   3. the tutorial module (Modules), whose testers make the whitelist
 *   4. default false
 *
 * Source 2 (constant) and the DB path's actual query are NOT covered
 * here:
 *   - PHP constants are process-global and cannot be undefined; testing
 *     them cleanly requires runInSeparateProcess which trades runtime
 *     for what would only verify a single `defined()` check (KISS:
 *     not worth it in Phase B);
 *   - the actual DB read is tested by Cypress end-to-end.
 *
 * What IS covered: every other branch reachable via $_ENV manipulation
 * + Modules::force() for the module state. That pins the env-precedence
 * rule and the module fallback, which are the actual mistakes future
 * refactors are likely to make.
 */
#[Group('tutorial-feature-flag')]
class TutorialFeatureFlagTest extends TestCase
{
    private ReflectionProperty $cacheProp;

    protected function setUp(): void
    {
        // Cache is a private static — needs reflection to prime/reset.
        $this->cacheProp = new ReflectionProperty(TutorialFeatureFlag::class, 'settingsCache');
        $this->cacheProp->setValue(null, null);

        unset($_ENV['TUTORIAL_V2_ENABLED']);
        Modules::force(Module::Tutorial, false);
    }

    protected function tearDown(): void
    {
        $this->cacheProp->setValue(null, null);
        unset($_ENV['TUTORIAL_V2_ENABLED']);
        Modules::reset();
    }

    /**
     * Prime the private settings cache with the given key/value pairs
     * so subsequent isEnabled() / getWhitelistedPlayers() calls hit the
     * cache instead of touching the database.
     *
     * @param array<string, string> $settings
     */
    private function primeCache(array $settings): void
    {
        $this->cacheProp->setValue(null, [
            'data'      => $settings,
            'timestamp' => time(),
        ]);
    }

    public function testEnvVarTrueEnablesGlobally(): void
    {
        $_ENV['TUTORIAL_V2_ENABLED'] = 'true';

        $this->assertTrue(TutorialFeatureFlag::isEnabled());
    }

    public function testEnvVarFalseDisablesGlobally(): void
    {
        $_ENV['TUTORIAL_V2_ENABLED'] = 'false';

        // Even with the module on, env wins.
        Modules::force(Module::Tutorial, true);

        $this->assertFalse(TutorialFeatureFlag::isEnabled());
    }

    public function testFallsBackToModuleWhenEnvAbsent(): void
    {
        Modules::force(Module::Tutorial, true);

        $this->assertTrue(TutorialFeatureFlag::isEnabled());
    }

    public function testReturnsFalseWhenModuleOff(): void
    {
        $this->assertFalse(TutorialFeatureFlag::isEnabled());
    }

    public function testWhitelistIsTheModuleTesters(): void
    {
        Modules::force(Module::Tutorial, false, [10, 20, 30]);

        $this->assertSame([10, 20, 30], TutorialFeatureFlag::getWhitelistedPlayers());
    }

    public function testWhitelistDefaultsToEmpty(): void
    {
        // No default whitelist: the old [1, 2, 3] dev fallback leaked
        // tutorial access to the first three real player IDs in prod.
        $this->assertSame([], TutorialFeatureFlag::getWhitelistedPlayers());
    }

    public function testIsEnabledForPlayerHonorsWhitelistWhenGloballyDisabled(): void
    {
        // Module off → isEnabled() returns false → the testers gate is
        // the ONLY way through.
        Modules::force(Module::Tutorial, false, [42, 99]);

        $this->assertTrue(TutorialFeatureFlag::isEnabledForPlayer(42));
        $this->assertTrue(TutorialFeatureFlag::isEnabledForPlayer(99));
        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(100));
    }

    public function testIsEnabledForPlayerRejectsNpcs(): void
    {
        // NPCs use negative IDs — globally enabled OR whitelisted, they
        // must never reach the tutorial flow.
        Modules::force(Module::Tutorial, true, [-1, -1000023]);

        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(-1));
        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(-1000023));
    }

    public function testClearCacheResetsState(): void
    {
        $this->primeCache(['skip_reward_xp' => '50']);
        $this->assertTrue(TutorialFeatureFlag::getCacheStats()['cached']);

        TutorialFeatureFlag::clearCache();

        $this->assertFalse(TutorialFeatureFlag::getCacheStats()['cached']);
    }

    public function testGetCacheStatsShapeIsStable(): void
    {
        // Empty state — public consumers (admin dashboard, ops scripts)
        // depend on these keys existing.
        $stats = TutorialFeatureFlag::getCacheStats();

        $this->assertArrayHasKey('cached', $stats);
        $this->assertArrayHasKey('ttl_seconds', $stats);
        $this->assertSame(300, $stats['ttl_seconds']);
        $this->assertFalse($stats['cached']);
    }
}
