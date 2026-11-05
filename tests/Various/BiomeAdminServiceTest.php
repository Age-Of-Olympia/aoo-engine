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
}
