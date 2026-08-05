<?php

namespace Tests\Various;

use App\Service\TurnService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * When an entity plays lives in `turns` and is mirrored into `players` until
 * those columns are dropped. These cases pin both halves of that contract, and
 * the fallback that makes a character created before the table still readable.
 */
#[Group('entities-baseline')]
class TurnServiceTest extends LegacyPlayerFixtureTestCase
{
    public function testAWriteLandsInBothPlacesWhileTheColumnsRemain(): void
    {
        $player = $this->createRealPlayer('GmTour');
        $id = (int) $player->id;

        (new TurnService($this->link))->setNextTurnTime($id, 1_800_000_000);

        $this->assertSame(
            1_800_000_000,
            (int) $this->link->fetchOne('SELECT next_turn_time FROM turns WHERE player_id = ?', [$id]),
            'the satellite holds it'
        );
        $this->assertSame(
            1_800_000_000,
            (int) $this->link->fetchOne('SELECT nextTurnTime FROM players WHERE id = ?', [$id]),
            'and the column still mirrors it'
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

    public function testAReadFallsBackToTheColumnWhenNoSatelliteRowExists(): void
    {
        $player = $this->createRealPlayer('GmSansTour');
        $id = (int) $player->id;

        $this->link->executeStatement('DELETE FROM turns WHERE player_id = ?', [$id]);
        $this->link->executeStatement(
            'UPDATE players SET nextTurnTime = ? WHERE id = ?',
            [1_700_000_000, $id]
        );

        $player->get_row();

        $this->assertSame(
            1_700_000_000,
            (int) $player->row->nextTurnTime,
            'a character predating the table still answers'
        );
    }

    /**
     * An untouched satellite value is 0, which must not beat a column an
     * unrouted path has just written — that is what NULLIF buys over COALESCE.
     */
    public function testAnEmptySatelliteValueLetsTheColumnAnswer(): void
    {
        $player = $this->createRealPlayer('GmVide');
        $id = (int) $player->id;

        (new TurnService($this->link))->ensureRow($id);
        $this->link->executeStatement('UPDATE turns SET anti_berserk_time = 0 WHERE player_id = ?', [$id]);
        $this->link->executeStatement('UPDATE players SET antiBerserkTime = ? WHERE id = ?', [4_242, $id]);

        $player->get_row();

        $this->assertSame(4_242, (int) $player->row->antiBerserkTime);
    }

    /** A structure takes no turn of its own yet: it keeps answering from its row. */
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
