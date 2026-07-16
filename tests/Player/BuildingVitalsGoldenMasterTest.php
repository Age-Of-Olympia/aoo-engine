<?php

namespace Tests\Player;

use App\Factory\PlayerFactory;
use App\Service\BuildingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * THE proof the buildings plan stands on
 * (docs/design-buildings-entities.md §5, first risk line): a building —
 * a players row with player_type='building' and a pseudo-race — flows
 * through the UNTOUCHED legacy vitals pipeline:
 *
 *   - get_data() loads it like any player row;
 *   - get_caracs() gives it the pseudo-race base stats (pv 100, rest 0);
 *   - putBonus(['pv' => -x]) wounds it through players_bonus;
 *   - getRemaining('pv') reads the wound back, same-instance and fresh.
 *
 * Mirrors PlayerVitalsGoldenMasterTest on purpose: same pins, other
 * entity branch. Skips when the palissade pseudo-race or the buildings
 * table is absent (migrations Version20260716120000/130000 not run).
 */
#[Group('entities-golden-master')]
#[Group('entities-structure')]
class BuildingVitalsGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private const ARCHETYPE = 'palissade';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT 1 FROM buildings LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('buildings table unavailable (run migrations): ' . $e->getMessage());
        }

        $race = (new \App\Service\RaceService())->getRaceByName(self::ARCHETYPE);
        if ($race === null || $race->getPlayable()) {
            $this->markTestSkipped("pseudo-race 'palissade' not seeded (run migrations).");
        }
    }

    private function placePalissade(): int
    {
        $id = (new BuildingService())->place(
            self::ARCHETYPE,
            (object) ['x' => 0, 'y' => 3, 'z' => 0, 'plan' => 'gaia']
        );
        $this->trackEntityId($id);

        return $id;
    }

    public function testBuildingCaracsAreThePseudoRaceBase(): void
    {
        $id = $this->placePalissade();

        $building = PlayerFactory::legacy($id);
        $building->get_data();
        $this->assertSame(self::ARCHETYPE, (string) $building->data->race);

        $building->get_caracs();
        $this->assertSame(100, (int) $building->caracs->pv, 'palissade base PV must come from the pseudo-race');
        foreach (['mvt', 'a', 'cc', 'f', 'e', 'agi', 'pm'] as $k) {
            $this->assertSame(0, (int) $building->caracs->$k, "a palissade must have 0 '{$k}'");
        }

        $this->assertSame(100, $building->getRemaining('pv'));
    }

    public function testBuildingTakesWoundsThroughPlayersBonus(): void
    {
        $id = $this->placePalissade();
        $building = PlayerFactory::legacy($id);
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);

        $building->putBonus(['pv' => -30]);

        $this->assertSame(70, $building->getRemaining('pv'), 'same-instance view');
        $this->assertSame(
            -30,
            (int) $this->link->fetchOne(
                'SELECT n FROM players_bonus WHERE player_id = ? AND name = "pv"',
                [$id]
            ),
            'the wound must persist as a players_bonus row'
        );
        $this->assertSame(
            70,
            PlayerFactory::legacy($id)->getRemaining('pv'),
            'fresh-instance view'
        );
    }

    public function testPlaceRejectsUnknownAndPlayableArchetypes(): void
    {
        $service = new BuildingService();
        $coords = (object) ['x' => 0, 'y' => 3, 'z' => 0, 'plan' => 'gaia'];

        try {
            $service->place('race_inexistante_' . bin2hex(random_bytes(3)), $coords);
            $this->fail('unknown archetype must be rejected');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Archétype inconnu', $e->getMessage());
        }

        try {
            $service->place('nain', $coords);
            $this->fail('a playable race must be rejected as archetype');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('race jouable', $e->getMessage());
        }
    }

    public function testRemoveDeletesTheBuildingAndItsComponentRows(): void
    {
        $id = $this->placePalissade();
        $building = PlayerFactory::legacy($id);
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);
        $building->putBonus(['pv' => -5]);

        $this->assertTrue((new BuildingService())->remove($id));

        foreach (['players', 'buildings', 'players_bonus'] as $table) {
            $col = $table === 'players' ? 'id' : 'player_id';
            $this->assertFalse(
                $this->link->fetchOne("SELECT 1 FROM {$table} WHERE {$col} = ?", [$id]),
                "{$table} row must be gone after remove()"
            );
        }

        $this->assertFalse(
            (new BuildingService())->remove($id),
            'removing twice must report false, not explode'
        );
    }

    public function testRemoveRefusesNonBuildingRows(): void
    {
        $player = $this->createRealPlayer('GmNotABuilding');

        $this->assertFalse(
            (new BuildingService())->remove($player->id),
            'remove() must never delete a non-building row'
        );
        $this->assertNotFalse(
            $this->link->fetchOne('SELECT 1 FROM players WHERE id = ?', [$player->id]),
            'the real player row must still exist'
        );
    }
}
