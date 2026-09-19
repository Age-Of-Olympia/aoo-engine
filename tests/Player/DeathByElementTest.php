<?php

namespace Tests\Player;

use App\Service\EffectService;
use App\Service\PlayerService;
use Classes\Element;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Stepping on an element whose effect takes PV can kill: the walker at
 * 0 PV goes to the enfers, with the log and the XP loss of any death.
 */
#[Group('database')]
class DeathByElementTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        $this->link->executeStatement("DELETE FROM map_elements WHERE name = 'lave_test'");
        $this->link->executeStatement("DELETE FROM effects WHERE name = 'lave_test'");
        EffectService::clearCache();
        parent::tearDown();
    }

    public function testAWalkerDrainedByTheGroundDies(): void
    {
        $this->link->executeStatement(
            "INSERT INTO effects (name, label, pv_on_apply) VALUES ('lave_test', 'Lave', -5)"
        );
        EffectService::clearCache();

        $player = $this->createRealPlayer('GmLave');
        $player->get_caracs();
        // Down to 2 PV: the next step is the last.
        $player->putBonus(['pv' => -((int) $player->caracs->pv - 2)]);

        $cell = $this->tile(1, 0);
        Element::put('lave_test', (int) \Classes\View::get_coords_id($cell), 4);

        $player->go($cell);
        $this->assertLessThan(1, $player->getRemaining('pv'), 'the element took the last PV');
        $this->assertSame(1, (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM players_logs WHERE player_id = ? AND type = 'move' AND text LIKE '%PV −5%'", [$player->id]
        ), 'the step is in the walker\'s log');

        ob_start();
        PlayerService::processSelfDeath($player, 'aux éléments');
        ob_end_clean();

        $plan = (string) $this->link->fetchOne(
            'SELECT c.plan FROM players p JOIN coords c ON c.id = p.coords_id WHERE p.id = ?', [$player->id]
        );
        $this->assertSame(plans()->deathPlan(), $plan, 'dead where they stood, sent to the enfers');
    }
}
