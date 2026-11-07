<?php

namespace Tests\Action\Combat;

use App\Action\OutcomeInstruction\DamageObjectOutcomeInstruction;
use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\View\ActionResultsView;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * What an outcome did on a missed attack reaches the results, and through
 * them the event details (#137): the malus a miss inflicts, or an item
 * broken by an outcome set to run on failure.
 */
#[Group('action-combat')]
class MissOutcomeLinesShownTest extends LegacyPlayerFixtureTestCase
{
    public function testTheMalusOfAMissIsShown(): void
    {
        $actor = $this->createRealPlayer('GmRate');
        $target = $this->createRealPlayer('GmEsquive');
        $this->movePlayerTo($target->id, 0, 1);
        // Forty ranks of agility make the miss certain.
        for ($i = 0; $i < 40; $i++) {
            $this->link->executeStatement("INSERT INTO players_upgrades (player_id, name, cost) VALUES (?, 'agi', 0)", [$target->id]);
        }
        $actor = PlayerFactory::legacy($actor->id);
        $target = PlayerFactory::legacy($target->id);
        $actor->getCoords(); $target->getCoords(); $actor->get_caracs(); $target->get_caracs();

        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }
        $results = (new ActionExecutorService($action, $actor, $target))->executeAction();
        $this->assertFalse($results->isSuccess(), 'forty ranks of agility: a miss');

        $this->assertStringContainsString('malus à ' . $target->data->name, (new ActionResultsView($results))->getActionResults());
    }

    /** A miss may break the attacker's weapon too, never the target's armour. */
    public function testAMissMayBreakTheAttackersWeapon(): void
    {
        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        $onMiss = [];
        foreach ($action->getOnSuccessOutcomes(false) as $outcome) {
            foreach ($outcome->getInstructions() as $instruction) {
                if ($instruction instanceof DamageObjectOutcomeInstruction) {
                    $onMiss[] = $instruction->getParameters()['player'] ?? 'BOTH';
                }
            }
        }

        $this->assertSame(['ACTOR'], $onMiss);
    }
}
