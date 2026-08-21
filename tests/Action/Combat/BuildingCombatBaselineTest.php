<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Action\ActionResults;
use App\Action\Condition\ConditionObject;
use App\Action\Condition\TargetTypeCondition;
use App\Entity\ActionCondition;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
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
#[Group('entities-baseline')]
#[Group('entities-structure')]
#[Group('action-combat')]
class BuildingCombatBaselineTest extends LegacyPlayerFixtureTestCase
{
    private const TYPE = 'palissade';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBuildingsOrSkip();

        $race = (new \App\Service\RaceService())->getRaceByName(self::TYPE);
        if ($race === null || !$race->isStructureKind()) {
            $this->markTestSkipped("structure type 'palissade' not seeded (run migrations).");
        }
    }

    private function placePalissadeAt(int $x, int $y): int
    {
        return $this->placeStructure('palissade', $x, $y);
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

    public function testHealActionIsBlockedOnABuildingByTheCatalogTargetType(): void
    {
        $actor = $this->createRealPlayer('GmMedic');
        $buildingId = $this->placePalissadeAt(0, 1);
        $building = PlayerFactory::legacy($buildingId);
        $actor->getCoords();
        $building->getCoords();
        $actor->get_caracs();
        $building->get_caracs();

        // A heal declares ['character']: the catalogue soin rotates with the
        // season balancing, so the test sows its own.
        $this->sowCatalogAction('soin_de_test', 'heal', ['TargetType' => ['allowed' => ['character']]]);
        $action = ActionFactory::getAction('soin_de_test');

        $results = (new ActionExecutorService($action, $actor, $building))->executeAction();

        $this->assertTrue(
            $results->isBlocked(),
            'a heal action must be blocked on a structure by its catalog TargetType condition'
        );
        $this->assertFalse(
            $this->link->fetchOne(
                'SELECT n FROM players_bonus WHERE player_id = ? AND name = "pv"',
                [$buildingId]
            ) !== false,
            'a blocked heal must not touch the building PV ledger'
        );
    }

    public function testReparerHealsAWoundedBuildingAndRefusesCharacters(): void
    {
        $actor = $this->createRealPlayer('GmMason');
        $buildingId = $this->placePalissadeAt(0, 1);
        $building = PlayerFactory::legacy($buildingId);
        $actor->getCoords();
        $building->getCoords();
        $actor->get_caracs();
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);

        $action = ActionFactory::getAction('reparer');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'reparer' row).");
        }

        $building->putBonus(['pv' => -30]);

        $results = (new ActionExecutorService($action, $actor, $building))->executeAction();

        $this->assertFalse($results->isBlocked(), 'reparer must accept an adjacent structure target');
        $this->assertTrue($results->isSuccess(), 'reparer has no dice: passing conditions means success');

        // healing {actorHealingTrait: f} — repaired PV = 70 + F, clamped at max.
        $expected = min(100, 70 + (int) $actor->caracs->f);
        $this->assertSame(
            $expected,
            PlayerFactory::legacy($buildingId)->getRemaining('pv'),
            'reparer must heal the structure through the standard healing instruction'
        );

        // The same action on a character must be refused by its TargetType.
        $victim = $this->createRealPlayer('GmPatient');
        $victim->getCoords();
        $victim->get_caracs();
        $onCharacter = (new ActionExecutorService($action, $actor, $victim))->executeAction();
        $this->assertTrue(
            $onCharacter->isBlocked(),
            'reparer must refuse a character target — soigner les personnages est une autre action'
        );
    }

    public function testDeathPathVanishesTheBuildingWithoutCharacterDeathMachinery(): void
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

        // Mort = disparition du plateau (BuildingService::vanish) : plus de
        // satellite, mais la ligne players SURVIT hors-plateau — les logs
        // gardent des FK vraies et l'id n'est jamais recyclé.
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM buildings WHERE player_id = ?', [$buildingId]),
            'destruction must drop the buildings satellite row'
        );
        $shelved = $this->link->fetchAssociative(
            'SELECT coords_id FROM players WHERE id = ?',
            [$buildingId]
        );
        $this->assertNotFalse(
            $shelved,
            'the players row must survive destruction (log FKs, id never recycled)'
        );
        $this->assertNull(
            $shelved['coords_id'],
            'a destroyed building is nowhere — no cell, and no plan invented to hold it'
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
