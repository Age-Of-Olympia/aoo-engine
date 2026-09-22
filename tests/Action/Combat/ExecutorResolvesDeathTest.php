<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Death belongs to the executor: a blow that takes the last hit point kills
 * on its own, with no help from the page that started the action.
 *
 * The rule used to live in action.php, so an action started anywhere else
 * (a move that digs, an API) left its target at zero hit points, alive.
 */
#[Group('entities-baseline')]
class ExecutorResolvesDeathTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();
    }

    public function testABlowThatTakesTheLastPointKillsWithoutThePage(): void
    {
        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        [$x, $y] = $this->farTile();
        $chestId = $this->installExemplar('coffre_bois', $x, $y);
        $this->assertNotNull(
            $this->link->fetchOne('SELECT 1 FROM players WHERE id = ?', [$chestId])
        );

        $actor = $this->createRealPlayer('GmBourreau');
        $this->movePlayerTo((int) $actor->id, $x, $y - 1);
        $actor->getCoords();
        $actor->get_caracs();

        // One point left: the next blow that lands is lethal.
        $chest = PlayerFactory::legacy($chestId);
        $chest->get_caracs();
        $chest->putBonus(['pv' => -($chest->getRemaining('pv') - 1)]);
        $this->assertSame(1, $chest->getRemaining('pv'));

        // The attack rolls, so strike until one lands.
        $output = '';
        for ($blow = 1; $blow <= 20; $blow++) {
            $target = PlayerFactory::legacy($chestId);
            $target->get_caracs();

            $executor = new ActionExecutorService($action, $actor, $target);
            ob_start();
            try {
                $executor->executeAction();
            } finally {
                ob_end_clean();
            }
            $output = $executor->getDeathOutput();

            if ($output !== '') {
                break;
            }
        }

        $this->assertStringContainsString(
            'Vous détruisez la structure.',
            $output,
            'the executor reports the kill it resolved'
        );
        $this->assertFalse(
            (bool) $this->link->fetchOne('SELECT 1 FROM item_instances i JOIN players e ON e.id = i.entity_id WHERE e.id = ? AND i.destroyed = 0', [$chestId]),
            'the chest is destroyed, without the page calling ProcessTargetDeath'
        );
    }
}
