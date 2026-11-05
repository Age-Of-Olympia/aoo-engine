<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Service\Map\BiomeCellService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlanFixtureTrait;

class BiomeCellServiceTest extends TestCase
{
    use PlanFixtureTrait;

    private const PLAN = 'test_biome_cells';
    private const WANG_TILE = 'test_biome_transition';

    private Connection $db;
    private int $planId;
    private int $typeId;

    protected function setUp(): void
    {
        $this->db = EntityManagerFactory::getEntityManager()->getConnection();
        $this->purgePlan($this->db, self::PLAN);
        $this->db->insert('plans', ['slug' => self::PLAN, 'name' => self::PLAN]);
        $this->planId = (int) $this->db->lastInsertId();
        $this->db->insert('biome_types', ['name' => 'test_biome_type', 'label' => 'Test']);
        $this->typeId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        // The plans row takes its biomes and their cells with it
        $this->purgePlan($this->db, self::PLAN);
        $this->db->executeStatement("DELETE FROM biome_types WHERE name = 'test_biome_type'");
        $this->db->executeStatement('DELETE FROM terrain_tiles WHERE name = ?', [self::WANG_TILE]);
    }

    public function testSquareCoversTheCellsWhoseCentreIsInside(): void
    {
        $this->assertSame(
            [[0, 0], [0, 1], [1, 0], [1, 1]],
            BiomeCellService::cellsInside([[0, 0], [2, 0], [2, 2], [0, 2]])
        );
    }

    public function testDiagonalThroughACentreKeepsThatCell(): void
    {
        // Triangle (0,0) (2,0) (0,2): its long edge passes through the centres of (0,1) and (1,0)
        $this->assertSame([[0, 0], [0, 1], [1, 0]], BiomeCellService::cellsInside([[0, 0], [2, 0], [0, 2]]));
    }

    public function testConcaveOutlineLeavesItsNotchOut(): void
    {
        // An L: 3×3 square without its top-right 2×2 corner
        $cells = BiomeCellService::cellsInside([[0, 0], [3, 0], [3, 1], [1, 1], [1, 3], [0, 3]]);

        $this->assertSame([[0, 0], [0, 1], [0, 2], [1, 0], [2, 0]], $cells);
    }

    public function testBrokenOutlineCoversNothing(): void
    {
        $this->assertSame([], BiomeCellService::cellsInside([[0, 0], [1, 1]]));
        $this->assertSame([], BiomeCellService::cellsInside(['oops', [0, 0], [2, 2]]));
    }

    public function testTopZoneWinsWhereZonesOverlap(): void
    {
        $forest = $this->biome('Forêt', [[0, 0], [3, 0], [3, 3], [0, 3]], 0);
        $lake = $this->biome('Lac', [[1, 1], [2, 1], [2, 2], [1, 2]], 1);

        $this->assertSame(9, (new BiomeCellService($this->db))->rebuild($this->planId));
        $this->assertSame([$lake], $this->biomesAt(1, 1));
        $this->assertSame([$forest], $this->biomesAt(0, 0));
    }

    public function testTransitionTileBelongsToBothZones(): void
    {
        $this->db->insert('terrain_tiles', ['layer' => 'tiles', 'name' => self::WANG_TILE, 'wang_id' => '1,0,1,0,2,0,2,0']);
        $this->db->insert('map_tiles', ['name' => self::WANG_TILE, 'coords_id' => $this->coordsIdOn(self::PLAN, 1, 0)]);
        $grass = $this->biome('Prairie', [[0, 0], [2, 0], [2, 1], [0, 1]], 0);
        $sand = $this->biome('Désert', [[1, 0], [3, 0], [3, 1], [1, 1]], 1);

        $this->assertSame(4, (new BiomeCellService($this->db))->rebuild($this->planId));
        $this->assertSame([$grass, $sand], $this->biomesAt(1, 0));
        $this->assertSame([$grass], $this->biomesAt(0, 0));
    }

    public function testRebuildReplacesTheOldCells(): void
    {
        $id = $this->biome('Forêt', [[0, 0], [2, 0], [2, 2], [0, 2]], 0);
        (new BiomeCellService($this->db))->rebuild($this->planId);

        $this->db->update('biomes', ['zone' => json_encode([[5, 5], [6, 5], [6, 6], [5, 6]])], ['id' => $id]);

        $this->assertSame(1, (new BiomeCellService($this->db))->rebuild($this->planId));
        $this->assertSame([], $this->biomesAt(0, 0));
        $this->assertSame([$id], $this->biomesAt(5, 5));
    }

    /** @param list<array{int, int}> $zone */
    private function biome(string $name, array $zone, int $stackOrder): int
    {
        $this->db->insert('biomes', [
            'plan_id' => $this->planId,
            'biome_type_id' => $this->typeId,
            'name' => $name,
            'zone' => json_encode($zone),
            'stack_order' => $stackOrder,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return list<int> */
    private function biomesAt(int $x, int $y): array
    {
        return array_map('intval', $this->db->fetchFirstColumn(
            'SELECT biome_id FROM biome_cells WHERE plan_id = ? AND x = ? AND y = ? ORDER BY biome_id',
            [$this->planId, $x, $y]
        ));
    }
}
