<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use App\Service\UniqueObjectService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Items Phase 3 golden masters — the ground bridge, revised after
 * review (2026-07-17): an instance ON THE GROUND is part of the tile's
 * BOURSE like any loot — dropped via dropAt(), collected by WALKING on
 * the tile (collectAt(), wired in go.php). No dedicated action.
 * Identity — wear, name, provenance — survives the round trip.
 *
 * The ENTITY wrapper (UniqueObjectService, unique_objects.
 * item_instance_id) remains available for future animator-placed
 * attackable artifacts/chests; its invariants stay pinned here.
 */
#[Group('items-golden-master')]
#[Group('entities-structure')]
class UniqueObjectBridgeGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT instance_id FROM map_items_instances LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('map_items_instances unavailable (run migrations): ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Ground instances left by a failed test: unlink then orphan rows.
        if ($this->link !== null) {
            $ids = $this->link->fetchFirstColumn(
                'SELECT g.instance_id FROM map_items_instances g
                 JOIN item_instances i ON i.id = g.instance_id
                 WHERE i.creator_id IS NULL OR i.creator_id IN (SELECT id FROM players WHERE name LIKE "Gm%")'
            );
            if ($ids !== []) {
                $in = implode(',', array_map('intval', $ids));
                $this->link->executeStatement("DELETE FROM map_items_instances WHERE instance_id IN ({$in})");
                $this->link->executeStatement("DELETE FROM item_instances WHERE id IN ({$in})");
            }
        }
        parent::tearDown();
    }

    /** @return array{0:\Classes\Player,1:Item,2:int} player, gladius, worn unequipped instance id */
    private function playerWithWornGladius(): array
    {
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        $item->get_data();

        $player = $this->createRealPlayer('GmLooter');
        $item->add_item($player, 1);
        $player->get_caracs();
        $player->equip($item);

        $instanceId = (int) $this->link->fetchOne(
            'SELECT l.instance_id FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id WHERE l.player_id = ? AND i.item_id = ?',
            [$player->id, $item->id]
        );
        $this->link->executeStatement('UPDATE item_instances SET durability = 60 WHERE id = ?', [$instanceId]);
        $player->equip($item); // unequip — worn, stays an instance

        return [$player, $item, $instanceId];
    }

    public function testDropAtRefusesAnEquippedInstance(): void
    {
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        $item->get_data();
        $player = $this->createRealPlayer('GmLooter');
        $item->add_item($player, 1);
        $player->get_caracs();
        $player->equip($item);
        $instanceId = (int) $this->link->fetchOne(
            'SELECT instance_id FROM players_items_instances WHERE player_id = ?', [$player->id]
        );
        $coordsId = (int) $this->link->fetchOne("SELECT id FROM coords WHERE x = 0 AND y = 0 AND z = 0 AND plan = 'gaia'");

        $this->expectException(\InvalidArgumentException::class);
        (new ItemInstanceService())->dropAt($instanceId, $coordsId);
    }

    public function testWornInstanceRoundTripsThroughTheGroundWithItsIdentity(): void
    {
        [$player, , $instanceId] = $this->playerWithWornGladius();
        $coordsId = (int) $this->link->fetchOne("SELECT id FROM coords WHERE x = 0 AND y = 0 AND z = 0 AND plan = 'gaia'");

        $service = new ItemInstanceService();
        $service->dropAt($instanceId, $coordsId);

        $this->assertSame(
            $coordsId,
            (int) $this->link->fetchOne('SELECT coords_id FROM map_items_instances WHERE instance_id = ?', [$instanceId]),
            'the instance is part of the tile bourse'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM players_items_instances WHERE instance_id = ?', [$instanceId]),
            'the owner link is released — the ground IS the location'
        );

        // Walking on the tile collects it (the go.php path calls collectAt).
        $labels = $service->collectAt($coordsId, $player->id);

        $this->assertSame(['Gladius'], $labels, 'the loot recap names the instance');
        $this->assertSame(
            $player->id,
            (int) $this->link->fetchOne('SELECT player_id FROM players_items_instances WHERE instance_id = ?', [$instanceId]),
            'the instance is back in the walker inventory'
        );
        $this->assertSame(
            60,
            (int) $this->link->fetchOne('SELECT durability FROM item_instances WHERE id = ?', [$instanceId]),
            'identity — the wear — survived the round trip'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM map_items_instances WHERE instance_id = ?', [$instanceId]),
            'the ground row is gone'
        );
    }

    public function testEntityWrapperStillWorksForAnimatorArtifacts(): void
    {
        // The UniqueObject ENTITY path stays available (attackable artifact):
        // place, verify wrap + release, take back — service level.
        [$player, , $instanceId] = $this->playerWithWornGladius();

        ob_start();
        try {
            $uniqueId = (new UniqueObjectService())->placeInstance(
                $instanceId,
                (object) ['x' => 0, 'y' => 5, 'z' => 0, 'plan' => 'gaia']
            );
        } finally {
            ob_end_clean();
        }
        $this->trackEntityId($uniqueId);

        $this->assertSame('unique', $this->link->fetchOne('SELECT player_type FROM players WHERE id = ?', [$uniqueId]));
        $this->assertSame(
            $instanceId,
            (int) $this->link->fetchOne('SELECT item_instance_id FROM unique_objects WHERE player_id = ?', [$uniqueId])
        );

        $taken = (new UniqueObjectService())->takeInstance($uniqueId, $player->id);
        $this->assertSame($instanceId, $taken);
        $this->assertSame(
            60,
            (int) $this->link->fetchOne('SELECT durability FROM item_instances WHERE id = ?', [$instanceId]),
            'identity survives the entity round trip too'
        );
        $this->assertFalse($this->link->fetchOne('SELECT 1 FROM players WHERE id = ?', [$uniqueId]));
    }
}
