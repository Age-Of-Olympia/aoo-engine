<?php

namespace Tests\Action\Combat;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\ObstacleCondition;
use App\Entity\ActionCondition;
use App\Service\BuildingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La ligne de tir, garde unique.
 *
 * `ObstacleCondition` est déclarée en précondition des SIX types de
 * conditions de calcul — tir à distance, technique, sort, et leurs trois
 * variantes pures. C'est donc elle, et elle seule, qui décide si un
 * projectile passe.
 *
 * Elle n'interrogeait auparavant que `map_resources`, via une seconde copie
 * de Bresenham. Depuis que les murs sont devenus des entités et ont quitté
 * cette table, les techniques et les sorts les TRAVERSAIENT, quand les tirs
 * à distance — qui passaient déjà par `lineOfFireReport` — étaient bien
 * arrêtés. Ces tests épinglent le comportement rétabli et unifié.
 */
#[Group('items-golden-master')]
class ObstacleConditionTest extends LegacyPlayerFixtureTestCase
{
    private function condition(): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setConditionType('Obstacle');
        $condition->setParameters([]);

        return $condition;
    }

    /** Place le tireur en (0,0) et rend sa cible en (4,0). */
    private function shooterAndTarget(): array
    {
        $this->requireBuildingsOrSkip();

        $shooter = $this->createRealPlayer('GmTireur');
        $victim = $this->createRealPlayer('GmCible');

        $this->movePlayerTo($shooter->id, 0, 0);
        $this->movePlayerTo($victim->id, 4, 0);

        $shooter->getCoords();
        $victim->getCoords();

        return [$shooter, $victim];
    }

    public function testAWallStopsTheShot(): void
    {
        [$shooter, $victim] = $this->shooterAndTarget();

        // Plein milieu de la trajectoire, et un type qui arrête les projectiles.
        $this->placeStructure('mur_pierre', 2, 0);

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $this->assertFalse($result->isSuccess(), 'un mur de pierre doit arrêter le tir');
        $messages = $result->getConditionFailureMessages();
        $this->assertStringContainsString('s\'écrase sur', $messages[0]);
        $this->assertStringContainsString('(2, 0)', $messages[0], 'le message situe l\'obstacle');
    }

    /**
     * Le catalogue décide, pas la présence : `races.blocks_projectiles` vaut
     * zéro sur les meubles, et une table laisse donc passer une flèche.
     */
    public function testFurnitureDoesNotStopTheShot(): void
    {
        [$shooter, $victim] = $this->shooterAndTarget();

        $this->placeStructure('table_bois', 2, 0);

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $this->assertTrue($result->isSuccess(), 'une table ne bloque pas les projectiles');
    }

    public function testAClearLineIsNotBlocked(): void
    {
        [$shooter, $victim] = $this->shooterAndTarget();

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->getConditionFailureMessages());
    }

    /**
     * LA règle : le tir passe dans les deux sens ou dans aucun. C'est le
     * rapport lui-même qu'on compare, puisque c'est lui qui décide.
     */
    public function testBlockingIsSymmetric(): void
    {
        [$shooter, $victim] = $this->shooterAndTarget();
        $this->placeStructure('mur_pierre', 2, 0);

        $service = new BuildingService();
        $there = $service->lineOfFireReport($shooter->coords, $victim->coords);
        $back = $service->lineOfFireReport($victim->coords, $shooter->coords);

        $this->assertSame(
            $there['blocker'] === null,
            $back['blocker'] === null,
            'un tir bloqué dans un sens doit l\'être dans l\'autre'
        );
        $this->assertSame($there['blockerName'], $back['blockerName']);
    }

    /** L'échec porte le tracé, rendu par le même code que le clic droit. */
    public function testFailureCarriesTheTrace(): void
    {
        [$shooter, $victim] = $this->shooterAndTarget();
        $this->placeStructure('mur_pierre', 2, 0);

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $message = $result->getConditionFailureMessages()[0];
        $this->assertStringContainsString('showLineOfFire', $message);
        $this->assertStringNotContainsString('alert(', $message, 'plus de modale navigateur');
    }
}
