<?php

namespace Tests\Action\Combat;

use App\Action\ActionFactory;
use App\Action\ActionResults;
use App\Action\Condition\ConditionObject;
use App\Action\Condition\TargetTypeCondition;
use App\Entity\ActionCondition;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\BuildingService;
use App\Service\PlayerService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Roadmap step 6 (docs/design-buildings-entities.md §6): combat against a
 * building, end to end.
 *
 *  - the REAL melee action from the catalog resolves against a building
 *    target through the untouched executor — never blocked, 1 A paid,
 *    players_bonus stays the PV source of truth;
 *  - TargetTypeCondition gates by entity branch: ['character'] refuses a
 *    building, ['character','structure'] accepts it;
 *  - the death path branches for buildings: destruction is logged both
 *    ways, build_state flips to 'ruin', the players row STAYS (log FKs,
 *    tile occupancy), and none of the character-death machinery runs
 *    (no XP share, no kill counters, no death()).
 */
#[Group('entities-golden-master')]
#[Group('entities-structure')]
#[Group('action-combat')]
class BuildingCombatGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private const ARCHETYPE = 'palissade';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT 1 FROM buildings LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('buildings table unavailable (run migrations): ' . $e->getMessage());
        }

        $race = (new \App\Service\RaceService())->getRaceByName(self::ARCHETYPE);
        if ($race === null || $race->getPlayable()) {
            $this->markTestSkipped("pseudo-race 'palissade' not seeded (run migrations).");
        }
    }

    private function placePalissadeAt(int $x, int $y): int
    {
        $id = (new BuildingService())->place(
            self::ARCHETYPE,
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia']
        );
        $this->trackEntityId($id);

        return $id;
    }

    public function testMeleeAttackOnBuildingResolvesEndToEnd(): void
    {
        $actor = $this->createRealPlayer('GmSapper');
        $buildingId = $this->placePalissadeAt(0, 1);
        $building = PlayerFactory::legacy($buildingId);

        $actor->getCoords();
        $building->getCoords();
        $actor->get_caracs();
        $building->get_caracs();
        $maxA = (int) $actor->caracs->a;
        $this->snapshotBloodAt((int) $building->data->coords_id);
        $this->snapshotBloodAt((int) $actor->data->coords_id);

        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        $results = (new ActionExecutorService($action, $actor, $building))->executeAction();

        $this->assertInstanceOf(ActionResults::class, $results);
        $this->assertFalse(
            $results->isBlocked(),
            'melee against an adjacent building must pass every stock condition'
        );

        $this->assertSame(
            $maxA - 1,
            PlayerFactory::legacy($actor->id)->getRemaining('a'),
            'attacking a building must cost exactly 1 A'
        );

        $pvRow = $this->link->fetchOne(
            'SELECT n FROM players_bonus WHERE player_id = ? AND name = "pv"',
            [$buildingId]
        );
        $expectedPv = 100 + ($pvRow === false ? 0 : (int) $pvRow);
        $this->assertSame(
            $expectedPv,
            PlayerFactory::legacy($buildingId)->getRemaining('pv'),
            'building PV must equal max + players_bonus ledger after the attack'
        );

        if ($results->isSuccess()) {
            $this->assertLessThan(
                100,
                PlayerFactory::legacy($buildingId)->getRemaining('pv'),
                'a palissade cannot defend (e 0): a successful hit must always damage it'
            );
        }
    }

    public function testTargetTypeConditionGatesByEntityBranch(): void
    {
        $actor = $this->createRealPlayer('GmSapper');
        $buildingId = $this->placePalissadeAt(0, 1);
        $building = PlayerFactory::legacy($buildingId);
        $actor->get_data();
        $building->get_data();

        $condition = new TargetTypeCondition();

        $charactersOnly = (new ActionCondition())
            ->setConditionType('TargetType')
            ->setParameters(['allowed' => ['character']]);
        $result = $condition->check($actor, $building, $charactersOnly, new ConditionObject());
        $this->assertFalse($result->isSuccess(), "['character'] must refuse a building target");

        $structuresToo = (new ActionCondition())
            ->setConditionType('TargetType')
            ->setParameters(['allowed' => ['character', 'structure']]);
        $result = $condition->check($actor, $building, $structuresToo, new ConditionObject());
        $this->assertTrue($result->isSuccess(), "['character','structure'] must accept a building target");

        $result = $condition->check($building, $actor, $charactersOnly, new ConditionObject());
        $this->assertTrue($result->isSuccess(), "['character'] must accept a real-player target");
    }

    public function testDeathPathFlipsTheBuildingToRuinWithoutCharacterDeathMachinery(): void
    {
        $attacker = $this->createRealPlayer('GmSapper');
        $attacker->get_data();
        // Read from the DB, not $attacker->data: the legacy memo caches by id
        // and fixture ids get reused between tests of this very class.
        $xpBefore = (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$attacker->id]);

        $buildingId = $this->placePalissadeAt(0, 1);
        $building = PlayerFactory::legacy($buildingId);
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);
        $building->putBonus(['pv' => -100]);
        $this->assertSame(0, $building->getRemaining('pv'));

        ob_start();
        try {
            PlayerService::ProcessTargetDeath($attacker, $building);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertStringContainsString('Vous détruisez la structure.', (string) $output);

        $this->assertSame(
            'ruin',
            $this->link->fetchOne('SELECT build_state FROM buildings WHERE player_id = ?', [$buildingId]),
            'destruction must flip build_state to ruin'
        );
        $this->assertNotFalse(
            $this->link->fetchOne('SELECT 1 FROM players WHERE id = ?', [$buildingId]),
            'the players row must survive destruction (log FKs, tile occupancy)'
        );
        $this->assertSame(
            2,
            (int) $this->link->fetchOne(
                'SELECT COUNT(*) FROM players_logs WHERE player_id IN (?, ?) AND type = "kill"',
                [$attacker->id, $buildingId]
            ),
            'destruction must be logged on both sides'
        );
        $this->assertSame(
            $xpBefore,
            (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$attacker->id]),
            'no character-death XP share may run for a building'
        );
    }
}
