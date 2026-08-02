<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\Action\ActionTargeting;
use App\Service\ActionExecutorService;
use App\Service\Map\EntityPlacementService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * What `reparer` reaches, family by family: scenery mends, what grows does not.
 */
#[Group('entities-baseline')]
class RepairTargetsBaselineTest extends LegacyPlayerFixtureTestCase
{
    public function testASceneryIsRepaired(): void
    {
        [$actor, $sceneryId] = $this->anActorFacing('scenery', 20);

        $results = (new ActionExecutorService($this->repairer(), $actor, PlayerFactory::legacy($sceneryId)))
            ->executeAction();

        $this->assertFalse(
            $results->isBlocked(),
            'un décor entamé se répare : ce qui a été taillé se retaille'
        );
    }

    public function testAResourceIsNotRepaired(): void
    {
        [$actor, $resourceId] = $this->anActorFacing('resource', 22);

        $results = (new ActionExecutorService($this->repairer(), $actor, PlayerFactory::legacy($resourceId)))
            ->executeAction();

        $this->assertTrue($results->isBlocked(), 'un filon ne se répare pas');
        $this->assertStringContainsString(
            'ne se répare pas',
            $this->refusalOf($results),
            'et c\'est le TYPE qui le dit, pas la visée'
        );
    }

    /**
     * No plant type exceeds 1 PV, so a damaged plant is already broken and gets
     * refused as such before its type is consulted. Assert on the button, which
     * is what a player sees anyway.
     */
    public function testAPlantIsNotRepaired(): void
    {
        [$actor, $plantId] = $this->anActorFacing('plant', 24, minPv: 1);
        $plant = PlayerFactory::legacy($plantId);
        $plant->get_data();

        $this->assertFalse(
            (new ActionTargeting())->matchesDisplayContext($this->repairer(), $actor, $plant),
            'pas de bouton Réparer sur une fleur'
        );

        $this->assertTrue(
            (new ActionExecutorService($this->repairer(), $actor, $plant))->executeAction()->isBlocked(),
            'et l\'exécution la refuse aussi'
        );
    }

    /** The envelope only says what the action REACHES; the type decides the rest. */
    public function testTheEnvelopeStaysWideAndTheTypeDecides(): void
    {
        $targeting = new ActionTargeting();
        $repair = $this->repairer();

        foreach (['building', 'scenery', 'item', 'resource', 'plant'] as $family) {
            $this->assertTrue(
                $targeting->canTargetEntity($repair, $family),
                "la visée atteint {$family} : cocher « réparable » sur un tel type doit pouvoir servir"
            );
        }

        $this->assertFalse(
            $targeting->canTargetEntity($repair, 'real'),
            'un personnage se soigne, il ne se répare pas'
        );
    }

    /** The guard is `display_context`: the button leaves the card entirely. */
    public function testTheButtonHidesOnWhatDoesNotMend(): void
    {
        $targeting = new ActionTargeting();

        [$actor, $resourceId] = $this->anActorFacing('resource', 26);
        $resource = PlayerFactory::legacy($resourceId);
        $resource->get_data();

        $this->assertFalse(
            $targeting->matchesDisplayContext($this->repairer(), $actor, $resource),
            'pas de bouton Réparer sur un filon'
        );

        [$actor2, $sceneryId] = $this->anActorFacing('scenery', 28);
        $scenery = PlayerFactory::legacy($sceneryId);
        $scenery->get_data();

        $this->assertTrue(
            $targeting->matchesDisplayContext($this->repairer(), $actor2, $scenery),
            'mais bien sur une statue ébréchée'
        );
    }

    /** Attacks keep the whole branch: felling a tree is intended. */
    public function testAttackingStillReachesTheWholeBranch(): void
    {
        $attack = ActionFactory::getAction('melee');

        if ($attack === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        $targeting = new ActionTargeting();

        $this->assertTrue($targeting->canTargetEntity($attack, 'resource'), 'on abat un arbre');
        $this->assertTrue($targeting->canTargetEntity($attack, 'plant'));
        $this->assertTrue($targeting->canTargetEntity($attack, 'real'));
    }

    private function repairer(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('reparer');

        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'reparer' row).");
        }

        return $action;
    }

    /**
     * An actor at (x, 0) facing a damaged entity of that family at (x, 1).
     *
     * One point of damage only: damaged but not broken, the two states
     * RequiresDamagedTarget refuses on its own. Hence $minPv — at 1 PV,
     * damaging is breaking and the case would prove something else.
     *
     * @return array{0: \Classes\Player, 1: int}
     */
    private function anActorFacing(string $family, int $x, int $minPv = 2): array
    {
        $entityId = $this->placeEntityOfFamily($family, $x, 1, $minPv);

        $actor = $this->createRealPlayer('GmRepare' . ucfirst($family));
        $this->movePlayerTo((int) $actor->id, $x, 0);
        $actor->getCoords();
        $actor->get_caracs();

        $entity = PlayerFactory::legacy($entityId);
        $entity->get_caracs();
        $entity->putBonus(['pv' => -1]);

        return [$actor, $entityId];
    }

    /** Place an entity of that family, from a seeded type with enough PV. */
    private function placeEntityOfFamily(string $family, int $x, int $y, int $minPv = 2): int
    {
        $type = $this->link->fetchOne(
            'SELECT name FROM races WHERE type_kind = ? AND pv >= ? ORDER BY name LIMIT 1',
            [$family, $minPv]
        );

        if ($type === false || $type === null) {
            $this->markTestSkipped("aucun type « {$family} » d'au moins {$minPv} PV au catalogue.");
        }

        $coordsId = (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia']
        );

        $id = (new EntityPlacementService($this->link))->create(
            $family,
            (string) $type,
            $coordsId,
            ucfirst((string) $type),
            ''
        );
        $this->trackEntityId($id);

        return $id;
    }

    /** Every refusal of one execution, flattened. */
    private function refusalOf(\App\Action\ActionResults $results): string
    {
        $messages = [];

        foreach ($results->getConditionsResultsArray() as $conditionResult) {
            foreach ($conditionResult->getConditionFailureMessages() ?? [] as $message) {
                $messages[] = (string) $message;
            }
        }

        return implode(' ', $messages);
    }
}
