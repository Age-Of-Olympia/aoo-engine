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

    public function testListBuildingsReportsPositionStateAndCurrentPv(): void
    {
        $owner = $this->createRealPlayer('GmOwner');
        $id = (new BuildingService())->place(
            self::ARCHETYPE,
            (object) ['x' => 0, 'y' => 3, 'z' => 0, 'plan' => 'gaia'],
            $owner->id
        );
        $this->trackEntityId($id);

        $building = PlayerFactory::legacy($id);
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);
        $building->putBonus(['pv' => -40]);

        $rows = array_values(array_filter(
            (new BuildingService())->listBuildings(),
            static fn (array $row): bool => $row['id'] === $id
        ));
        $this->assertCount(1, $rows, 'the placed building must appear in the admin roster');

        $row = $rows[0];
        $this->assertSame(self::ARCHETYPE, $row['archetype']);
        $this->assertSame('built', $row['build_state']);
        $this->assertSame(['x' => 0, 'y' => 3, 'plan' => 'gaia'], ['x' => $row['x'], 'y' => $row['y'], 'plan' => $row['plan']]);
        $this->assertSame(100, $row['max_pv']);
        $this->assertSame(60, $row['current_pv'], 'current PV must reflect the players_bonus ledger');
        $this->assertSame($owner->id, $row['owner_id']);
    }

    public function testRestoreResetsPvAndBuiltState(): void
    {
        $id = $this->placePalissade();
        $building = PlayerFactory::legacy($id);
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);
        $building->putBonus(['pv' => -100]);
        $this->link->executeStatement(
            "UPDATE buildings SET build_state = 'ruin' WHERE player_id = ?",
            [$id]
        );

        $this->assertTrue((new BuildingService())->restore($id));

        $this->assertSame(100, PlayerFactory::legacy($id)->getRemaining('pv'), 'restore must reset full PV');
        $this->assertSame(
            'built',
            $this->link->fetchOne('SELECT build_state FROM buildings WHERE player_id = ?', [$id]),
            'restore must flip the state back to built'
        );

        $realPlayer = $this->createRealPlayer('GmNotABuilding');
        $this->assertFalse(
            (new BuildingService())->restore($realPlayer->id),
            'restore() must refuse character rows — healing players is the heal mechanic, not an admin reset'
        );
    }

    public function testHealingAStructureIsJustPutBonus(): void
    {
        // The user-facing "repair" concept IS the heal mechanic: a positive
        // pv bonus restores a wounded structure through the same ledger as a
        // character, clamped at the pseudo-race max. Pin it so the future
        // heal-type repair action needs zero new plumbing.
        $id = $this->placePalissade();
        $building = PlayerFactory::legacy($id);
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);

        $building->putBonus(['pv' => -30]);
        $building->putBonus(['pv' => 10]);
        $this->assertSame(80, PlayerFactory::legacy($id)->getRemaining('pv'), 'partial heal');

        $building->putBonus(['pv' => 999]);
        $this->assertSame(100, PlayerFactory::legacy($id)->getRemaining('pv'), 'overheal clamps at the pseudo-race max');
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
