<?php

namespace Tests\Various;

use App\Service\EffectService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * DB-backed EffectService (effects / effect_corruption_materials), the
 * replacement for the EFFECTS_* / ELE_* / ITEM_CORRUPT* constants of
 * config/constants.php.
 *
 * Pins the invariants those constants carried, on the migration seed:
 *  - the master-list contract: exists() knows a seeded effect, refuses an
 *    unknown one; getIcon() falls back on FALLBACK_ICON;
 *  - the five ephemeral stances (ex-EFFECTS_HIDDEN);
 *  - the buff/debuff carac maps (ex-ELE_DEBUFFS / ELE_BUFFS);
 *  - the elemental cycle and its COMPUTED inverse (ex-ELE_CONTROLS /
 *    ELE_IS_CONTROLED);
 *  - the corruption maps under the normalized 'corruption_des_plantes'
 *    key (ex-ITEM_CORRUPTIONS, fixed spelling);
 *  - map markers (trace_pas*) excluded from the gameplay name list.
 *
 * Skips cleanly when the DB is unreachable (same convention as
 * FactionServiceTest).
 */
class EffectServiceTest extends TestCase
{
    private EffectService $service;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        EffectService::clearCache();
        $this->service = new EffectService();
    }

    public function testTheCatalogIsTheExistenceValidator(): void
    {
        $this->assertTrue($this->service->exists('feu'));
        $this->assertTrue($this->service->exists('trace_pas_ne'));
        $this->assertFalse($this->service->exists('effet_inconnu'));

        $this->assertSame('ra-small-fire', $this->service->getIcon('feu'));
        $this->assertSame(EffectService::FALLBACK_ICON, $this->service->getIcon('effet_inconnu'));
    }

    public function testTheFiveCombatStancesAreHidden(): void
    {
        $this->assertEqualsCanonicalizing(
            ['parade', 'leurre', 'dedoublement', 'cle_de_bras', 'pas_de_cote'],
            $this->service->getHiddenNames()
        );
        $this->assertTrue($this->service->isHidden('parade'));
        $this->assertFalse($this->service->isHidden('feu'));
    }

    public function testCaracModifierMapsMatchTheOldConstants(): void
    {
        $debuffs = $this->service->getDebuffCaracs();
        $this->assertSame('e', $debuffs['feu']);
        $this->assertSame('mvt', $debuffs['eau']);
        $this->assertSame('mvt', $debuffs['styx']);
        $this->assertSame('p', $debuffs['aveuglement']);

        $this->assertSame(['acuite_visuelle' => 'p'], $this->service->getBuffCaracs());
    }

    public function testTheElementalCycleInverseIsComputed(): void
    {
        // Le cycle : eau→feu→diamant→ronce→boue→eau. Des listes — un
        // effet peut en annuler plusieurs.
        $this->assertSame(['feu'], $this->service->getControlledEffects('eau'));
        $this->assertSame(['eau'], $this->service->getControllersOf('feu'));
        $this->assertSame(['boue'], $this->service->getControllersOf('eau'));
        $this->assertSame([], $this->service->getControlledEffects('poison'));
        $this->assertSame([], $this->service->getControllersOf('poison'));
    }

    public function testCorruptionMapsUseTheNormalizedPlantsKey(): void
    {
        $materials = $this->service->getCorruptionMaterials();

        $this->assertArrayHasKey('corruption_des_plantes', $materials);
        $this->assertArrayNotHasKey('corruption_du_plantes', $materials);
        $this->assertContains('adonis', $materials['corruption_des_plantes']);
        $this->assertSame(['bronze', 'nickel'], $materials['corruption_du_metal']);

        $chances = $this->service->getCorruptionBreakChances();
        $this->assertSame(array_keys($materials), array_keys($chances));
    }

    public function testMapMarkersAreExcludedFromGameplayLists(): void
    {
        $names = $this->service->getGameplayEffectNames();

        $this->assertContains('feu', $names);
        $this->assertNotContains('trace_pas', $names);
        $this->assertNotContains('trace_pas_so', $names);
    }

    private function bootstrapOrSkip(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1 FROM effects LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('effects table unreachable: ' . $e->getMessage());
        }
    }
}
