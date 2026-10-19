<?php

namespace Tests\Various;

use App\Service\TurnService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * When an entity plays lives in `turns`, written through TurnService and read
 * into the legacy row by Player::get_row().
 */
#[Group('entities-baseline')]
class TurnServiceTest extends LegacyPlayerFixtureTestCase
{
    public function testAWriteLandsInTheSatellite(): void
    {
        $player = $this->createRealPlayer('GmTour');
        $id = (int) $player->id;

        (new TurnService($this->link))->setNextTurnTime($id, 1_800_000_000);

        $this->assertSame(
            1_800_000_000,
            (int) $this->link->fetchOne('SELECT next_turn_time FROM turns WHERE player_id = ?', [$id])
        );
    }

    /** Opening a turn restarts the counters the turn owns. */
    public function testOpeningATurnRestartsTheTurnsOwnCounters(): void
    {
        $player = $this->createRealPlayer('GmOuverture');
        $id = (int) $player->id;
        $turns = new TurnService($this->link);

        $turns->reschedule($id, 1_800_000_000);
        $turns->touchLastAction($id, 1_799_999_000);

        $turns->openTurn($id, 1_800_086_400, 1_800_005_400);

        $row = $this->link->fetchAssociative('SELECT * FROM turns WHERE player_id = ?', [$id]);

        $this->assertSame(1_800_086_400, (int) $row['next_turn_time']);
        $this->assertSame(0, (int) $row['last_action_time'], 'the turn starts with no action taken');
        $this->assertSame(0, (int) $row['next_turn_rescheduled'], 'the right to reschedule comes back');
        $this->assertSame(1_800_005_400, (int) $row['anti_berserk_time']);
    }

    public function testTheLegacyRowStillCarriesTheTurnFields(): void
    {
        $player = $this->createRealPlayer('GmLegacyTour');
        $id = (int) $player->id;

        (new TurnService($this->link))->reschedule($id, 1_800_000_000);

        $player->get_row();

        $this->assertSame(1_800_000_000, (int) $player->row->nextTurnTime);
        $this->assertSame(1, (int) $player->row->nextTurnRescheduled);
    }

    /** A character is born with its turn row, its first turn due at once. */
    public function testACharacterIsBornWithItsTurnRow(): void
    {
        $before = time();
        $id = (int) $this->createRealPlayer('GmNaissance')->id;

        $this->assertGreaterThanOrEqual(
            $before,
            (int) $this->link->fetchOne('SELECT next_turn_time FROM turns WHERE player_id = ?', [$id])
        );
    }

    /** A structure that does not play gets no row, and reads the defaults. */
    public function testAStructureGetsNoSatelliteRow(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure('mur_pierre', $x, $y);

        (new TurnService($this->link))->ensureRow($id);

        $this->assertFalse(
            $this->link->fetchOne('SELECT player_id FROM turns WHERE player_id = ?', [$id])
        );
    }
}
