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
 * Ce que « réparer » atteint, famille par famille.
 *
 * L'action visait la BRANCHE `structure`, qui tient aussi les filons et les
 * plantes : un arbre entamé se réparait au marteau. Un décor, lui, se répare —
 * une statue ébréchée se retaille. La visée nomme donc les familles, et ces
 * cas disent lesquelles.
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
            'une ressource',
            $this->refusalOf($results),
            'et le refus nomme la cible, pas la branche entière'
        );
    }

    public function testAPlantIsNotRepaired(): void
    {
        [$actor, $plantId] = $this->anActorFacing('plant', 24);

        $results = (new ActionExecutorService($this->repairer(), $actor, PlayerFactory::legacy($plantId)))
            ->executeAction();

        $this->assertTrue($results->isBlocked(), 'une plante pousse et se cueille, elle ne se répare pas');
        $this->assertStringContainsString('une plante', $this->refusalOf($results));
    }

    /**
     * Le bouton suit la même liste que l'exécution.
     *
     * Les deux lisaient `allowed` chacun de leur côté, la vue à la seule
     * branche : « Réparer » se serait affiché sur l'arbre pour n'y produire
     * qu'un refus.
     */
    public function testTheDisplayedButtonsFollowTheSameList(): void
    {
        $targeting = new ActionTargeting();
        $repair = $this->repairer();

        $this->assertTrue($targeting->canTargetEntity($repair, 'building'));
        $this->assertTrue($targeting->canTargetEntity($repair, 'scenery'));
        $this->assertTrue($targeting->canTargetEntity($repair, 'item'));
        $this->assertFalse($targeting->canTargetEntity($repair, 'resource'));
        $this->assertFalse($targeting->canTargetEntity($repair, 'plant'));
        $this->assertFalse($targeting->canTargetEntity($repair, 'real'));
    }

    /**
     * Le sens INVERSE ne bouge pas : abattre un arbre reste voulu, seule la
     * réparation n'avait rien à faire sur une plante.
     */
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
     * Un acteur en (x, 0), une entité entamée de cette famille en (x, 1).
     *
     * L'entité est blessée d'un seul point : entamée sans être brisée, les
     * deux états que RequiresDamagedTarget refuse par ailleurs — ce qui reste
     * en jeu est bien la famille.
     *
     * @return array{0: \Classes\Player, 1: int}
     */
    private function anActorFacing(string $family, int $x): array
    {
        $entityId = $this->placeEntityOfFamily($family, $x, 1);

        $actor = $this->createRealPlayer('GmRepare' . ucfirst($family));
        $this->movePlayerTo((int) $actor->id, $x, 0);
        $actor->getCoords();
        $actor->get_caracs();

        $entity = PlayerFactory::legacy($entityId);
        $entity->get_caracs();
        $entity->putBonus(['pv' => -1]);

        return [$actor, $entityId];
    }

    /** Pose une entité d'une famille dont un type est seedé AVEC des PV. */
    private function placeEntityOfFamily(string $family, int $x, int $y): int
    {
        $type = $this->link->fetchOne(
            'SELECT name FROM races WHERE type_kind = ? AND pv > 0 ORDER BY name LIMIT 1',
            [$family]
        );

        if ($type === false || $type === null) {
            $this->markTestSkipped("aucun type « {$family} » avec des PV au catalogue.");
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

    /** Les refus d'une exécution, aplatis. */
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
