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
            'ne se répare pas',
            $this->refusalOf($results),
            'et c\'est le TYPE qui le dit, pas la visée'
        );
    }

    /**
     * Aucun type de plante ne dépasse 1 PV : une plante entamée est déjà
     * BRISÉE, si bien que l'exécution la refuse à ce titre avant d'interroger
     * son type. La démonstration porte donc sur le bouton — qui est de toute
     * façon ce que le joueur voit.
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

    /**
     * L'enveloppe reste LARGE, et c'est le type qui tranche.
     *
     * La visée a nommé les familles réparables un temps ; une liste gravée dans
     * la donnée d'une action ne peut pas être contredite par un type, alors que
     * la promesse est qu'un type puisse contredire sa famille dans les deux
     * sens. Elle dit donc seulement ce que l'action ATTEINT.
     */
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

    /**
     * Le bouton ne s'affiche pas sur ce qui ne s'entretient pas.
     *
     * La garde est posée en `display_context` : elle disparaît de la carte
     * plutôt que d'y proposer une action qui ne peut qu'échouer.
     */
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
     * en jeu est bien le type. D'où le seuil de PV du type : à 1 PV, entamer
     * c'est déjà briser, et le cas prouverait autre chose que ce qu'il dit.
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

    /** Pose une entité d'une famille dont un type est seedé avec assez de PV. */
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
