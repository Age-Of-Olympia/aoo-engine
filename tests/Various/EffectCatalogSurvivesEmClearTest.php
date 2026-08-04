<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Service\EffectService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The static effect catalog outlives EntityManager clears — every
 * fixture test starts with one. Reading a detached effect's controls
 * must not push MANAGED EffectControl rows pointing at a DETACHED
 * parent into the identity map: the next flush, any flush, would die
 * on the "new" entity it finds through EffectControl#effect. This is
 * the CI-only failure of 2026-08-04, replayed deterministically.
 */
#[Group('action')]
class EffectCatalogSurvivesEmClearTest extends LegacyPlayerFixtureTestCase
{
    public function testControlsReadAfterAClearLeaveTheNextFlushSound(): void
    {
        $em = EntityManagerFactory::getEntityManager();
        $withControls = $em->getConnection()->fetchOne(
            'SELECT e.name FROM effects e JOIN effect_controls c ON c.effect_id = e.id LIMIT 1'
        );
        if ($withControls === false) {
            $this->markTestSkipped('no effect carries controls in this catalog.');
        }
        $name = (string) $withControls;

        // Force the exact CI sequence: a catalog warmed fresh, then cleared.
        $catalogProperty = new \ReflectionProperty(EffectService::class, 'catalog');
        $catalogProperty->setValue(null, null);

        $service = new EffectService();
        $service->getEffectByName($name);
        $em->clear();

        $service->getControlledEffects($name);

        $player = $this->createRealPlayer('GmAlchimiste');
        $player->get_caracs();
        $player->add_effect($name, 2);

        $this->assertSame(
            1,
            (int) $this->link->fetchOne(
                'SELECT COUNT(*) FROM players_effects WHERE player_id = ? AND name = ?',
                [$player->id, $name]
            ),
            'the flush after a catalog clear must stay sound'
        );
    }
}
