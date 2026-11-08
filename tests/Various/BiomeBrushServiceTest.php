<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Service\Map\BiomeAdminService;
use App\Service\Map\BiomeBrushService;
use App\Service\Map\GroundNoise;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlanFixtureTrait;

class BiomeBrushServiceTest extends TestCase
{
    use PlanFixtureTrait;

    private const PLAN = 'test_biome_brush';
    private const GRASS = 'test_brush_herbe';
    private const MOSS = 'test_brush_mousse';
    private const AREA = ['xMin' => 0, 'xMax' => 19, 'yMin' => 0, 'yMax' => 19];

    private Connection $db;
    private int $biomeId;
    private int $typeId;

    /** @var array<string, list<string>> tile name => its terrains at TL, TR, BR, BL */
    private array $cornersOf = [];

    protected function setUp(): void
    {
        $this->db = EntityManagerFactory::getEntityManager()->getConnection();
        $this->tearDown();
        $this->db->insert('plans', ['slug' => self::PLAN, 'name' => self::PLAN]);

        // Two terrains, their plain tiles and every transition between them (14 corner combinations)
        $positions = [];
        foreach ([self::GRASS, self::MOSS] as $terrain) {
            $positions[$terrain] = (int) $this->db->fetchOne("SELECT COALESCE(MAX(position), 0) + 1 FROM terrain_colors WHERE layer = 'tiles'");
            $this->db->insert('terrain_colors', ['layer' => 'tiles', 'position' => $positions[$terrain], 'name' => $terrain]);
            $this->db->insert('terrain_tiles', ['layer' => 'tiles', 'name' => $terrain, 'color' => $terrain]);
            $this->cornersOf[$terrain] = array_fill(0, 4, $terrain);
        }
        foreach (range(1, 14) as $combination) {
            $four = array_map(static fn (int $bit): string => ($combination >> $bit) & 1 ? self::MOSS : self::GRASS, [3, 2, 1, 0]);
            $wangId = array_fill(0, 8, 0);
            foreach ([7, 1, 3, 5] as $corner => $slot) {
                $wangId[$slot] = $positions[$four[$corner]];
            }
            $name = 'test_brush_trans_' . $combination;
            $this->db->insert('terrain_tiles', ['layer' => 'tiles', 'name' => $name, 'wang_id' => implode(',', $wangId)]);
            $this->cornersOf[$name] = $four;
        }

        $admin = new BiomeAdminService($this->db);
        $this->typeId = $admin->saveType(0, 'test_brush_prairie', 'Prairie', '#88aa44', 4);
        $admin->saveGrounds($this->typeId, [self::GRASS => 70, self::MOSS => 30]);
        $this->biomeId = $admin->saveBiome(0, self::PLAN, $this->typeId, 'Prairie', '0,0 20,0 20,20 0,20', 0, 1234)['id'];
    }

    protected function tearDown(): void
    {
        $this->purgePlan($this->db, self::PLAN);
        $this->db->executeStatement("DELETE FROM biome_types WHERE name = 'test_brush_prairie'");
        $this->db->executeStatement("DELETE FROM terrain_colors WHERE layer = 'tiles' AND name LIKE 'test_brush_%'");
        $this->db->executeStatement("DELETE FROM terrain_tiles WHERE layer = 'tiles' AND name LIKE 'test_brush_%'");
    }

    public function testTheNoiseDependsOnlyOnThePointAndTheSeed(): void
    {
        $noise = new GroundNoise(7);

        $this->assertSame($noise->at(3.25, -8.5), (new GroundNoise(7))->at(3.25, -8.5));
        $this->assertNotSame($noise->at(3.25, -8.5), (new GroundNoise(8))->at(3.25, -8.5));
    }

    public function testNeighboursAlwaysShareTheirCorners(): void
    {
        $painted = (new BiomeBrushService($this->db))->paint($this->biomeId, self::AREA);

        $this->assertSame([], $painted['missing']);
        $this->assertCount(400, $painted['tiles']);
        foreach ($painted['tiles'] as $key => $tile) {
            [$x, $y] = array_map('intval', explode(',', $key));
            [$topLeft, $topRight, $bottomRight] = $this->cornersOf[$tile];
            if (isset($painted['tiles'][($x + 1) . ',' . $y])) {
                $right = $this->cornersOf[$painted['tiles'][($x + 1) . ',' . $y]];
                $this->assertSame([$topRight, $bottomRight], [$right[0], $right[3]], "right of $key");
            }
            if (isset($painted['tiles'][$x . ',' . ($y + 1)])) {
                $above = $this->cornersOf[$painted['tiles'][$x . ',' . ($y + 1)]];
                $this->assertSame([$topLeft, $topRight], [$above[3], $above[2]], "above $key");
            }
        }
    }

    public function testTheWeightsSetTheShares(): void
    {
        $plain = array_count_values(array_filter(
            (new BiomeBrushService($this->db))->paint($this->biomeId, self::AREA)['tiles'],
            fn (string $tile): bool => in_array($tile, [self::GRASS, self::MOSS], true)
        ));

        $this->assertGreaterThan(2 * ($plain[self::MOSS] ?? 0), $plain[self::GRASS] ?? 0, '70 grass to 30 moss');
        $this->assertArrayHasKey(self::MOSS, $plain, 'the lighter terrain still shows');
    }

    public function testStrokesDrawWhatOneFillDraws(): void
    {
        $brush = new BiomeBrushService($this->db);
        $left = $brush->paint($this->biomeId, ['xMax' => 9] + self::AREA)['tiles'];
        $right = $brush->paint($this->biomeId, ['xMin' => 10] + self::AREA)['tiles'];

        $this->assertEquals($brush->paint($this->biomeId, self::AREA)['tiles'], $left + $right);
    }

