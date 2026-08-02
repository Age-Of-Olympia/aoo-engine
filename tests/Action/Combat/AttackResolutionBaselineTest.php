<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Action\ActionResults;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Phase 0 golden master for one FULL attack resolution
 * (docs/design-buildings-entities.md §7.3): two real fixture players, the
 * real 'melee' action from the DB catalog, the real executor — conditions,
 * dice, outcomes, costs, XP and logs all live.
 *
 * The dice make success/failure nondeterministic, so this pins what is
 * deterministic about the pipeline instead of exact damage numbers (the
 * damage math itself is already pinned by LifeLossExecuteCharacterization-
 * Test):
 *
 *   - a fresh melee-armed actor adjacent to its target is NEVER blocked
 *     (distance, weapon-type and trait conditions all pass on race base);
 *   - the action costs 1 A whether it hits or misses;
 *   - players_bonus stays the single source of truth for the target's PV:
 *     getRemaining('pv') on a fresh instance == max + (bonus row | 0);
 *   - both per-side log templates resolve to non-empty strings.
 *
 * Buildings become attackable by being the $target of exactly this pipeline,
 * so this contract must survive the Structure/TargetTypeCondition work
 * unchanged.
 */
#[Group('entities-baseline')]
#[Group('action-combat')]
class AttackResolutionBaselineTest extends LegacyPlayerFixtureTestCase
{
    public function testAdjacentMeleeAttackResolvesEndToEnd(): void
    {
        $actor = $this->createRealPlayer('GmAttacker');
        $target = $this->createRealPlayer('GmTarget');
        $this->movePlayerTo($target->id, 0, 1);
        $target = PlayerFactory::legacy($target->id);

        // Production (action.php) resolves both coords before building the
        // executor; PlanCondition reads ->coords directly and expects that.
        $actor->getCoords();
        $target->getCoords();
        $actor->get_caracs();
        $target->get_caracs();
        $maxA = (int) $actor->caracs->a;
        $maxTargetPv = (int) $target->caracs->pv;
        $this->snapshotBloodAt((int) $target->data->coords_id);
        $this->snapshotBloodAt((int) $actor->data->coords_id);

        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        $results = (new ActionExecutorService($action, $actor, $target))->executeAction();

        $this->assertInstanceOf(ActionResults::class, $results);
        $this->assertFalse(
            $results->isBlocked(),
            'a fresh melee-armed actor adjacent to its target must never be blocked'
        );

        // Cost: RequiresTraitValue {a:1} is paid on hit AND miss.
        $freshActor = PlayerFactory::legacy($actor->id);
        $this->assertSame(
            $maxA - 1,
            $freshActor->getRemaining('a'),
            'the melee attack must cost exactly 1 A, success or not'
        );

        // PV source of truth: whatever the dice decided, a fresh reload of the
        // target must agree with the players_bonus ledger.
        $pvRow = $this->link->fetchOne(
            'SELECT n FROM players_bonus WHERE player_id = ? AND name = "pv"',
            [$target->id]
        );
        $expectedPv = $maxTargetPv + ($pvRow === false ? 0 : (int) $pvRow);
        $freshTarget = PlayerFactory::legacy($target->id);
        $this->assertSame(
            $expectedPv,
            $freshTarget->getRemaining('pv'),
            'target PV must equal max + players_bonus ledger after the attack'
        );

        if ($results->isSuccess()) {
            $this->assertLessThanOrEqual(
                $maxTargetPv,
                $freshTarget->getRemaining('pv'),
                'a successful attack must never leave the target above max PV'
            );
        } else {
            $this->assertSame(
                $maxTargetPv,
                $freshTarget->getRemaining('pv'),
                'a missed attack must leave the target untouched'
            );
        }

        // Logs: both per-side templates must have resolved.
        $logs = $results->getLogsArray();
        $this->assertNotSame('', trim((string) ($logs['actor'] ?? '')), 'actor log must resolve');
        $this->assertNotSame('', trim((string) ($logs['target'] ?? '')), 'target log must resolve');
    }
}
