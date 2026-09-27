<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use App\Service\EntityVisibility;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** One rule per question of "what may this viewer see of that entity". */
#[Group('action')]
class EntityVisibilityTest extends LegacyPlayerFixtureTestCase
{
    private function standingAt(string $name, int $x, int $y): Player
    {
        $id = (int) $this->createRealPlayer($name)->id;
        $this->link->executeStatement(
            "UPDATE players SET coords_id = ?, faction = '', secretFaction = '' WHERE id = ?",
            [$this->coordsIdOn('gaia', $x, $y), $id]
        );
        (new \App\Service\Map\EntityCellService($this->link))->syncCells($id);

        return $this->loaded($id);
    }

    private function loaded(int $id): Player
    {
        $player = PlayerFactory::legacy($id);
        $player->get_data();
        $player->get_caracs();
        $player->getCoords();

        return $player;
    }

    public function testTheBoardIsTheSquareOfPerception(): void
    {
        [$x, $y] = $this->farTile();
        $viewer = $this->standingAt('GmVigie', $x, $y);
        $p = (int) $viewer->caracs->p;
        $sees = new EntityVisibility($viewer);

        $this->assertTrue($sees->seesCell($x + $p, $y - $p, 0, 'gaia'), 'corner of the board');
        $this->assertFalse($sees->seesCell($x + $p + 1, $y, 0, 'gaia'), 'one step past it');
        $this->assertFalse($sees->seesCell($x, $y, 1, 'gaia'), 'another floor');
    }

    public function testACharacterIsDetailedWithinPerceptionOnly(): void
    {
        [$x, $y] = $this->farTile();
        $viewer = $this->standingAt('GmOeil', $x, $y);
        $p = (int) $viewer->caracs->p;
        $near = $this->standingAt('GmProche', $x + $p, $y);
        $far = $this->standingAt('GmLointain', $x + $p + 1, $y);
        $sees = new EntityVisibility($viewer);

        $this->assertTrue($sees->seesDetailsOf((int) $viewer->id), 'oneself');
        $this->assertTrue($sees->seesDetailsOf((int) $near->id));
        $this->assertFalse($sees->seesDetailsOf((int) $far->id));
    }

    public function testTwoFactionlessPlayersDoNotShareEffectTimers(): void
    {
        [$x, $y] = $this->farTile();
        $viewer = $this->standingAt('GmSansBanniere', $x, $y);
        $other = $this->standingAt('GmSansBanniereBis', $x + 1, $y);
        $sees = new EntityVisibility($viewer);

        $this->assertFalse($sees->seesEffectTimersOf((int) $other->id, '', ''), "'' is no shared faction");
        $this->assertFalse($sees->seesSecretFaction(''));

        $this->link->executeStatement("UPDATE players SET secretFaction = 'loge' WHERE id = ?", [$viewer->id]);
        $sees = new EntityVisibility($this->loaded((int) $viewer->id));
        $this->assertTrue($sees->seesEffectTimersOf((int) $other->id, '', 'loge'));
        $this->assertTrue($sees->seesSecretFaction('loge'));
    }

    public function testAChestShowsItsContentsFromBesideOrToItsPeople(): void
    {
        [$x, $y] = $this->farTile();
        $chest = $this->installExemplar('coffre_bois', $x, $y);
        $beside = $this->standingAt('GmVoisin', $x + 1, $y);
        $away = $this->standingAt('GmPasseur', $x + 4, $y);
        $owner = $this->standingAt('GmMaitre', $x + 4, $y + 1);
        $this->link->executeStatement("UPDATE players SET owner_id = ?, faction = '' WHERE id = ?", [$owner->id, $chest]);

        $this->assertTrue((new EntityVisibility($beside))->seesContentsOf($chest), 'beside: anyone');
        $this->assertFalse((new EntityVisibility($away))->seesContentsOf($chest), 'away: not a stranger');
        $this->assertTrue((new EntityVisibility($owner))->seesContentsOf($chest), 'away: its owner');

        $this->link->executeStatement('UPDATE players SET is_open = 0 WHERE id = ?', [$chest]);
        $this->assertFalse((new EntityVisibility($owner))->seesContentsOf($chest), 'shut: nobody');
    }
}
