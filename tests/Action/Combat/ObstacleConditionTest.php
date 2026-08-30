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
#[Group('items-baseline')]
class ObstacleConditionTest extends LegacyPlayerFixtureTestCase
{
    private function condition(): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setConditionType('Obstacle');
        $condition->setParameters([]);

        return $condition;
    }

    /**
     * Give an entity one more cell, with the role wanted.
     *
     * @return int the coords id of that cell
     */
    private function giveCellTo(int $entityId, int $x, int $y, string $role): int
    {
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia']
        );

        $this->link->executeStatement(
            'INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)',
            [$entityId, $coordsId, 'gaia', $x, $y, $role]
        );

        return $coordsId;
    }

    /** Place le tireur en ($x, $y) et rend sa cible en ($x+4, $y). */
    private function shooterAndTarget(): array
    {
        $this->requireBuildingsOrSkip();

        [$x, $y] = $this->farTile();

        $shooter = $this->createRealPlayer('GmTireur');
        $victim = $this->createRealPlayer('GmCible');

        $this->movePlayerTo($shooter->id, $x, $y);
        $this->movePlayerTo($victim->id, $x + 4, $y);

        $shooter->getCoords();
        $victim->getCoords();

        return [$shooter, $victim, $x, $y];
    }

    public function testAWallStopsTheShot(): void
    {
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();

        // Plein milieu de la trajectoire, et un type qui arrête les projectiles.
        $this->placeStructure('mur_pierre', $x + 2, $y);

        $condition = $this->condition();
        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $condition, new ConditionObject()
        );

        $this->assertFalse($result->isSuccess(), 'un mur de pierre doit arrêter le tir');
        $messages = $result->getConditionFailureMessages();
        $this->assertStringContainsString('bloque la ligne de tir', $messages[0]);
        $this->assertStringContainsString(
            '(' . ($x + 2) . ', ' . $y . ')',
            $messages[0],
            'le message situe l\'obstacle'
        );
    }

    /**
     * The message says the shot did not happen. It used to read "votre tir
     * s'écrase sur X", which testers took for an attack against the obstacle.
     */
    public function testTheMessageSaysNoShotWasFired(): void
    {
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();

        $this->placeStructure('mur_pierre', $x + 2, $y);

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $this->assertStringContainsString(
            'vous ne tirez pas',
            $result->getConditionFailureMessages()[0]
        );
    }

    /**
     * The condition never touches the parent's flag: what a condition IS does
     * not change during its existence. The refusal is carried by the
     * precondition row, and read by the executor.
     */
    public function testTheParentConditionFlagIsLeftAlone(): void
    {
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();

        $this->placeStructure('mur_pierre', $x + 2, $y);

        $condition = $this->condition();
        (new ObstacleCondition())->check($shooter, $victim, $condition, new ConditionObject());

        $this->assertFalse(
            $condition->isBlocking(),
            'le refus vit sur la ligne de précondition, pas sur la condition'
        );
    }

    /**
     * Le catalogue décide, pas la présence : `races.blocks_projectiles` vaut
     * zéro sur les meubles, et une table laisse donc passer une flèche.
     */
    public function testFurnitureDoesNotStopTheShot(): void
    {
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();

        $this->placeStructure('table_bois', $x + 2, $y);

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $this->assertTrue($result->isSuccess(), 'une table ne bloque pas les projectiles');
    }

    /**
     * An entity screens every cell it HOLDS. A 2×2 wall used to stop arrows
     * on the single cell it stood on.
     */
    public function testAnEntityScreensEveryCellItHolds(): void
    {
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();

        /* The wall stands aside; only its emprise crosses the line. */
        $wall = $this->placeStructure('mur_pierre', $x + 2, $y + 1);
        $this->giveCellTo($wall, $x + 2, $y, \App\Service\Map\EntityCellService::ROLE_PART);

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $this->assertFalse($result->isSuccess(), 'the cell it holds screens the shot');
    }

    /**
     * `cover` is a drawing order: the back of a building must not make
     * whoever stands behind it unreachable.
     */
    public function testACoverCellLetsTheShotThrough(): void
    {
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();

        $wall = $this->placeStructure('mur_pierre', $x + 2, $y + 1);
        $this->giveCellTo($wall, $x + 2, $y, 'cover');

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $this->assertTrue($result->isSuccess(), 'one is not invulnerable behind a sprite');
    }

    /**
     * The arch: its base refuses the step, its opening lets arrows pass.
     * Blocking the way and screening a shot are two dials, on purpose.
     */
    public function testAnArchBlocksTheStepButNotTheShot(): void
    {
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();

        $arch = $this->placeStructure('mur_pierre', $x + 2, $y + 1);
        $cell = $this->giveCellTo($arch, $x + 2, $y, 'block');

        $entityManager = \App\Factory\EntityManagerFactory::getEntityManager();
        $race = $entityManager->getRepository(\App\Entity\Race::class)->findOneBy(['name' => 'mur_pierre']);

        if ($race === null) {
            $this->markTestSkipped('mur_pierre absent du catalogue.');
        }

        $race->setBlocksProjectiles(false);
        $entityManager->flush();

        try {
            $this->assertNotNull(
                (new \App\Service\Map\TileOccupancyService())->stepRefusal($cell, (int) $shooter->id, true),
                'the base refuses the step'
            );

            $result = (new ObstacleCondition())->check(
                $shooter, $victim, $this->condition(), new ConditionObject()
            );

            $this->assertTrue($result->isSuccess(), 'and the arrow goes through the opening');
        } finally {
            $race->setBlocksProjectiles(true);
            $entityManager->flush();
        }
    }

    /**
     * Le trajet de pente exacte 1:2 : les deux tracés n'ont aucune case
     * commune, et l'ancienne règle par case n'y laissait RIEN bloquer. Un mur
     * de trois cases de large se traversait.
     */
    public function testAHalfSlopeShotIsStoppedByAWideWall(): void
    {
        $this->requireBuildingsOrSkip();

        [$x, $y] = $this->farTile();
        $shooter = $this->createRealPlayer('GmPente');
        $victim = $this->createRealPlayer('GmPenteCible');
        $this->movePlayerTo($shooter->id, $x, $y);
        $this->movePlayerTo($victim->id, $x + 2, $y + 4);
        $shooter->getCoords();
        $victim->getCoords();

        /* Trois cases de large en travers du corridor, à mi-chemin. */
        $wall = $this->placeStructure('mur_pierre', $x, $y + 2);
        $this->giveCellTo($wall, $x + 1, $y + 2, \App\Service\Map\EntityCellService::ROLE_PART);
        $this->giveCellTo($wall, $x + 2, $y + 2, \App\Service\Map\EntityCellService::ROLE_PART);

        $report = (new BuildingService())->lineOfFireReport(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia'],
            (object) ['x' => $x + 2, 'y' => $y + 4, 'z' => 0, 'plan' => 'gaia']
        );

        $this->assertNotNull($report['blocker'], 'aucun tracé ne contourne un mur de trois cases');
    }

    /**
     * L'obstacle nommé est le premier VU DEPUIS LE TIREUR, mesuré le long de
     * la droite du tir. Le damier dessine en projetant dessus : s'en écarter
     * faisait courir le trait vert au-delà du premier point d'impact.
     */
    public function testTheNamedBlockerIsTheNearestAlongTheShot(): void
    {
        $this->requireBuildingsOrSkip();

        [$x, $y] = $this->farTile();
        $shooter = $this->createRealPlayer('GmProjection');
        $victim = $this->createRealPlayer('GmProjectionCible');
        $this->movePlayerTo($shooter->id, $x, $y);
        $this->movePlayerTo($victim->id, $x + 2, $y + 4);
        $shooter->getCoords();
        $victim->getCoords();

        $wall = $this->placeStructure('mur_pierre', $x, $y + 2);
        $this->giveCellTo($wall, $x + 1, $y + 2, \App\Service\Map\EntityCellService::ROLE_PART);

        $report = (new BuildingService())->lineOfFireReport(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia'],
            (object) ['x' => $x + 2, 'y' => $y + 4, 'z' => 0, 'plan' => 'gaia']
        );

        $this->assertNotNull($report['blocker']);

        $along = static fn(array $tile): int => $tile[0] * 2 + $tile[1] * 4;
        $named = $along($report['blocker']);

        foreach ($report['blockers'] as $tile) {
            $this->assertGreaterThanOrEqual(
                $named,
                $along($tile),
                'un obstacle se projette plus près que celui qu\'on nomme'
            );
        }
    }

    /**
     * A target does not screen itself. With a single cell the question did
     * not arise — that cell is an endpoint, excluded from the corridor.
     */
    public function testATargetDoesNotScreenItself(): void
    {
        $this->requireBuildingsOrSkip();

        [$x, $y] = $this->farTile();
        $shooter = $this->createRealPlayer('GmVisee');
        $this->movePlayerTo((int) $shooter->id, $x, $y);
        $shooter->getCoords();

        /* Un mur large, dont on vise la case la PLUS LOINTAINE. */
        $wall = $this->placeStructure('mur_pierre', $x, $y + 3);
        $this->giveCellTo($wall, $x, $y + 2, \App\Service\Map\EntityCellService::ROLE_PART);

        $far = (object) ['x' => $x, 'y' => $y + 3, 'z' => 0, 'plan' => 'gaia'];
        $service = new BuildingService();

        $this->assertNotNull(
            $service->lineOfFireReport($shooter->getCoords(), $far)['blocker'],
            'sans connaître la cible, sa propre case proche arrête le tir'
        );

        $this->assertNull(
            $service->lineOfFireReport($shooter->getCoords(), $far, (int) $wall)['blocker'],
            'en la connaissant, elle ne se bloque plus elle-même'
        );
    }

    /** On vise la case la plus proche, comme la portée la mesure. */
    public function testTheShotAimsAtTheNearestCell(): void
    {
        $this->requireBuildingsOrSkip();

        [$x, $y] = $this->farTile();
        $shooter = $this->createRealPlayer('GmViseeProche');
        $this->movePlayerTo((int) $shooter->id, $x, $y);
        $shooter->getCoords();

        $wall = $this->placeStructure('mur_pierre', $x, $y + 5);
        $this->giveCellTo($wall, $x, $y + 1, \App\Service\Map\EntityCellService::ROLE_PART);

        $aim = \Classes\View::get_nearest_cell_of(
            $shooter->getCoords(),
            (int) $wall,
            (object) ['x' => $x, 'y' => $y + 5, 'z' => 0, 'plan' => 'gaia']
        );

        $this->assertSame($y + 1, (int) $aim->y, 'la case collée, pas celle du fond');
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
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();
        $this->placeStructure('mur_pierre', $x + 2, $y);

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
        [$shooter, $victim, $x, $y] = $this->shooterAndTarget();
        $this->placeStructure('mur_pierre', $x + 2, $y);

        $result = (new ObstacleCondition())->check(
            $shooter, $victim, $this->condition(), new ConditionObject()
        );

        $message = $result->getConditionFailureMessages()[0];
        $this->assertStringContainsString('showLineOfFire', $message);
        $this->assertStringNotContainsString('alert(', $message, 'plus de modale navigateur');
    }
}
