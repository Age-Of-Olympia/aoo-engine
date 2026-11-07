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
    private int $typeId;

    /** A ground terrain of the test, its plain tile, and a transition between it and an unknown terrain */
    private const MOSS = 'test_growth_mousse';
    private const MOSS_TILE = 'test_growth_mousse_tile';
    private const MOSS_EDGE = 'test_growth_mousse_edge';

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
        $this->typeId = $admin->saveType(0, 'test_growth_prairie', 'Prairie', '#88aa44');
        $this->biomeId = $admin->saveBiome(0, self::PLAN, $this->typeId, 'Prairie', '0,0 5,0 5,1 0,1', 0)['id'];
        Modules::force(Module::Biomes, true);
    }

    protected function tearDown(): void
    {
        Modules::reset();
        $this->purgePlan($this->link, self::PLAN);
        $this->link->executeStatement("DELETE FROM biome_types WHERE name = 'test_growth_prairie'");
        $this->link->executeStatement("DELETE FROM terrain_colors WHERE layer = 'tiles' AND name = ?", [self::MOSS]);
        $this->link->executeStatement("DELETE FROM terrain_tiles WHERE layer = 'tiles' AND name IN (?, ?)", [self::MOSS_TILE, self::MOSS_EDGE]);
        parent::tearDown();
    }

    public function testGrowsOnFreeGroundUpToTheDensity(): void
    {
        // 60 per 100 of the 4 cells with ground: 2 (the bare fifth cell would make it 3)
        $this->population(60);

        $grown = $this->grow();

        $this->assertCount(2, $grown, 'the density stops the third');
        $this->assertSame(2, $this->plantsOnPlan());
        $this->assertNotContains($this->coordsIdOn(self::PLAN, 3, 0), $grown, 'nothing grows under the fire');
    }

    public function testPlantsAlreadyThereCountTowardsTheDensity(): void
    {
        PlantsService::plantAt($this->plant, $this->coordsIdOn(self::PLAN, 0, 0));
        $this->population(60);

        $this->assertCount(1, $this->grow());
        $this->assertSame(2, $this->plantsOnPlan());
    }

    public function testAGroundPaletteKeepsGrowthOnItsTerrains(): void
    {
        $position = (int) $this->link->fetchOne("SELECT COALESCE(MAX(position), 0) + 1 FROM terrain_colors WHERE layer = 'tiles'");
        $this->link->insert('terrain_colors', ['layer' => 'tiles', 'position' => $position, 'name' => self::MOSS]);
        $this->link->insert('terrain_tiles', ['layer' => 'tiles', 'name' => self::MOSS_TILE, 'color' => self::MOSS]);
        // Half moss, half a terrain outside the palette
        $this->link->insert('terrain_tiles', ['layer' => 'tiles', 'name' => self::MOSS_EDGE, 'wang_id' => "0,$position,0,$position,0,999,0,999"]);
        $this->link->update('map_tiles', ['name' => self::MOSS_TILE], ['coords_id' => $this->coordsIdOn(self::PLAN, 0, 0)]);
        $this->link->update('map_tiles', ['name' => self::MOSS_EDGE], ['coords_id' => $this->coordsIdOn(self::PLAN, 1, 0)]);
        (new BiomeAdminService($this->link))->saveGrounds($this->typeId, [self::MOSS => 1]);
        $this->population(100);

        $this->assertSame([$this->coordsIdOn(self::PLAN, 0, 0)], $this->grow(), 'only the moss tile: not the grass, nor the half-moss edge');
    }

    public function testNothingGrowsWhileTheModuleIsOff(): void
    {
        $this->population(100);
        Modules::force(Module::Biomes, false);

        $this->assertSame([], $this->grow());
    }

    public function testGrowTriggersKeepWorkingInsideABiome(): void
    {
        $inside = $this->coordsIdOn(self::PLAN, 1, 0);
        $outside = $this->coordsIdOn(self::PLAN, 9, 0);
        foreach ([$inside, $outside] as $coordsId) {
            $this->link->insert('map_triggers', ['name' => 'grow', 'params' => $this->plant, 'coords_id' => $coordsId]);
        }

        $cells = [];
        $res = PlantsService::getTriggerGrow();
        while ($row = $res->fetch_object()) {
            if (in_array((int) $row->coords_id, [$inside, $outside], true)) {
                $cells[] = (int) $row->coords_id;
            }
        }

        $this->assertEqualsCanonicalizing([$inside, $outside], $cells, 'the biome adds to the triggers, it does not replace them');
    }

    private function population(float $density): void
    {
        (new BiomeAdminService($this->link))->savePopulation($this->biomeId, $this->plantId, 3, $density, 0, 0);
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
