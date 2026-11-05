<?php

namespace Tests\Various;

use App\Module\Module;
use App\Module\Modules;
use App\Service\Map\BiomeAdminService;
use App\Service\Map\BiomeGrowthService;
use App\Service\PlantsService;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;
use Tests\Support\PlanFixtureTrait;

class BiomeGrowthServiceTest extends LegacyPlayerFixtureTestCase
{
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_biome_growth';

    private string $plant;
    private int $plantId;
    private int $biomeId;

    protected function setUp(): void
    {
        parent::setUp();
        $plant = $this->link->fetchAssociative("SELECT id, name FROM races WHERE type_kind = 'plant' ORDER BY name LIMIT 1");
        if ($plant === false) {
            $this->markTestSkipped('Aucun type de plante au catalogue.');
        }
        $this->plant = (string) $plant['name'];
        $this->plantId = (int) $plant['id'];

        $this->purgePlan($this->link, self::PLAN);
        $this->link->insert('plans', ['slug' => self::PLAN, 'name' => self::PLAN]);
        // A row of four cells with ground, the last one under a fire
        foreach ([0, 1, 2, 3] as $x) {
            $this->link->insert('map_tiles', ['name' => 'herbe', 'coords_id' => $this->coordsIdOn(self::PLAN, $x, 0)]);
        }
        $this->link->insert('map_elements', ['name' => 'feu', 'coords_id' => $this->coordsIdOn(self::PLAN, 3, 0), 'endTime' => 0]);

        $admin = new BiomeAdminService($this->link);
        $type = $admin->saveType(0, 'test_growth_prairie', 'Prairie', '#88aa44');
        $this->biomeId = $admin->saveBiome(0, self::PLAN, $type, 'Prairie', '0,0 5,0 5,1 0,1', 0)['id'];
        Modules::force(Module::Biomes, true);
    }

    protected function tearDown(): void
    {
        Modules::reset();
        $this->purgePlan($this->link, self::PLAN);
        $this->link->executeStatement("DELETE FROM biome_types WHERE name = 'test_growth_prairie'");
        parent::tearDown();
    }

    public function testGrowsOnFreeGroundUpToTheMaximum(): void
    {
        $this->population(2);

        $grown = $this->grow();

        $this->assertCount(2, $grown, 'the maximum stops the third');
        $this->assertSame(2, $this->plantsOnPlan());
        $this->assertNotContains($this->coordsIdOn(self::PLAN, 3, 0), $grown, 'nothing grows under the fire');
    }

    public function testPlantsAlreadyThereCountTowardsTheMaximum(): void
    {
        PlantsService::plantAt($this->plant, $this->coordsIdOn(self::PLAN, 0, 0));
        $this->population(2);

        $this->assertCount(1, $this->grow());
        $this->assertSame(2, $this->plantsOnPlan());
    }

    public function testNothingGrowsWhileTheModuleIsOff(): void
    {
        $this->population(5);
        Modules::force(Module::Biomes, false);

        $this->assertSame([], $this->grow());
    }

    public function testGrowTriggersInsideABiomeGiveWayToIt(): void
    {
        $this->link->insert('map_triggers', ['name' => 'grow', 'params' => $this->plant, 'coords_id' => $this->coordsIdOn(self::PLAN, 1, 0)]);
        $outside = $this->coordsIdOn(self::PLAN, 9, 0);
        $this->link->insert('map_triggers', ['name' => 'grow', 'params' => $this->plant, 'coords_id' => $outside]);

        $cells = [];
        $res = PlantsService::getTriggerGrow();
        while ($row = $res->fetch_object()) {
            if ($row->coords_id == $outside || $row->coords_id == $this->coordsIdOn(self::PLAN, 1, 0)) {
                $cells[] = (int) $row->coords_id;
            }
        }

        $this->assertSame([$outside], $cells);
    }

    private function population(int $max): void
    {
        (new BiomeAdminService($this->link))->savePopulation($this->biomeId, $this->plantId, 3, $max, 0, 0);
    }

    /** @return list<int> */
    private function grow(): array
    {
        // A die that always rolls 1: every free cell gets its plant
        return (new BiomeGrowthService($this->link, static fn (int $sides): int => 1))->growNight();
    }

    private function plantsOnPlan(): int
    {
        return (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ? AND p.player_type = 'plant'",
            [self::PLAN]
        );
    }
}
