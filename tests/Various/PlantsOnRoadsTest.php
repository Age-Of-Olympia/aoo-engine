<?php

namespace Tests\Various;

use App\Service\Map\EntityPlacementService;
use App\Service\PlantsService;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A plant on a road goes back to the ground as its seed; off the road it stays.
 */
class PlantsOnRoadsTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_plants_roads';
    private const SEED = 'graine_test_routes';

    protected function tearDown(): void
    {
        $link = $this->link;

        $link->executeStatement(
            'DELETE mi FROM map_items mi JOIN coords c ON c.id = mi.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $link->executeStatement(
            'DELETE p FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $link->executeStatement('DELETE FROM items WHERE name = ?', [self::SEED]);

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    public function testAPlantOnARoadBecomesItsSeedAgain(): void
    {
        $plant = $this->link->fetchOne("SELECT name FROM races WHERE type_kind = 'plant' ORDER BY name LIMIT 1");
        if (!$plant) {
            $this->markTestSkipped('Aucun type de plante au catalogue.');
        }

        $this->link->executeStatement(
            "INSERT INTO items (name, type, stats_in_db, extra) VALUES (?, 'graine', 1, ?)",
            [self::SEED, json_encode(['growTo' => [['name' => $plant, 'table' => 'plants', 'chance' => 1]]])]
        );
        $seedId = (int) $this->link->lastInsertId();

        $place = new EntityPlacementService($this->link);
        $onRoad = $this->coordsIdOn(self::PLAN, 1, 1);
        $offRoad = $this->coordsIdOn(self::PLAN, 2, 1);
        $place->create('route', 'route_test', $onRoad, 'Route', 'img/routes/route_test.png');
        $place->create('plant', $plant, $onRoad, $plant, 'img/plants/' . $plant . '.png');
        $place->create('plant', $plant, $offRoad, $plant, 'img/plants/' . $plant . '.png');

        $report = PlantsService::seedBackFromRoads(false);
        $this->assertSame(1, $report['plants']);
        $this->assertSame(1, $report['seeded']);
        $this->assertSame(2, $this->plantsOnPlan(), 'the report changes nothing');

        PlantsService::seedBackFromRoads(true);

        $this->assertSame(1, $this->plantsOnPlan(), 'the plant off the road stays');
        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT n FROM map_items WHERE coords_id = ? AND item_id = ?', [$onRoad, $seedId])
        );
    }

    private function plantsOnPlan(): int
    {
        return (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = 'plant' AND c.plan = ?",
            [self::PLAN]
        );
    }
}