    public function testAMissingTransitionIsReported(): void
    {
        $used = array_diff((new BiomeBrushService($this->db))->paint($this->biomeId, self::AREA)['tiles'], [self::GRASS, self::MOSS]);
        $gone = reset($used);
        $this->db->delete('terrain_tiles', ['layer' => 'tiles', 'name' => $gone]);

        $painted = (new BiomeBrushService($this->db))->paint($this->biomeId, self::AREA);

        $this->assertContains(implode(' / ', $this->cornersOf[$gone]), $painted['missing']);
        $this->assertNotContains($gone, $painted['tiles']);
    }

    public function testOneClickPaintsTheWholeBiomeButNotTheOneOnTop(): void
    {
        $clicked = $this->coordsIdOn(self::PLAN, 3, 4);
        $this->db->insert('map_tiles', ['name' => 'test_brush_old', 'coords_id' => $clicked, 'foreground' => 0]);
        $admin = new BiomeAdminService($this->db);
        $admin->saveBiome(0, self::PLAN, $this->typeId, 'Clairière', '10,10 12,10 12,12 10,12', 1);
        $brush = new BiomeBrushService($this->db);

        $painted = $brush->paintBiome($clicked);
        $brush->paintBiome($clicked);

        $this->assertSame('Prairie', $painted['biome']);
        $this->assertCount(400 - 4, $painted['coords'], 'every cell of the biome, except the 4 of the glade on top');
        $expected = $brush->paint($this->biomeId, self::AREA)['tiles'];
        $grounds = $this->db->fetchAllAssociative(
            "SELECT c.x, c.y, t.name FROM map_tiles t JOIN coords c ON c.id = t.coords_id WHERE c.plan = ? AND t.foreground = 0",
            [self::PLAN]
        );
        $this->assertCount(400 - 4, $grounds, 'one ground row per cell, even painted twice');
        foreach ($grounds as $ground) {
            $this->assertSame($expected[$ground['x'] . ',' . $ground['y']], $ground['name']);
        }
        $this->assertNull($brush->paintBiome($this->coordsIdOn(self::PLAN, 40, 40))['biome'], 'no biome, nothing painted');
    }

    public function testTheExtensionPaintsABiomeByItsIdOnTheLevelItNames(): void
    {
        $brush = new BiomeBrushService($this->db);

        $painted = $brush->paintBiomeOnLevel($this->biomeId, -1);

        $this->assertSame('Prairie', $painted['biome']);
        $this->assertCount(400, $painted['coords']);
        $this->assertSame([-1], array_map('intval', $this->db->fetchFirstColumn(
            'SELECT DISTINCT c.z FROM map_tiles t JOIN coords c ON c.id = t.coords_id WHERE c.plan = ?',
            [self::PLAN]
        )));
        $this->assertNull($brush->paintBiomeOnLevel(0, 0)['biome'], 'no such biome, nothing painted');
    }

    public function testTheEdgeJoinsTheGroundAlreadyLaidNextToIt(): void
    {
        (new BiomeAdminService($this->db))->saveGrounds($this->typeId, [self::GRASS => 100]);
        // Moss east of the biome, and a tile of no terrain west of it
        foreach (range(0, 19) as $y) {
            $this->db->insert('map_tiles', ['name' => self::MOSS, 'coords_id' => $this->coordsIdOn(self::PLAN, 20, $y), 'foreground' => 0]);
        }
        $this->db->insert('map_tiles', ['name' => 'test_brush_inconnu', 'coords_id' => $this->coordsIdOn(self::PLAN, -1, 5), 'foreground' => 0]);
        $brush = new BiomeBrushService($this->db);

        $painted = $brush->paintBiome($this->coordsIdOn(self::PLAN, 3, 4));

        $this->assertSame([], $painted['missing']);
        $this->assertSame(['test_brush_inconnu'], $painted['unreadable']);
        foreach (range(0, 19) as $y) {
            $tile = (string) $this->db->fetchOne(
                'SELECT name FROM map_tiles WHERE coords_id = ? AND foreground = 0',
                [$this->coordsIdOn(self::PLAN, 19, $y)]
            );
            [$topLeft, $topRight, $bottomRight, $bottomLeft] = $this->cornersOf[$tile];
            $this->assertSame([self::GRASS, self::MOSS, self::MOSS, self::GRASS], [$topLeft, $topRight, $bottomRight, $bottomLeft], "edge cell 19,$y");
        }
        $inner = (string) $this->db->fetchOne('SELECT name FROM map_tiles WHERE coords_id = ? AND foreground = 0', [$this->coordsIdOn(self::PLAN, 10, 10)]);
        $this->assertSame(self::GRASS, $inner, 'inside, the palette alone');
    }

    public function testANewSeedDrawsOtherPatches(): void
    {
        $brush = new BiomeBrushService($this->db);
        $before = $brush->paint($this->biomeId, self::AREA)['tiles'];

        $brush->reseed($this->biomeId);

        $this->assertNotEquals($before, $brush->paint($this->biomeId, self::AREA)['tiles']);
    }

    public function testATypeWithoutGroundsCannotPaint(): void
    {
        (new BiomeAdminService($this->db))->saveGrounds($this->typeId, []);

        $this->expectException(InvalidArgumentException::class);
        (new BiomeBrushService($this->db))->paint($this->biomeId, self::AREA);
    }
}
