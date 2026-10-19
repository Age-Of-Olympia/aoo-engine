<?php

namespace Tests\Various;

use App\Service\ProgressionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * What an entity earns lives in `progression`. These cases pin the writes, the
 * reads, and the conditional debit that makes spending PI safe under
 * concurrency.
 */
#[Group('entities-baseline')]
class ProgressionServiceTest extends LegacyPlayerFixtureTestCase
{
    public function testAGainLandsInTheSatellite(): void
    {
        $player = $this->createRealPlayer('GmGain');
        $id = (int) $player->id;

        (new ProgressionService($this->link))->gain($id, 120, 120, 3);

        $satellite = $this->link->fetchAssociative('SELECT * FROM progression WHERE player_id = ?', [$id]);

        $this->assertSame(120, (int) $satellite['xp']);
        $this->assertSame(120, (int) $satellite['pi']);
        $this->assertSame(3, (int) $satellite['rank']);
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

    public function testACharacterWithoutRowReadsTheDefaults(): void
    {
        $player = $this->createRealPlayer('GmSansProgression');
        $id = (int) $player->id;

        $this->link->executeStatement('DELETE FROM progression WHERE player_id = ?', [$id]);

        $player->get_row();

        $this->assertSame(0, (int) $player->row->xp);
        $this->assertSame(1, (int) $player->row->rank);
        $this->assertSame(['xp' => 0, 'rank' => 1], (new ProgressionService($this->link))->of([$id])[$id]);
    }

    /** The first gain creates the missing row instead of being lost. */
    public function testAGainCreatesTheMissingRow(): void
    {
        $player = $this->createRealPlayer('GmReprise');
        $id = (int) $player->id;

        $this->link->executeStatement('DELETE FROM progression WHERE player_id = ?', [$id]);

        (new ProgressionService($this->link))->gain($id, 100, 100);

        $this->assertSame(
            100,
            (int) $this->link->fetchOne('SELECT xp FROM progression WHERE player_id = ?', [$id])
        );
    }

    public function testSeveralLevelsAreReadAtOnce(): void
    {
        $first = (int) $this->createRealPlayer('GmNiveauA')->id;
        $second = (int) $this->createRealPlayer('GmNiveauB')->id;
        $progression = new ProgressionService($this->link);

        $progression->gain($first, 300, 0, 4);

        $this->assertSame(
            [$first => ['xp' => 300, 'rank' => 4], $second => ['xp' => 0, 'rank' => 1]],
            $progression->of([$first, $second])
        );
    }

    public function testSpendingPiDebitsTheBalance(): void
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

    /** A structure that does not play gets no row, and reads the defaults. */
    public function testAStructureGetsNoSatelliteRow(): void
    {
        $id = $this->placeStructure('mur_pierre', 43, 43);

        (new ProgressionService($this->link))->ensureRow($id);

        $this->assertFalse(
            $this->link->fetchOne('SELECT player_id FROM progression WHERE player_id = ?', [$id])
        );
    }
}
