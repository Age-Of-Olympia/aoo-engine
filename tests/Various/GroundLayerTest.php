<?php

namespace Tests\Various;

use App\Service\Map\GroundLayerService;
use App\Service\MapService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A road is ground, not an object standing on it.
 *
 * Everything that knows about roads reads `map_routes`: the running bonus
 * (`courir` → TileTypeOutcomeInstruction → MapService), the drawn map,
 * `observe`, and the rule keeping plants off roads. A road installed as an
 * object is invisible to every one of them — which is what happened when the
 * placement action moved to `placestructure`.
 */
#[Group('items-baseline')]
class GroundLayerTest extends LegacyPlayerFixtureTestCase
{
    /** @var list<int> */
    private array $laidAt = [];

    protected function tearDown(): void
    {
        foreach ($this->laidAt as $coordsId) {
            $this->link->executeStatement('DELETE FROM map_routes WHERE coords_id = ?', [$coordsId]);
        }

        parent::tearDown();
    }

    private function cell(int $dx = 0): object
    {
        [$x, $y] = $this->farTile();

        return (object) ['x' => $x + $dx, 'y' => $y, 'z' => 0, 'plan' => 'gaia'];
    }

    public function testARoadSubtypeNamesALayer(): void
    {
        $this->assertTrue(GroundLayerService::isLayer('routes'));
        $this->assertFalse(GroundLayerService::isLayer('walls'));
        $this->assertFalse(GroundLayerService::isLayer(''));
    }

    /** Laying one writes the layer the running bonus reads. */
    public function testLayingARoadIsSeenByTheRunningBonus(): void
    {
        $coords = $this->cell();
        $coordsId = (int) View::get_coords_id($coords);
        $player = $this->createRealPlayer('GmPaveur');

        $this->assertSame(0, (int) (new MapService())->getTileTypeAtCoord('routes', $coordsId)->n);

        $laid = (new GroundLayerService())->lay('routes', 'route', $coords, (int) $player->id);
        $this->laidAt[] = $coordsId;

        $this->assertTrue($laid['ok'], $laid['message']);
        $this->assertSame(1, (int) (new MapService())->getTileTypeAtCoord('routes', $coordsId)->n);
    }

    /** The layer records who laid it — a road stays its builder's. */
    public function testTheLayerKeepsItsBuilder(): void
    {
        $coords = $this->cell(1);
        $player = $this->createRealPlayer('GmProprio');

        (new GroundLayerService())->lay('routes', 'route', $coords, (int) $player->id);
        $this->laidAt[] = (int) View::get_coords_id($coords);

        $this->assertSame(
            (int) $player->id,
            (int) $this->link->fetchOne(
                'SELECT player_id FROM map_routes WHERE coords_id = ?',
                [(int) View::get_coords_id($coords)]
            )
        );
    }

    /** One road per cell. */
    public function testASecondRoadOnTheSameCellIsRefused(): void
    {
        $coords = $this->cell(2);
        $player = $this->createRealPlayer('GmDouble');
        $service = new GroundLayerService();

        $service->lay('routes', 'route', $coords, (int) $player->id);
        $this->laidAt[] = (int) View::get_coords_id($coords);

        $again = $service->lay('routes', 'route', $coords, (int) $player->id);

        $this->assertFalse($again['ok']);
        $this->assertSame(
            1,
            (int) $this->link->fetchOne(
                'SELECT COUNT(*) FROM map_routes WHERE coords_id = ?',
                [(int) View::get_coords_id($coords)]
            )
        );
    }
}
