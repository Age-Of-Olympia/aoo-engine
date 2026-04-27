<?php

namespace Tests\Tutorial;

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
 *   3. DB row tutorial_settings.global_enabled (cached for 5 min)
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
 * + the private $settingsCache priming pattern established in
 * TutorialPlaceholderServiceTest. That pins the env-precedence rule
 * and the cache-fallback behaviour, which are the actual mistakes
 * future refactors are likely to make.
 */
class TutorialFeatureFlagTest extends TestCase
{
    private ReflectionProperty $cacheProp;

    protected function setUp(): void
    {
        // Cache is a private static — needs reflection to prime/reset.
        $this->cacheProp = new ReflectionProperty(TutorialFeatureFlag::class, 'settingsCache');
        $this->cacheProp->setValue(null, null);

        unset($_ENV['TUTORIAL_V2_ENABLED']);
    }

    protected function tearDown(): void
    {
        $this->cacheProp->setValue(null, null);
        unset($_ENV['TUTORIAL_V2_ENABLED']);
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

    #[Group('tutorial-feature-flag')]
    public function testEnvVarTrueEnablesGlobally(): void
    {
        $_ENV['TUTORIAL_V2_ENABLED'] = 'true';

        $this->assertTrue(TutorialFeatureFlag::isEnabled());
    }

    #[Group('tutorial-feature-flag')]
    public function testEnvVarFalseDisablesGlobally(): void
    {
        $_ENV['TUTORIAL_V2_ENABLED'] = 'false';

        // Even with the cached DB setting saying enabled, env wins.
        $this->primeCache(['global_enabled' => 'true']);

        $this->assertFalse(TutorialFeatureFlag::isEnabled());
    }

    #[Group('tutorial-feature-flag')]
    public function testFallsBackToCachedDbSettingWhenEnvAbsent(): void
    {
        $this->primeCache(['global_enabled' => 'true']);

        $this->assertTrue(TutorialFeatureFlag::isEnabled());
    }

    #[Group('tutorial-feature-flag')]
    public function testReturnsFalseWhenNoSourceProvidesValue(): void
    {
        // Empty cache simulates "DB query returned no rows" — the safe
        // default per the doc comment is OFF.
        $this->primeCache([]);

        $this->assertFalse(TutorialFeatureFlag::isEnabled());
    }

    #[Group('tutorial-feature-flag')]
    public function testWhitelistFromCachedCsvSetting(): void
    {
        $this->primeCache(['whitelisted_players' => '10,20,30']);

        $this->assertSame([10, 20, 30], TutorialFeatureFlag::getWhitelistedPlayers());
    }

    #[Group('tutorial-feature-flag')]
    public function testWhitelistDropsZeroAndNegativeIdsFromCsv(): void
    {
        // intval('abc') is 0, then array_filter drops <=0. Same for
        // explicit "-5". Pins the input-sanitisation contract.
        $this->primeCache(['whitelisted_players' => '10,abc,-5,20']);

        $result = array_values(TutorialFeatureFlag::getWhitelistedPlayers());

        $this->assertSame([10, 20], $result);
    }

    #[Group('tutorial-feature-flag')]
    public function testWhitelistFallsBackToHardcodedDevAccountsWhenEmpty(): void
    {
        // Empty cache + no constant defined → the [1, 2, 3] dev fallback.
        // Documented in the source as "Default test players" — pinning it
        // catches future refactors that drop the fallback.
        $this->primeCache([]);

        $this->assertSame([1, 2, 3], TutorialFeatureFlag::getWhitelistedPlayers());
    }

    #[Group('tutorial-feature-flag')]
    public function testIsEnabledForPlayerHonorsWhitelistWhenGloballyDisabled(): void
    {
        // global_enabled missing → isEnabled() returns false → the
        // whitelist gate is the ONLY way through.
        $this->primeCache(['whitelisted_players' => '42,99']);

        $this->assertTrue(TutorialFeatureFlag::isEnabledForPlayer(42));
        $this->assertTrue(TutorialFeatureFlag::isEnabledForPlayer(99));
        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(100));
    }

    #[Group('tutorial-feature-flag')]
    public function testIsEnabledForPlayerRejectsNpcs(): void
    {
        // NPCs use negative IDs — globally enabled OR whitelisted, they
        // must never reach the tutorial flow.
        $this->primeCache([
            'global_enabled'      => 'true',
            'whitelisted_players' => '-1,-1000023',
        ]);

        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(-1));
        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(-1000023));
    }

    #[Group('tutorial-feature-flag')]
    public function testClearCacheResetsState(): void
    {
        $this->primeCache(['global_enabled' => 'true']);
        $this->assertTrue(TutorialFeatureFlag::getCacheStats()['cached']);

        TutorialFeatureFlag::clearCache();

        $this->assertFalse(TutorialFeatureFlag::getCacheStats()['cached']);
    }

    #[Group('tutorial-feature-flag')]
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
