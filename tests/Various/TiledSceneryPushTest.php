<?php

namespace Tests\Various;

use App\Service\Map\EntityTypeFootprintService;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

/**
 * Pushing a multi-piece object from Tiled.
 *
 * The plugin used to explode a composite tile itself, so the object died at
 * the door and the server only saw loose pieces. It now sends the object and
 * the server lays it out — while a plugin that still explodes keeps working,
 * since animators update at their own pace.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class TiledSceneryPushTest extends TestCase
{
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_tiled_scenery';
    private const FAMILY = 'gm_push_tour';

    private ?Connection $conn = null;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->cleanup();

        $this->coordsIdOn(self::PLAN, 0, 0);

        /* A two-piece tower, declared so the server knows how to lay it out. */
        (new EntityTypeFootprintService($this->conn))->declare(
            self::FAMILY,
            1,
            2,
            [0 => [0, 0], 1 => [0, -1]]
        );
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->purgePlan($this->conn, self::PLAN);
        $this->conn?->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [self::FAMILY]);
        $this->conn?->executeStatement('DELETE FROM races WHERE name = ?', [self::FAMILY]);
    }

    /** @return list<string> the piece names lying on the plan */
    private function piecesOnPlan(): array
    {
        $names = $this->conn->fetchFirstColumn(
            'SELECT f.name FROM map_foregrounds f JOIN coords c ON c.id = f.coords_id
              WHERE c.plan = ? ORDER BY f.name',
            [self::PLAN]
        );

        return array_map('strval', $names);
    }

    /** One row for the object; the server lays out the figure. */
    public function testACompositeRowBecomesItsPieces(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $service->importPlan(self::PLAN, 0, [
            'foregrounds' => [
                ['x' => 4, 'y' => 4, 'name' => self::FAMILY . '-00', 'composite' => true],
            ],
        ], $export['version']);

        $this->assertSame(
            [self::FAMILY . '-00', self::FAMILY . '-01'],
            $this->piecesOnPlan(),
            'the whole figure, from a single row'
        );
    }

    /** And it becomes ONE entity holding both cells, not two loose pieces. */
    public function testAPushedObjectBecomesAnEntity(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $service->importPlan(self::PLAN, 0, [
            'foregrounds' => [
                ['x' => 6, 'y' => 6, 'name' => self::FAMILY . '-00', 'composite' => true],
            ],
        ], $export['version']);

        $cells = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
               JOIN coords c ON c.id = ec.coords_id
              WHERE c.plan = ? AND p.player_type = 'scenery'",
            [self::PLAN]
        );

        $this->assertSame(2, $cells, 'one entity, two cells');
    }

    /**
     * A plugin that still explodes keeps working: rows without the flag pass
     * through untouched. The two shapes have to coexist while animators
     * update at their own pace.
     */
    public function testAnExplodedPushStillWorks(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $service->importPlan(self::PLAN, 0, [
            'foregrounds' => [
                ['x' => 8, 'y' => 8, 'name' => self::FAMILY . '-00'],
                ['x' => 8, 'y' => 9, 'name' => self::FAMILY . '-01'],
            ],
        ], $export['version']);

        $this->assertSame(
            [self::FAMILY . '-00', self::FAMILY . '-01'],
            $this->piecesOnPlan(),
            'the pieces land exactly where the plugin put them'
        );
    }

    /**
     * Erasing every piece of a figure in Tiled must vanish its entity too —
     * otherwise the game keeps drawing it from entity_cells while the editor
     * shows nothing left to erase.
     */
    public function testErasingAllPiecesVanishesTheEntity(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $push = $service->importPlan(self::PLAN, 0, [
            'foregrounds' => [
                ['x' => 10, 'y' => 10, 'name' => self::FAMILY . '-00', 'composite' => true],
            ],
        ], $export['version']);

        $entityId = (int) $this->conn->fetchOne(
            "SELECT p.id FROM players p
               JOIN entity_cells ec ON ec.player_id = p.id
               JOIN coords c ON c.id = ec.coords_id
              WHERE c.plan = ? AND p.player_type = 'scenery'",
            [self::PLAN]
        );
        $this->assertGreaterThan(0, $entityId, 'the object became an entity');

        $export = $service->exportPlan(self::PLAN, 0);
        $push = $service->importPlan(self::PLAN, 0, ['foregrounds' => []], $export['version']);

        $this->assertSame(1, $push['layers']['foregrounds']['vanished'], 'the orphaned entity is vanished');
        $this->assertSame([], $this->piecesOnPlan(), 'no pieces left');

        $stillScenery = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
              WHERE p.id = ? AND p.player_type = 'scenery'",
            [$entityId]
        );
        $this->assertSame(0, $stillScenery, 'no cell left holding the vanished entity');
    }

    /**
     * A truncated figure — one piece erased, one still standing — is not an
     * orphan: it stays, waiting to be repaired, not silently deleted.
     */
    public function testPartiallyErasingAFigureKeepsTheEntity(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $service->importPlan(self::PLAN, 0, [
            'foregrounds' => [
                ['x' => 12, 'y' => 12, 'name' => self::FAMILY . '-00', 'composite' => true],
            ],
        ], $export['version']);

        // Push again with only the anchor piece: the second piece is erased.
        $export = $service->exportPlan(self::PLAN, 0);
        $push = $service->importPlan(self::PLAN, 0, [
            'foregrounds' => [
                ['x' => 12, 'y' => 12, 'name' => self::FAMILY . '-00'],
            ],
        ], $export['version']);

        $this->assertSame(0, $push['layers']['foregrounds']['vanished'], 'a truncated figure is not an orphan');

        $cells = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
               JOIN coords c ON c.id = ec.coords_id
              WHERE c.plan = ? AND p.player_type = 'scenery'",
            [self::PLAN]
        );
        $this->assertSame(2, $cells, 'the entity keeps both its cells');
    }

    /** A family with no known cut-out is never guessed at. */
    public function testAnUnknownFamilyIsLaidDownAsIs(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $service->importPlan(self::PLAN, 0, [
            'foregrounds' => [
                ['x' => 2, 'y' => 2, 'name' => 'gm_push_inconnu', 'composite' => true],
            ],
        ], $export['version']);

        $this->assertSame(['gm_push_inconnu'], $this->piecesOnPlan());
    }
}
