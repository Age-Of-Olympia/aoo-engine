<?php

namespace Tests\Various;

use App\Service\Map\EntityTypeFootprintService;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

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
    private const PLAN = 'plan_test_tiled_scenery';
    private const FAMILY = 'gm_push_tour';

    private ?Connection $conn = null;

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->cleanup();

        \Classes\View::get_coords_id((object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]);

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
        if ($this->conn === null) {
            return;
        }

        foreach ($this->conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            \App\Service\BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        $this->conn->executeStatement(
            'DELETE m FROM map_foregrounds m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [self::FAMILY]);
        $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [self::FAMILY]);

        $this->conn->executeStatement('DELETE FROM plans WHERE slug = ?', [self::PLAN]);
        \App\Service\PlanService::forget(self::PLAN);
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
