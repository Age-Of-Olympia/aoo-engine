<?php

namespace Tests\Various;

use App\Service\Map\EntityCellService;
use App\Service\Map\TileOccupancyService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;
use Tests\Support\PlantsResources;

/**
 * The STEP rule, extracted from `go.php` where it lived in three pieces that
 * each refused with an `alert()` and an `exit()`, so none was testable.
 *
 * Includes the point where extraction CORRECTED the original: an entity used
 * to block only on plans that had a JSON file.
 */
#[Group('items-golden-master')]
class TileOccupancyServiceTest extends LegacyPlayerFixtureTestCase
{
    use PlantsResources;

    private const PLAN = 'plan_test_pas';

    protected function tearDown(): void
    {
        $link = $this->link;

        $this->uprootResources($link, self::PLAN);

        $link->executeStatement(
            'DELETE l FROM map_triggers l JOIN coords c ON c.id = l.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        /* Cells written by hand here; the `coords` constraint is RESTRICT, so
         * a still-referenced cell would block the cleanup. */
        $link->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    /** EntityCellService only lays anchors; these cases write the rest by hand. */
    private function giveCell(int $entityId, int $x, int $y, string $role): int
    {
        $coordsId = $this->coordsId($x, $y);

        $this->link->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)",
            [$entityId, $coordsId, self::PLAN, $x, $y, $role]
        );

        return $coordsId;
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    private function service(): TileOccupancyService
    {
        return new TileOccupancyService();
    }

    public function testAnEmptyTileIsWalkable(): void
    {
        $this->assertNull($this->service()->stepRefusal($this->coordsId(0, 0), 1, true));
    }

    /**
     * What LIES on a tile holds nothing: it neither bars the step nor forbids
     * building. Ground loot never did, and it only became askable once it
     * gained a `coords_id` of its own — before that it lived in a table of
     * its own and no occupancy query could see it.
     */
    public function testDroppedLootHoldsNothing(): void
    {
        $entity = $this->createRealPlayer('OccupeJete');
        $coordsId = $this->coordsId(7, 7);

        (new \App\Service\Map\EntityLocationService($this->link))
            ->dropOnCell((int) $entity->id, $coordsId);

        $this->assertNull(
            $this->service()->stepRefusal($coordsId, 1, true),
            'on marche sur ce qui traîne'
        );
        $this->assertNull(
            $this->service()->buildRefusal($coordsId),
            'et on construit par-dessus'
        );
    }

    /** Installed on a tile, the same entity holds it. */
    public function testAnInstalledEntityHoldsItsTile(): void
    {
        $entity = $this->createRealPlayer('OccupePose');
        $coordsId = $this->coordsId(8, 8);

        (new \App\Service\Map\EntityLocationService($this->link))
            ->installOnCell((int) $entity->id, $coordsId);

        $this->assertNotNull(
            $this->service()->buildRefusal($coordsId),
            'une entité posée occupe sa case'
        );
    }

    public function testAResourceBlocksTheStep(): void
    {
        $id = $this->coordsId(1, 0);
        $this->plantResource($this->link, 'arbre1', $id, self::PLAN, 1, 0);

        $this->assertSame('Quelque chose obstrue ton chemin.', $this->service()->stepRefusal($id, 1, true));
    }

    /** An EXHAUSTED resource blocks like any other. Legacy behaviour. */
    public function testAnExhaustedResourceStillBlocks(): void
    {
        $id = $this->coordsId(2, 0);
        $this->plantResource($this->link, 'arbre1', $id, self::PLAN, 2, 0, damages: -2);

        $this->assertNotNull($this->service()->stepRefusal($id, 1, true));
    }

    public function testAForbiddenTriggerBlocksTheStep(): void
    {
        $id = $this->coordsId(3, 0);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('forbidden', ?, '')",
            [$id]
        );

        $this->assertSame('Impossible de se rendre à cet endroit.', $this->service()->stepRefusal($id, 1, true));
    }

    /** Another trigger — a teleporter — blocks nothing. */
    public function testANonForbiddenTriggerDoesNotBlock(): void
    {
        $id = $this->coordsId(4, 0);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('tp', ?, '')",
            [$id]
        );

