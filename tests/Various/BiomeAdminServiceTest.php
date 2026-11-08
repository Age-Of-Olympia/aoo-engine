<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Service\Map\BiomeAdminService;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlanFixtureTrait;

class BiomeAdminServiceTest extends TestCase
{
    use PlanFixtureTrait;

    private const PLAN = 'test_biome_admin';
    private const OTHER_PLAN = 'test_biome_admin_2';

    private Connection $db;
    private BiomeAdminService $service;

    protected function setUp(): void
    {
        $this->db = EntityManagerFactory::getEntityManager()->getConnection();
        $this->tearDown();
        foreach ([self::PLAN, self::OTHER_PLAN] as $plan) {
            $this->db->insert('plans', ['slug' => $plan, 'name' => $plan]);
        }
        $this->service = new BiomeAdminService($this->db);
    }

    protected function tearDown(): void
    {
        $this->purgePlan($this->db, self::PLAN);
        $this->purgePlan($this->db, self::OTHER_PLAN);
        $this->db->executeStatement("DELETE FROM biome_types WHERE name LIKE 'test_admin_%'");
        $this->db->executeStatement("DELETE FROM terrain_colors WHERE layer = 'tiles' AND name IN ('test_admin_mousse', 'test_admin_tourbe')");
    }

    public function testParseZoneReadsCornersWhateverTheSeparator(): void
    {
        $this->assertSame([[0, 0], [10, 0], [10, -8]], BiomeAdminService::parseZone(" 0,0  10,0;\n10,-8 "));
    }

