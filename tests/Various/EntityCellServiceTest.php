<?php

namespace Tests\Various;

use App\Service\Map\EntityCellService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Emprise: the cells an entity occupies, and how they are laid.
 *
 * One writer lays them all, from the entity's origin and its type's cut-out.
 * These cases pin that a move carries the whole figure, that a shrunk cut-out
 * releases what it dropped, and that drift is visible and repairable — a badly
 * kept table lies without a sound.
 */
#[Group('items-baseline')]
class EntityCellServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_emprise';

    /** @var list<string> types a case declared a cut-out for */
    private array $declaredTypes = [];

    protected function tearDown(): void
    {
        $link = $this->link;

        foreach ($this->declaredTypes as $type) {
            $link->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [$type]);
        }

        $this->declaredTypes = [];

        /* Cells go with their entities (ON DELETE CASCADE), but the coords
         * cleanup comes after, and that constraint is RESTRICT. */
        $link->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    /** A placed entity holds the cell `players` declares. */
    public function testAPlacedEntityGetsItsAnchor(): void
    {
        $player = $this->createRealPlayer('GmEmprise');
        $id = $this->coordsIdOn(self::PLAN, 0, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);

        (new EntityCellService())->syncCells((int) $player->id);

        $cells = (new EntityCellService())->cellsOf((int) $player->id);
        $this->assertCount(1, $cells);
        $this->assertSame($id, (int) $cells[0]['coords_id']);
        $this->assertSame(EntityCellService::ROLE_PART, $cells[0]['role']);
        $this->assertSame(self::PLAN, $cells[0]['plan'], 'les colonnes chaudes sont recopiées');
    }

    /** The primary key is (player_id, coords_id): inserting alone would leave two. */
    public function testTheAnchorFollowsAndDoesNotAccumulate(): void
    {
        $player = $this->createRealPlayer('GmMarcheur');
        $service = new EntityCellService();

        foreach ([[0, 1], [0, 2], [3, 3]] as [$x, $y]) {
            $id = $this->coordsIdOn(self::PLAN, $x, $y);
            $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);
            $service->syncCells((int) $player->id);

            $cells = $service->cellsOf((int) $player->id);
            $this->assertCount(1, $cells, 'one cell only after a step to ('. $x .','. $y .')');
            $this->assertSame($id, (int) $cells[0]['coords_id']);
            $this->assertSame($x, (int) $cells[0]['x']);
            $this->assertSame($y, (int) $cells[0]['y']);
        }
    }

    /** Calling the sync twice changes nothing: it is idempotent. */
    public function testSyncingTwiceChangesNothing(): void
    {
        $player = $this->createRealPlayer('GmIdem');
        $id = $this->coordsIdOn(self::PLAN, 4, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);

        $service = new EntityCellService();
        $service->syncCells((int) $player->id);
        $service->syncCells((int) $player->id);

        $this->assertCount(1, $service->cellsOf((int) $player->id));
    }

    /** Scenery stacked over a trigger is the normal way the world marks a teleporter. */
    public function testTwoEntitiesMayShareOneTile(): void
    {
        $one = $this->createRealPlayer('GmEmpile1');
        $two = $this->createRealPlayer('GmEmpile2');
        $id = $this->coordsIdOn(self::PLAN, 5, 5);

        foreach ([$one, $two] as $p) {
            $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $p->id]);
            (new EntityCellService())->syncCells((int) $p->id);
        }

        $occupants = array_column((new EntityCellService())->occupantsOf($id), 'player_id');
        $this->assertContains((int) $one->id, array_map('intval', $occupants));
        $this->assertContains((int) $two->id, array_map('intval', $occupants));
    }

    /** A placed entity always has a cell; only a missing entity is reachable here. */
    public function testSyncingAnUnknownEntityRefusesInsteadOfGuessing(): void
    {
        $absent = -999123;

        $this->assertSame(0, (new EntityCellService())->syncCells($absent), 'le refus est explicite');
        $this->assertSame([], (new EntityCellService())->cellsOf($absent));
    }

    public function testDriftIsVisibleAndRepairable(): void
    {
        $player = $this->createRealPlayer('GmDerive');
        $id = $this->coordsIdOn(self::PLAN, 7, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);
        (new EntityCellService())->syncCells((int) $player->id);

        /* A write that forgot to call the service */
        $elsewhere = $this->coordsIdOn(self::PLAN, 8, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$elsewhere, $player->id]);

        $drifted = array_column((new EntityCellService())->drift(), 'player_id');
        $this->assertContains((int) $player->id, array_map('intval', $drifted), 'la dérive est signalée');

        (new EntityCellService())->reconcile();

        $drifted = array_column((new EntityCellService())->drift(), 'player_id');
        $this->assertNotContains((int) $player->id, array_map('intval', $drifted), 'et réparée');
        $this->assertSame($elsewhere, (int) (new EntityCellService())->cellsOf((int) $player->id)[0]['coords_id']);
    }

    /**
     * Declare a cut-out for a type, dropped on teardown.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     */
    private function declareFootprint(string $type, int $w, int $h, array $offsets, array $roles = []): void
    {
        $this->declaredTypes[] = $type;
        (new \App\Service\Map\EntityTypeFootprintService($this->link))
            ->declare($type, $w, $h, $offsets, $roles);
    }

    public function testAFootprintSpreadsTheEntityOverItsCells(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 40, 40, self::PLAN);

        $this->declareFootprint('mur_pierre', 2, 2, [
            0 => [0, 0], 1 => [1, 0], 2 => [0, -1], 3 => [1, -1],
        ]);

        $this->assertSame(4, (new EntityCellService())->syncCells($wall), 'the whole figure, origin included');

        $held = array_map(
            static fn(array $cell): string => $cell['x'] . ',' . $cell['y'],
            (new EntityCellService())->cellsOf($wall)
        );
        sort($held);

        $this->assertSame(['40,39', '40,40', '41,39', '41,40'], $held);
    }

    /** Without a declared role, every cell is plain `part`. */
    public function testTheAnchorKeepsItsRoleWithinAFootprint(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 42, 42, self::PLAN);

        $this->declareFootprint('mur_pierre', 2, 1, [0 => [0, 0], 1 => [1, 0]]);
        (new EntityCellService())->syncCells($wall);

        $roles = array_count_values(array_column((new EntityCellService())->cellsOf($wall), 'role'));

        $this->assertSame(2, $roles[EntityCellService::ROLE_PART] ?? 0, 'both cells, no special one');
    }

    /** `part` is the absence of an opinion: the type decides passability. */
    public function testADeclaredRoleSurvivesWhileTheRestStaysUndecided(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 44, 44, self::PLAN);

        $this->declareFootprint(
            'mur_pierre',
            3,
            1,
            [0 => [0, 0], 1 => [1, 0], 2 => [2, 0]],
            [1 => 'door']
        );

        (new EntityCellService())->syncCells($wall);

        $byCell = [];

        foreach ((new EntityCellService())->cellsOf($wall) as $cell) {
            $byCell[$cell['x'] . ',' . $cell['y']] = $cell['role'];
        }

        $this->assertSame('door', $byCell['45,44'], 'le morceau marqué garde son rôle');
        $this->assertSame(EntityCellService::ROLE_PART, $byCell['46,44']);
    }

    /** Otherwise an emprise could only grow, and a correction would add an error. */
    public function testShrinkingAFootprintReleasesTheCellsItDropped(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 46, 46, self::PLAN);

        $this->declareFootprint('mur_pierre', 3, 1, [0 => [0, 0], 1 => [1, 0], 2 => [2, 0]]);
        (new EntityCellService())->syncCells($wall);
        $this->assertCount(3, (new EntityCellService())->cellsOf($wall));

        $this->declareFootprint('mur_pierre', 2, 1, [0 => [0, 0], 1 => [1, 0]]);
        (new EntityCellService())->syncCells($wall);

        $this->assertCount(2, (new EntityCellService())->cellsOf($wall), 'la case abandonnée est rendue');
    }

    /** A type without a cut-out holds a single cell, as before. */
    public function testATypeWithoutAFootprintKeepsASingleCell(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 48, 48, self::PLAN);

        $this->assertSame(1, (new EntityCellService())->syncCells($wall));
        $this->assertCount(1, (new EntityCellService())->cellsOf($wall));
    }

    /** Correcting a figure takes up the copies already on the map. */
    public function testCorrectingAFootprintTakesUpThePlacedCopies(): void
    {
        $this->requireBuildingsOrSkip();
        $first = $this->placeStructure('mur_pierre', 50, 50, self::PLAN);
        $second = $this->placeStructure('mur_pierre', 55, 55, self::PLAN);

        $this->declareFootprint('mur_pierre', 2, 1, [0 => [0, 0], 1 => [1, 0]]);

        $reapplied = (new EntityCellService())->reapplyForType('mur_pierre');

        $this->assertGreaterThanOrEqual(2, $reapplied, 'les deux exemplaires au moins');
        $this->assertCount(2, (new EntityCellService())->cellsOf($first));
        $this->assertCount(2, (new EntityCellService())->cellsOf($second));
    }

    /**
     * L'emprise s'en va avec l'entité, et la case ne s'en va pas sous elle.
     *
     * Both rules live in the schema, not in code: a deleted entity takes its
     * cells (CASCADE), an occupied cell refuses to vanish (RESTRICT).
     *
     * Asserted on the schema rather than by deleting, which would only
     * exercise MariaDB.
     */
    public function testTheSchemaCarriesTheLifecycleRules(): void
    {
        $rules = [];

        foreach ($this->link->fetchAllAssociative(
            "SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE
               FROM information_schema.REFERENTIAL_CONSTRAINTS rc
              WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = 'entity_cells'"
        ) as $row) {
            $rules[$row['CONSTRAINT_NAME']] = $row['DELETE_RULE'];
        }

        $this->assertSame('CASCADE', $rules['fk_entity_cells_player'] ?? null, 'l\'entité emporte ses cases');
        $this->assertSame('RESTRICT', $rules['fk_entity_cells_coords'] ?? null, 'une case occupée ne disparaît pas');
    }
}