        $this->assertNull($this->service()->stepRefusal($id, 1, true));
    }

    /** A structure is scenery: it blocks on plans with no JSON too. */
    public function testAStructureBlocksEvenWhenCharactersAreHidden(): void
    {
        $this->requireBuildingsOrSkip();
        $this->placeStructure('mur_pierre', 5, 0, self::PLAN);
        $id = $this->coordsId(5, 0);

        $this->assertNotNull(
            $this->service()->stepRefusal($id, 1, true),
            'un mur bloque, plan visible'
        );
        $this->assertNotNull(
            $this->service()->stepRefusal($id, 1, false),
            'et il bloque AUSSI quand les personnages sont cachés — c\'est le décor'
        );
    }

    /** The other half of "blocking is being seen". */
    public function testACharacterOnlyBlocksWhenVisible(): void
    {
        $other = $this->createRealPlayer('GmObstacle');
        $id = $this->coordsId(6, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $other->id]);

        $this->assertNotNull(
            $this->service()->stepRefusal($id, 1, true),
            'personnage visible : il barre'
        );
        $this->assertNull(
            $this->service()->stepRefusal($id, 1, false),
            'personnages cachés sur ce plan : il ne barre plus'
        );
    }

    /** Hidden mode also removes someone from the way. */
    public function testAnInvisibleCharacterDoesNotBlock(): void
    {
        $other = $this->createRealPlayer('GmDiscret');
        $id = $this->coordsId(7, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $other->id]);
        $this->link->executeStatement(
            "INSERT INTO players_options (player_id, name) VALUES (?, 'invisibleMode')",
            [$other->id]
        );

        $this->assertNull($this->service()->stepRefusal($id, 1, true));
    }

    /** One does not block oneself. */
    public function testTheMoverDoesNotBlockHimself(): void
    {
        $me = $this->createRealPlayer('GmMoi');
        $id = $this->coordsId(8, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $me->id]);

        $this->assertNull($this->service()->stepRefusal($id, (int) $me->id, true));
    }

    /** A teleporter cannot be landed on, can be stepped over, and can be built on — a frozen legacy divergence. */
    public function testTheThreeVerbsAnswerDifferentQuestions(): void
    {
        $id = $this->coordsId(9, 0);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('tp', ?, '')",
            [$id]
        );

        $service = $this->service();

        $this->assertNull($service->stepRefusal($id, 1, true), 'on marche sur un téléporteur');
        $this->assertFalse($service->isVacant($id), 'on n\'y atterrit pas');
        $this->assertNull($service->buildRefusal($id), 'et on peut y bâtir — divergence gelée');
    }

    public function testTheBatchFormAgreesWithTheSingleOne(): void
    {
        $free = $this->coordsId(11, 0);
        $withResource = $this->coordsId(12, 0);
        $forbidden = $this->coordsId(13, 0);

        $this->plantResource($this->link, 'arbre1', $withResource, self::PLAN, 12, 0);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('forbidden', ?, '')",
            [$forbidden]
        );

        $service = $this->service();
        $ids = [$free, $withResource, $forbidden];
        $batch = $service->blockedForStep($ids, 1, true);

        $this->assertSame(
            [$withResource, $forbidden],
            array_values(array_filter($ids, static fn (int $id): bool => isset($batch[$id]))),
            'seules les deux cases occupées ressortent du lot'
        );

        foreach ($ids as $id) {
            $this->assertSame(
                $service->stepRefusal($id, 1, true),
                $batch[$id] ?? null,
                'même verdict, même motif, pour la case '. $id
            );
        }
    }

    /** Un lot vide ne pose aucune question à la base. */
    public function testTheBatchFormAcceptsAnEmptyList(): void
    {
        $this->assertSame([], $this->service()->blockedForStep([], 1, true));
    }

    /** A genuinely empty tile is empty for all three verbs. */
    public function testAnEmptyTileSatisfiesTheThreeVerbs(): void
    {
        $id = $this->coordsId(10, 0);
        $service = $this->service();

        $this->assertNull($service->stepRefusal($id, 1, true));
        $this->assertTrue($service->isVacant($id));
        $this->assertNull($service->buildRefusal($id));
    }

    /** La visibilité de plan se lit comme au rendu : pas de JSON = cachés. */
    /** A 2×2 building used to block a quarter of itself. */
    public function testAnEntityBlocksEveryTileItHolds(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 20, 0, self::PLAN);

        $spread = $this->giveCell($wall, 21, 0, EntityCellService::ROLE_PART);

        $this->assertNotNull(
            $this->service()->stepRefusal($spread, 1, true),
            'la seconde case du mur barre le chemin comme la première'
        );
    }

    /**
     * A cell of a blocking type blocks, whatever its role — nothing can punch
     * a hole through one today, and nothing needs to.
     */
    public function testNoRoleOpensAHoleThroughABlockingType(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 22, 0, self::PLAN);

        $body = $this->giveCell($wall, 23, 0, EntityCellService::ROLE_PART);

        $this->assertNotNull(
            $this->service()->stepRefusal($body, 1, true),
            'the type decides, and this one blocks'
        );
    }

    public function testABlockingCellStopsAPassableType(): void
    {
        $this->requireBuildingsOrSkip();
        $decor = $this->placeStructure('mur_pierre', 24, 0, self::PLAN);

        /* No passable structure is seeded, and this case must not depend on
         * catalogue content or it would be skipped everywhere. Through the
         * domain rather than SQL: Doctrine holds the race in memory, so a raw
         * write would escape it. */
        $entityManager = \App\Entity\EntityManagerFactory::getEntityManager();
        $race = $entityManager->getRepository(\App\Entity\Race::class)->findOneBy(['name' => 'mur_pierre']);

        if ($race === null) {
            $this->markTestSkipped('type mur_pierre absent du catalogue.');
        }

        $race->setBlocksPassage(false);
        $entityManager->flush();

        try {
            $this->assertNull(
                $this->service()->stepRefusal($this->coordsId(24, 0), 1, true),
                'le type se traverse, sans quoi le cas ne prouverait rien'
            );

            $solid = $this->giveCell($decor, 25, 0, 'block');

            $this->assertNotNull(
                $this->service()->stepRefusal($solid, 1, true),
                'la case dite bloquante barre le chemin malgré son type'
            );
        } finally {
            $race->setBlocksPassage(true);
            $entityManager->flush();
        }
    }

    /** One could build inside a building whose front could not be crossed. */
    public function testTheThreeVerbsAgreeOnTheWholeFootprint(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 30, 0, self::PLAN);

        $body = $this->giveCell($wall, 31, 0, EntityCellService::ROLE_PART);
        $service = $this->service();

        $this->assertNotNull($service->stepRefusal($body, 1, true), 'on n\'y entre pas');
        $this->assertFalse($service->isVacant($body), 'on n\'y atterrit pas');
        $this->assertSame(
            'Case occupée par une entité.',
            $service->buildRefusal($body),
            'et on n\'y bâtit pas'
        );
    }

    /** A role overrides the TYPE, never the visibility: blocking is being seen. */
    public function testABlockingCellDoesNotBetrayAHiddenCharacter(): void
    {
        $ghost = $this->createRealPlayer('GmOmbre');
        $cell = $this->giveCell((int) $ghost->id, 28, 0, 'block');

        $this->link->executeStatement(
            "INSERT INTO players_options (player_id, name) VALUES (?, 'invisibleMode')",
            [$ghost->id]
        );

        $this->assertNull(
            $this->service()->stepRefusal($cell, 1, true),
            'discret : sa case bloquante ne le dénonce pas'
        );
    }

    /** Both sources add up, so drift can never make a wall walk-through. */
    public function testADriftedEntityStillBlocksWhereItStands(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 26, 0, self::PLAN);

        $moved = $this->coordsId(27, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$moved, $wall]);

        $this->assertNotNull(
            $this->service()->stepRefusal($moved, 1, true),
            'là où le mur se trouve vraiment'
        );
        $this->assertNotNull(
            $this->service()->stepRefusal($this->coordsId(26, 0), 1, true),
            'et là où ses cases le croient encore : jamais moins que la vérité'
        );
    }

    /**
     * A player does not raise a wall through a statue; decor fills the tile
     * for building. Landing is another matter — one walks on decor, so one
     * lands on it.
     */
    public function testAPlayerCannotBuildOnSceneryButCanLandOnIt(): void
    {
        $this->requireBuildingsOrSkip();
        $decor = $this->placeStructure('mur_pierre', 60, 0, self::PLAN);

        $this->link->executeStatement(
            "UPDATE players SET player_type = 'scenery' WHERE id = ?",
            [$decor]
        );

        $id = $this->coordsId(60, 0);
        $service = $this->service();

        $this->assertTrue($service->isVacant($id), 'decor does not fill a tile for landing');
        $this->assertSame(
            'Case occupée par une entité.',
            $service->buildRefusal($id),
            'but a player does not build through it'
        );
    }

    /**
     * An animator placing from the editor may build over decor — tucking
     * something behind a statue is a legitimate gesture there.
     */
    public function testTheEditorMayBuildOverScenery(): void
    {
        $this->requireBuildingsOrSkip();
        $decor = $this->placeStructure('mur_pierre', 62, 0, self::PLAN);

        $this->link->executeStatement(
            "UPDATE players SET player_type = 'scenery' WHERE id = ?",
            [$decor]
        );

        $this->assertNull(
            $this->service()->buildRefusal($this->coordsId(62, 0), overScenery: true),
            'the editor is allowed through'
        );
    }

    public function testCharacterVisibilityMatchesTheRenderRule(): void
    {
        $this->assertFalse(TileOccupancyService::charactersVisibleOn(null), 'pas de JSON : cachés');
        $this->assertTrue(TileOccupancyService::charactersVisibleOn((object) []), 'JSON sans la clé : visibles');
        $this->assertTrue(
            TileOccupancyService::charactersVisibleOn((object) ['player_visibility' => true])
        );
        $this->assertFalse(
            TileOccupancyService::charactersVisibleOn((object) ['player_visibility' => false])
        );
    }
}