    public function testParseZoneRefusesGarbageAndTooFewCorners(): void
    {
        foreach (['0,0 10 10,8', '0,0 1,1'] as $text) {
            try {
                BiomeAdminService::parseZone($text);
                $this->fail('accepted: ' . $text);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSaveComputesTheCellsAndDeleteBringsBackTheBiomeBelow(): void
    {
        $type = $this->service->saveType(0, 'test_admin_foret', 'Forêt', '#336633');
        $forest = $this->service->saveBiome(0, self::PLAN, $type, 'Forêt', '0,0 4,0 4,4 0,4', 0);
        $glade = $this->service->saveBiome(0, self::PLAN, $type, 'Clairière', '1,1 3,1 3,3 1,3', 1);

        $this->assertSame(['id' => $forest['id'], 'cells' => 12], $this->service->saveBiome(
            $forest['id'], self::PLAN, $type, 'Forêt', '0,0 4,0 4,4 0,4', 0
        ), 'the glade on top takes 4 of the 16 cells');
        $this->assertSame(4, $glade['cells']);

        $this->service->deleteBiome($glade['id']);

        $this->assertSame(16, (int) $this->db->fetchOne('SELECT COUNT(*) FROM biome_cells WHERE biome_id = ?', [$forest['id']]));
    }

    public function testTheExtensionPushReplacesThePlanZonesInItsOrder(): void
    {
        $this->service->saveType(0, 'test_admin_foret', 'Forêt', '#336633');
        $type = $this->service->saveType(0, 'test_admin_lac', 'Lac', '#3366cc');
        $lake = $this->service->saveBiome(0, self::PLAN, $type, 'Lac', '0,0 2,0 2,2 0,2', 0)['id'];
        $this->service->saveBiome(0, self::PLAN, $type, 'Mare', '5,5 6,5 6,6 5,6', 0);
        $pulled = $this->service->zonesOf(self::PLAN);
        $this->assertSame(['Lac', 'Mare'], array_column($pulled['zones'], 'name'));

        // A forest drawn under the lake, the pond erased
        $report = $this->service->pushZones(self::PLAN, [
            ['type' => 'test_admin_foret', 'name' => 'Bois', 'zone' => [[0, 0], [4, 0], [4, 4], [0, 4]]],
            $pulled['zones'][0],
        ], $pulled['version']);

        $this->assertSame(['created' => 1, 'updated' => 1, 'deleted' => ['Mare']], array_diff_key($report, ['version' => true, 'ids' => true]));
        $this->assertSame($lake, $report['ids'][1], 'ids in the order pushed');
        $this->assertSame(4, (int) $this->db->fetchOne('SELECT COUNT(*) FROM biome_cells WHERE biome_id = ?', [$lake]), 'the lake stays on top');
        $this->assertSame($report['version'], $this->service->zonesOf(self::PLAN)['version']);

        $this->expectExceptionCode(409);
        $this->service->pushZones(self::PLAN, [], $pulled['version']);
    }

    public function testAPushWithAnUnknownTypeWritesNothing(): void
    {
        $type = $this->service->saveType(0, 'test_admin_lac', 'Lac', '#3366cc');
        $this->service->saveBiome(0, self::PLAN, $type, 'Lac', '0,0 2,0 2,2 0,2', 0);
        $pulled = $this->service->zonesOf(self::PLAN);

        try {
            $this->service->pushZones(self::PLAN, [
                ['type' => 'test_admin_lac', 'name' => 'Étang', 'zone' => [[5, 5], [6, 5], [6, 6]]],
                ['type' => 'test_admin_volcan', 'name' => 'Cratère', 'zone' => [[9, 9], [12, 9], [12, 12]]],
            ], $pulled['version']);
            $this->fail('unknown type accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cratère', $e->getMessage());
        }

        $this->assertSame($pulled, $this->service->zonesOf(self::PLAN));
    }

    public function testMovingABiomeToAnotherPlanClearsItsOldPlan(): void
    {
        $type = $this->service->saveType(0, 'test_admin_lac', 'Lac', '#3366cc');
        $lake = $this->service->saveBiome(0, self::PLAN, $type, 'Lac', '0,0 2,0 2,2 0,2', 0);

        $this->service->saveBiome($lake['id'], self::OTHER_PLAN, $type, 'Lac', '0,0 2,0 2,2 0,2', 0);

        $this->assertSame([self::OTHER_PLAN], $this->db->fetchFirstColumn(
            'SELECT DISTINCT p.slug FROM biome_cells c JOIN plans p ON p.id = c.plan_id WHERE c.biome_id = ?',
            [$lake['id']]
        ));
    }

    public function testAUsedTypeCanBeRenamedButNotToATakenName(): void
    {
        $type = $this->service->saveType(0, 'test_admin_bois', 'Bois', '#336633');
        $this->service->saveType(0, 'test_admin_marais', 'Marais', '#556644');
        $wood = $this->service->saveBiome(0, self::PLAN, $type, 'Bois', '0,0 2,0 2,2 0,2', 0);

        $this->assertSame($type, $this->service->saveType($type, 'test_admin_foret_claire', 'Bois', '#336633'));
        $this->assertSame($type, (int) $this->db->fetchOne('SELECT biome_type_id FROM biomes WHERE id = ?', [$wood['id']]));

        $this->expectException(InvalidArgumentException::class);
        $this->service->saveType($type, 'test_admin_marais', 'Bois', '#336633');
    }

    public function testAPaletteAddsUpToAHundredPercentOfKnownTerrains(): void
    {
        foreach (['test_admin_mousse', 'test_admin_tourbe'] as $terrain) {
            $position = (int) $this->db->fetchOne("SELECT COALESCE(MAX(position), 0) + 1 FROM terrain_colors WHERE layer = 'tiles'");
            $this->db->insert('terrain_colors', ['layer' => 'tiles', 'position' => $position, 'name' => $terrain]);
        }
        $type = $this->service->saveType(0, 'test_admin_tourbiere', 'Tourbière', '#556644');

        $this->service->saveGrounds($type, ['test_admin_mousse' => 60, 'test_admin_tourbe' => 40]);
        $saved = array_column($this->service->types(), 'grounds', 'id');
        $this->assertSame(['test_admin_mousse' => 60, 'test_admin_tourbe' => 40], $saved[$type]);

        foreach ([['test_admin_mousse' => 60, 'test_admin_tourbe' => 30], ['test_admin_inconnu' => 100]] as $refused) {
            try {
                $this->service->saveGrounds($type, $refused);
                $this->fail('accepted: ' . json_encode($refused));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $saved = array_column($this->service->types(), 'grounds', 'id');
        $this->assertSame(['test_admin_mousse' => 60, 'test_admin_tourbe' => 40], $saved[$type], 'a refused palette changes nothing');
    }

    public function testAZoneCoveringNothingAndAUsedTypeAreRefused(): void
    {
        $type = $this->service->saveType(0, 'test_admin_desert', 'Désert', '#ccaa66');

        try {
            $this->service->saveBiome(0, self::PLAN, $type, 'Ligne', '0,0 5,0 10,0', 0);
            $this->fail('a flat zone was accepted');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->service->saveBiome(0, self::PLAN, $type, 'Désert', '0,0 1,0 1,1 0,1', 0);
        $this->expectException(InvalidArgumentException::class);
        $this->service->deleteType($type);
    }

    public function testASelfCrossingOutlineIsRefusedButAClearingIsNot(): void
    {
        $type = $this->service->saveType(0, 'test_admin_foret', 'Forêt', '#336633');

        try {
            $this->service->saveBiome(0, self::PLAN, $type, 'Nœud', '2,0 3,1 3,0 2,1', 0);
            $this->fail('a bowtie outline was accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('2,0 → 3,1 coupe le côté 3,0 → 2,1', $e->getMessage());
        }

        // In along y = 4, around the clearing, back out the same way: sides that run along each other
        $saved = $this->service->saveBiome(0, self::PLAN, $type, 'Forêt', '0,0 10,0 10,10 0,10 0,4 4,4 4,6 6,6 6,4 4,4 0,4', 0);
        $this->assertSame(96, $saved['cells'], '100 cells less the 2 × 2 clearing');
    }
}
