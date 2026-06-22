<?php

namespace Tests\Action\Combat;

use App\Simulation\SimulatedItem;
use App\Simulation\SimulatedPlayer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class SimulatedPlayerTest extends TestCase
{
    private function player(): SimulatedPlayer
    {
        return new SimulatedPlayer(
            1,
            ['cc' => 12],
            ['pa' => 6, 'pv' => 10, 'pm' => 10, 'mvt' => 6],
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia'],
        );
    }

    public function testEquippedWeaponStatsAreFoldedIntoCaracsLikeARealPlayer(): void
    {
        $weapon = SimulatedItem::fromData(['subtype' => 'melee', 'name' => 'Gladius', 'cc' => 2, 'fixedF' => 7]);
        $player = new SimulatedPlayer(
            1,
            ['cc' => 12, 'f' => 3],
            ['pv' => 10],
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia'],
            [],
            (object) ['main1' => $weapon],
        );

        $this->assertSame(14, $player->caracs->cc); // 12 + weapon's 2
        $this->assertSame(7, $player->caracs->f);   // fixedF overrides
    }

    public function testIsOnTileTypeReadsInjectedTilesNotTheDb(): void
    {
        $player = new SimulatedPlayer(
            1,
            ['cc' => 12],
            ['mvt' => 6],
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia'],
            [],
            null,
            [],
            [],
            ['routes'],
        );

        $this->assertTrue($player->isOnTileType('routes'));
        $this->assertFalse($player->isOnTileType('eau'));
    }

    public function testIsOnTileTypeDefaultsToFalseWhenNoTilesInjected(): void
    {
        $this->assertFalse($this->player()->isOnTileType('routes'));
    }

    public function testApplyItemCaracsIsTheSharedFoldingRule(): void
    {
        $caracs = (object) ['cc' => 10, 'ct' => 4];
        \Classes\Player::applyItemCaracs($caracs, SimulatedItem::fromData(['cc' => 2]));

        $this->assertSame(12, $caracs->cc);
        $this->assertSame(4, $caracs->ct); // untouched
    }

    /**
     * The simulator runs the real outcome instructions against players 1/2, so
     * every world-mutating Player method must be inert — otherwise a preview
     * writes to map_items / players_actions / players. These call paths would
     * hit the DB on a real Player; here they must complete without doing so.
     */
    public function testWorldMutationsAreInert(): void
    {
        $player = $this->player();

        $player->drop(new SimulatedItem('weapon', 'Épée'), 1);
        $player->add_action('fouiller');
        $player->end_action('fouiller');
        $player->put_pf(5);
        $player->putBonus(['pv' => -3]);
        $player->put_xp(100);
        $player->go((object) ['x' => 1, 'y' => 0, 'z' => 0, 'plan' => 'gaia']);

        // Injected state is unchanged: the mutations resolved to no-ops.
        $this->assertSame(10, $player->getRemaining('pv'));
        $this->assertSame(6, $player->getRemaining('mvt'));
    }
}
