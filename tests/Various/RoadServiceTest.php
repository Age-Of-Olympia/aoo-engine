<?php

namespace Tests\Various;

use App\Service\ItemInstanceService;
use App\Service\Map\RoadService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A cell carries a road whether the editor drew it or a player laid it.
 *
 * `courir` only ever asked `map_routes`, which the editor writes. A player
 * crafts a `route` and places it, and the placement installs an exemplar on
 * the cell — so a road built the way players build roads granted nothing.
 */
#[Group('items-baseline')]
class RoadServiceTest extends LegacyPlayerFixtureTestCase
{
    private function freeCell(): int
    {
        [$x, $y] = $this->farTile();

        return (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia']);
    }

    public function testABareCellCarriesNoRoad(): void
    {
        $this->assertFalse((new RoadService())->hasRoadAt($this->freeCell()));
    }

    /** What the map editor draws. */
    public function testTheEditorsLayerIsARoad(): void
    {
        $coordsId = $this->freeCell();
        $this->link->executeStatement(
            'INSERT INTO map_routes (name, coords_id) VALUES (?, ?)',
            ['route', $coordsId]
        );

        try {
            $this->assertTrue((new RoadService())->hasRoadAt($coordsId));
        } finally {
            $this->link->executeStatement('DELETE FROM map_routes WHERE coords_id = ?', [$coordsId]);
        }
    }

    /** What a player crafts, carries and lays down — the case that was lost. */
    public function testAnInstalledRoadItemIsARoad(): void
    {
        $coordsId = $this->freeCell();
        $item = $this->sowCatalogItem('zz_route_' . bin2hex(random_bytes(3)), [
            'type' => 'constructible',
            'subtype' => RoadService::SUBTYPE,
            'stats_in_db' => 1,
        ]);

        $entityId = (new ItemInstanceService())->installFromCatalogAt((int) $item->id, $coordsId);
        $this->trackEntityId($entityId);

        $this->assertTrue((new RoadService())->hasRoadAt($coordsId));
    }

    /** A crate laid on the ground is not a road. */
    public function testAnotherInstalledObjectIsNotARoad(): void
    {
        $coordsId = $this->freeCell();
        $item = $this->sowCatalogItem('zz_caisse_' . bin2hex(random_bytes(3)), [
            'type' => 'constructible',
            'subtype' => 'walls',
            'stats_in_db' => 1,
        ]);

        $entityId = (new ItemInstanceService())->installFromCatalogAt((int) $item->id, $coordsId);
        $this->trackEntityId($entityId);

        $this->assertFalse((new RoadService())->hasRoadAt($coordsId));
    }
}
