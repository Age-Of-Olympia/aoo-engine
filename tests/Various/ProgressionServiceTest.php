<?php

namespace Tests\Various;

use App\Service\ProgressionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * What an entity earns lives in `progression` and is mirrored into `players`
 * until those columns are dropped. These cases pin both halves of that
 * contract, the fallback for a character created before the table, and the
 * conditional debit that makes spending PI safe under concurrency.
 */
#[Group('entities-baseline')]
class ProgressionServiceTest extends LegacyPlayerFixtureTestCase
{
    public function testAGainLandsInBothPlacesWhileTheColumnsRemain(): void
    {
        $player = $this->createRealPlayer('GmGain');
        $id = (int) $player->id;

        (new ProgressionService($this->link))->gain($id, 120, 120, 3);

        $satellite = $this->link->fetchAssociative('SELECT * FROM progression WHERE player_id = ?', [$id]);
        $legacy = $this->link->fetchAssociative('SELECT xp, pi, `rank` FROM players WHERE id = ?', [$id]);

        $this->assertSame(120, (int) $satellite['xp'], 'the satellite holds it');
        $this->assertSame(120, (int) $satellite['pi']);
        $this->assertSame(3, (int) $satellite['rank']);

        $this->assertSame(120, (int) $legacy['xp'], 'and the columns still mirror it');
        $this->assertSame(120, (int) $legacy['pi']);
        $this->assertSame(3, (int) $legacy['rank']);
    }

    public function testTheLegacyRowStillCarriesTheProgressionFields(): void
    {
        $player = $this->createRealPlayer('GmLegacyXp');

        (new ProgressionService($this->link))->gain((int) $player->id, 250, 250, 4);

        $player->get_row();

        $this->assertSame(250, (int) $player->row->xp);
        $this->assertSame(250, (int) $player->row->pi);
        $this->assertSame(4, (int) $player->row->rank);
    }

    public function testAReadFallsBackToTheColumnWhenNoSatelliteRowExists(): void
    {
        $player = $this->createRealPlayer('GmSansProgression');
        $id = (int) $player->id;

        $this->link->executeStatement('DELETE FROM progression WHERE player_id = ?', [$id]);
        $this->link->executeStatement('UPDATE players SET xp = ? WHERE id = ?', [900, $id]);

        $player->get_row();

        $this->assertSame(900, (int) $player->row->xp, 'a character predating the table still answers');
    }

    /**
     * A satellite row born after the character must not start from zero: an
     * increment on a blank row would lose everything already earned.
     */
    public function testASatelliteRowIsSeededFromTheColumnsItMirrors(): void
    {
        $player = $this->createRealPlayer('GmReprise');
        $id = (int) $player->id;

        $this->link->executeStatement('DELETE FROM progression WHERE player_id = ?', [$id]);
        $this->link->executeStatement('UPDATE players SET xp = ?, pi = ? WHERE id = ?', [900, 40, $id]);

        (new ProgressionService($this->link))->gain($id, 100, 100);

        $this->assertSame(
            1000,
            (int) $this->link->fetchOne('SELECT xp FROM progression WHERE player_id = ?', [$id])
        );
        $this->assertSame(
            1000,
            (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$id])
        );
    }

    public function testSpendingPiDebitsBothPlaces(): void
    {
        $player = $this->createRealPlayer('GmDepense');
        $id = (int) $player->id;
        $progression = new ProgressionService($this->link);

        $progression->addPi($id, 30);

        $this->assertTrue($progression->spendPi($id, 12));

        $this->assertSame(
            18,
            (int) $this->link->fetchOne('SELECT pi FROM progression WHERE player_id = ?', [$id])
        );
        $this->assertSame(
            18,
            (int) $this->link->fetchOne('SELECT pi FROM players WHERE id = ?', [$id])
        );
    }

    /**
     * The balance is checked by the UPDATE, so a spend the entity cannot
     * afford changes nothing and says so — two requests arriving together
     * cannot both take the last point.
     */
    public function testASpendBeyondTheBalanceIsRefusedAndChangesNothing(): void
    {
        $player = $this->createRealPlayer('GmDecouvert');
        $id = (int) $player->id;
        $progression = new ProgressionService($this->link);

        $progression->addPi($id, 10);

        $this->assertFalse($progression->spendPi($id, 11));

        $this->assertSame(
            10,
            (int) $this->link->fetchOne('SELECT pi FROM progression WHERE player_id = ?', [$id])
        );
        $this->assertSame(
            10,
            (int) $this->link->fetchOne('SELECT pi FROM players WHERE id = ?', [$id])
        );
    }

    /** Spending the whole balance works once, and only once. */
    public function testTheLastPointCanOnlyBeSpentOnce(): void
    {
        $player = $this->createRealPlayer('GmDernierPi');
        $id = (int) $player->id;
        $progression = new ProgressionService($this->link);

        $progression->addPi($id, 7);

        $this->assertTrue($progression->spendPi($id, 7));
        $this->assertFalse($progression->spendPi($id, 7));

        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT pi FROM progression WHERE player_id = ?', [$id])
        );
    }

    /** A structure earns nothing yet: it keeps answering from its row. */
    public function testAStructureGetsNoSatelliteRow(): void
    {
        $id = $this->placeStructure('mur_pierre', 43, 43);

        (new ProgressionService($this->link))->ensureRow($id);

        $this->assertFalse(
            $this->link->fetchOne('SELECT player_id FROM progression WHERE player_id = ?', [$id])
        );
    }
}
