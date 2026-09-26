<?php

namespace Tests\Various;

use App\Service\Map\GroundLayerService;
use App\Service\Map\TileEraserService;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;
use Tests\Support\PlantsResourcesTrait;

/** The eraser empties a cell of every map layer; the info panel, one layer at a time. */
class TileEraserServiceTest extends LegacyPlayerFixtureTestCase
{
    use PlantsResourcesTrait;

    private const PLAN = 'plan_test_gomme';

    protected function tearDown(): void
    {
        $this->uprootResources($this->link, self::PLAN);
        $this->purgePlan($this->link, self::PLAN);
        parent::tearDown();
    }

    public function testTheEraserEmptiesEveryLayer(): void
    {
        $this->requireBuildingsOrSkip();
        $player = $this->createRealPlayer('Gommeur');

        $ground = $this->coordsIdOn(self::PLAN, 0, 0);
        $this->link->executeStatement("INSERT INTO map_tiles (name, coords_id) VALUES ('herbe', ?)", [$ground]);
        $this->link->executeStatement("INSERT INTO map_triggers (name, coords_id, params) VALUES ('forbidden', ?, '')", [$ground]);
        $this->link->executeStatement('UPDATE coords SET shade = 3 WHERE id = ?', [$ground]);

        (new GroundLayerService())->lay('routes', 'route', $this->tile(1, 0, self::PLAN), (int) $player->id);
        $road = $this->coordsIdOn(self::PLAN, 1, 0);

        $tree = $this->coordsIdOn(self::PLAN, 2, 0);
        $this->plantResource($this->link, 'arbre1', $tree, self::PLAN, 2, 0);

        $this->placeStructure('mur_pierre', 3, 0, self::PLAN);
        $wall = $this->coordsIdOn(self::PLAN, 3, 0);

        $eraser = new TileEraserService();
        foreach ([$ground, $road, $tree, $wall] as $cell) {
            $this->assertSame([], $eraser->eraseAll($cell));
        }

        $left = fn(string $sql): int => (int) $this->link->fetchOne($sql, [$ground, $road, $tree, $wall]);
        $this->assertSame(0, $left('SELECT COUNT(*) FROM map_tiles WHERE coords_id IN (?, ?, ?, ?)'));
        $this->assertSame(0, $left('SELECT COUNT(*) FROM map_triggers WHERE coords_id IN (?, ?, ?, ?)'));
        $this->assertSame(0, $left('SELECT COUNT(*) FROM entity_cells WHERE coords_id IN (?, ?, ?, ?)'));
        $this->assertSame(0, $left('SELECT COALESCE(SUM(shade), 0) FROM coords WHERE id IN (?, ?, ?, ?)'));
    }

    /** A building a player holds stays, and the eraser says why. */
    public function testAHeldBuildingStaysUnlessForced(): void
    {
        $this->requireBuildingsOrSkip();
        $owner = $this->createRealPlayer('Proprio');
        $id = $this->placeStructure('mur_pierre', 5, 0, self::PLAN);
        $this->link->executeStatement('UPDATE players SET owner_id = ? WHERE id = ?', [(int) $owner->id, $id]);
        $cell = $this->coordsIdOn(self::PLAN, 5, 0);
        $held = fn(): int => (int) $this->link->fetchOne('SELECT COUNT(*) FROM entity_cells WHERE coords_id = ?', [$cell]);

        $this->assertCount(1, (new TileEraserService())->eraseAll($cell));
        $this->assertSame(1, $held());

        (new TileEraserService())->eraseLayer($cell, 'buildings', force: true);
        $this->assertSame(0, $held());
    }

    public function testAnUnknownLayerIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TileEraserService())->eraseLayer($this->coordsIdOn(self::PLAN, 7, 0), 'players');
    }
}
