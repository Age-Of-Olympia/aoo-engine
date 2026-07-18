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

    public function testCombatAndTurnBehaviorsMatchTheOldHardcodedRules(): void
    {
        $carry = static function (string $name, int $value = 1): \App\Entity\PlayerEffect {
            $entry = new \App\Entity\PlayerEffect();
            $entry->setName($name);
            $entry->setValue($value);
            return $entry;
        };

        // Jets : ex-dexterite/maladresse (attaque), protection/vulnerabilite (défense).
        $mods = $this->service->modifierContributions([$carry('dexterite', 2), $carry('maladresse', 1)], 'getRollAttackMod');
        $this->assertSame(2, $mods['pos']);
        $this->assertSame(1, $mods['neg']);
        $this->assertSame(['Dextérité'], $mods['posLabels']);

        $mods = $this->service->modifierContributions([$carry('protection', 3)], 'getRollDefenseMod');
        $this->assertSame(3, $mods['pos']);

        // Dégâts : ex-agressivite/faiblesse, fragilite/armure, encaisse ×0.75.
        $mods = $this->service->modifierContributions([$carry('agressivite', 2)], 'getDamageDealtMod');
        $this->assertSame(2, $mods['pos']);
        $mods = $this->service->modifierContributions([$carry('armure', 2)], 'getDamageTakenMod');
        $this->assertSame(2, $mods['neg']);
        $this->assertSame(0.75, $this->service->damageTakenFactor([$carry('encaisse')]));
        $this->assertSame(1.0, $this->service->damageTakenFactor([$carry('feu')]));

        // Poussées : ex-renforcement / stabilite / instabilite.
        $this->assertSame(1, $this->service->modifierContributions([$carry('renforcement')], 'getPushAttackMod')['pos']);
        $this->assertSame(1, $this->service->modifierContributions([$carry('instabilite')], 'getPushDefenseMod')['neg']);

        // Tour : poison bloque la récup PV, poison_magique la récup PM,
        // regeneration régénère, ralentissement retire du Mvt.
        $blockers = $this->service->turnEffects([$carry('poison'), $carry('regeneration')], 'block_recovery', 'pv');
        $this->assertCount(1, $blockers);
        $this->assertSame('poison', $blockers[0]->getName());
        $this->assertSame([], $this->service->turnEffects([$carry('poison')], 'block_recovery', 'pm'));
        $this->assertCount(1, $this->service->turnEffects([$carry('regeneration')], 'turn_regen'));
        $this->assertCount(1, $this->service->turnEffects([$carry('ralentissement')], 'turn_mvt_malus'));
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
