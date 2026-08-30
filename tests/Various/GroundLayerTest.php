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
            $this->link->executeStatement(
                "DELETE ec FROM entity_cells ec
                   JOIN players p ON p.id = ec.player_id
                  WHERE p.player_type = 'route' AND ec.coords_id = ?",
                [$coordsId]
            );
            $this->link->executeStatement(
                "DELETE FROM players WHERE player_type = 'route' AND coords_id = ?",
                [$coordsId]
            );
        }

        parent::tearDown();
    }

    /** The road entity standing on that cell, 0 when there is none. */
    private function roadOn(int $coordsId): int
    {
        return (int) $this->link->fetchOne(
            "SELECT COALESCE(MAX(p.id), 0)
               FROM players p
               JOIN entity_cells ec ON ec.player_id = p.id
              WHERE p.player_type = 'route' AND ec.coords_id = ?",
            [$coordsId]
        );
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

    /** A road stays its builder's — that is what lets it decay when abandoned. */
    public function testTheRoadKeepsItsBuilder(): void
    {
        $coords = $this->cell(1);
        $coordsId = (int) View::get_coords_id($coords);
        $player = $this->createRealPlayer('GmProprio');

        (new GroundLayerService())->lay('routes', 'route', $coords, (int) $player->id);
        $this->laidAt[] = $coordsId;

        $road = $this->roadOn($coordsId);
        $this->assertGreaterThan(0, $road, 'laying one mints an entity');
        $this->assertSame(
            (int) $player->id,
            (int) $this->link->fetchOne('SELECT owner_id FROM players WHERE id = ?', [$road])
        );
    }

    /**
     * A road a PLAYER lays decays; one the map editor lays does not.
     *
     * The editor uses the same gesture — its palette is unchanged — so the
     * difference cannot come from the code path. It comes from the caller
     * saying which it is.
     */
    public function testOnlyAPlayerLaidRoadDecays(): void
    {
        $service = new GroundLayerService();
        $player = $this->createRealPlayer('GmVoyer');

        $byEditor = $this->cell(3);
        $service->lay('routes', 'route', $byEditor, (int) $player->id);
        $this->laidAt[] = (int) View::get_coords_id($byEditor);

        $byPlayer = $this->cell(4);
        $service->lay('routes', 'route', $byPlayer, (int) $player->id, byPlayer: true);
        $this->laidAt[] = (int) View::get_coords_id($byPlayer);

        $this->assertSame(
            0,
            $this->enrolled($this->roadOn((int) View::get_coords_id($byEditor))),
            'what the editor lays is not perishable'
        );
        $this->assertSame(
            1,
            $this->enrolled($this->roadOn((int) View::get_coords_id($byPlayer))),
            'what a player lays is'
        );
    }

    private function enrolled(int $entityId): int
    {
        return (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM entity_decay WHERE player_id = ?',
            [$entityId]
        );
    }

    /** It is walked on, not stood in: its type blocks nothing. */
    public function testARoadBlocksNothing(): void
    {
        $blocks = $this->link->fetchAssociative(
            'SELECT blocks_passage, blocks_projectiles FROM races WHERE name = ?',
            ['route']
        );

        if ($blocks === false) {
            $this->markTestSkipped('road type not seeded (run migrations).');
        }

        $this->assertSame(0, (int) $blocks['blocks_passage']);
        $this->assertSame(0, (int) $blocks['blocks_projectiles']);
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
                "SELECT COUNT(*)
                   FROM players p
                   JOIN entity_cells ec ON ec.player_id = p.id
                  WHERE p.player_type = 'route' AND ec.coords_id = ?",
                [(int) View::get_coords_id($coords)]
            )
        );
    }
}
