<?php

namespace Tests\Various;

use App\Service\EntityVisibility;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** One rule per question of "what may this viewer see of that entity". */
#[Group('action')]
class EntityVisibilityTest extends LegacyPlayerFixtureTestCase
{
    public function testTheBoardIsTheSquareOfPerception(): void
    {
        [$x, $y] = $this->farTile();
        $viewer = $this->characterAt('GmVigie', $x, $y);
        $p = (int) $viewer->caracs->p;
        $sees = new EntityVisibility($viewer);

        $this->assertTrue($sees->seesCell($x + $p, $y - $p, 0, 'gaia'), 'corner of the board');
        $this->assertFalse($sees->seesCell($x + $p + 1, $y, 0, 'gaia'), 'one step past it');
        $this->assertFalse($sees->seesCell($x, $y, 1, 'gaia'), 'another floor');
    }

    public function testACharacterIsDetailedWithinPerceptionOnly(): void
    {
        [$x, $y] = $this->farTile();
        $viewer = $this->characterAt('GmOeil', $x, $y);
        $p = (int) $viewer->caracs->p;
        $near = $this->characterAt('GmProche', $x + $p, $y);
        $far = $this->characterAt('GmLointain', $x + $p + 1, $y);
        $sees = new EntityVisibility($viewer);

        $this->assertTrue($sees->seesDetailsOf((int) $viewer->id), 'oneself');
        $this->assertTrue($sees->seesDetailsOf((int) $near->id));
        $this->assertFalse($sees->seesDetailsOf((int) $far->id));
    }

    public function testTwoFactionlessPlayersDoNotShareEffectTimers(): void
    {
        [$x, $y] = $this->farTile();
        $viewer = $this->characterAt('GmSansBanniere', $x, $y);
        $other = $this->characterAt('GmSansBanniereBis', $x + 1, $y);
        $sees = new EntityVisibility($viewer);

        $this->assertFalse($sees->seesEffectTimersOf((int) $other->id, '', ''), "'' is no shared faction");
        $this->assertFalse($sees->seesSecretFaction(''));

        $this->link->executeStatement("UPDATE players SET secretFaction = 'loge' WHERE id = ?", [$viewer->id]);
        $sees = new EntityVisibility($this->loadedCharacter((int) $viewer->id));
        $this->assertTrue($sees->seesEffectTimersOf((int) $other->id, '', 'loge'));
        $this->assertTrue($sees->seesSecretFaction('loge'));
    }

    public function testAChestShowsItsContentsFromBesideOrToItsPeople(): void
    {
        [$x, $y] = $this->farTile();
        $chest = $this->installExemplar('coffre_bois', $x, $y);
        $beside = $this->characterAt('GmVoisin', $x + 1, $y);
        $away = $this->characterAt('GmPasseur', $x + 4, $y);
        $owner = $this->characterAt('GmMaitre', $x + 4, $y + 1);
        $this->link->executeStatement("UPDATE players SET owner_id = ?, faction = '' WHERE id = ?", [$owner->id, $chest]);

        $this->assertTrue((new EntityVisibility($beside))->seesContentsOf($chest), 'beside: anyone');
        $this->assertFalse((new EntityVisibility($away))->seesContentsOf($chest), 'away: not a stranger');
        $this->assertTrue((new EntityVisibility($owner))->seesContentsOf($chest), 'away: its owner');

        $this->link->executeStatement('UPDATE players SET is_open = 0 WHERE id = ?', [$chest]);
        $this->assertFalse((new EntityVisibility($owner))->seesContentsOf($chest), 'shut: nobody');
    }
}
