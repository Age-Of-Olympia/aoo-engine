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
            $this->link->executeQuery('SELECT entity_id FROM item_instances LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('item_instances.entity_id unavailable (run migrations): ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        /* Exemplars a failed test left on a tile, dropped OR installed. Placing
         * releases the ownership link, so the fixture teardown — which finds
         * exemplars through their owner — cannot see them: an installed one
         * would sit there forever, holding a tile other cases build on. The
         * exemplar goes first, then its entity; the foreign key is RESTRICT. */
        if ($this->link !== null) {
            $rows = $this->link->fetchAllAssociative(
                'SELECT i.id, i.entity_id FROM players e
                   JOIN item_instances i ON i.entity_id = e.id
                  WHERE e.slot IN (?, ?)
                    AND (i.creator_id IS NULL OR i.creator_id IN (SELECT id FROM players WHERE name LIKE "Gm%"))',
                [
                    \App\Service\Map\EntityLocationService::SLOT_DROPPED,
                    \App\Service\Map\EntityLocationService::SLOT_INSTALLED,
                ]
            );
            if ($rows !== []) {
                $in = implode(',', array_map(static fn ($r) => (int) $r['id'], $rows));
                $entityIn = implode(',', array_map(static fn ($r) => (int) $r['entity_id'], $rows));
                $this->link->executeStatement("DELETE FROM item_instances WHERE id IN ({$in})");
                $this->link->executeStatement("DELETE FROM players WHERE id IN ({$entityIn})");
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

        $ground = $this->link->fetchAssociative(
            'SELECT e.coords_id, e.slot FROM players e
               JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?',
            [$instanceId]
        );
        $this->assertSame($coordsId, (int) $ground['coords_id'], 'the instance is part of the tile bourse');
        $this->assertSame(
            \App\Service\Map\EntityLocationService::SLOT_DROPPED,
            $ground['slot'],
            'it lies on the tile rather than standing on it'
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
        $picked = $this->link->fetchAssociative(
            'SELECT e.coords_id, e.holder_id FROM players e
               JOIN item_instances i ON i.entity_id = e.id WHERE i.id = ?',
            [$instanceId]
        );
        $this->assertNull($picked['coords_id'], 'it no longer lies on any tile');
        $this->assertSame($player->id, (int) $picked['holder_id'], 'its holder is the walker');
    }

    /**
     * Placing an exemplar installs the exemplar itself — no shell, no bridge.
     *
     * The entity SURVIVES the pickup, because it is the object rather than a
     * stand-in for it. That is what lets a placed thing carry anything at all:
     * a shell deleted on pickup could never hold a life, let alone contents.
     */
    public function testPlacingInstallsTheExemplarItselfAndSurvivesThePickup(): void
    {
        [$player, , $instanceId] = $this->playerWithWornGladius();

        ob_start();
        try {
            $entityId = (new UniqueObjectService())->placeInstance(
                $instanceId,
                (object) ['x' => 0, 'y' => 5, 'z' => 0, 'plan' => 'gaia']
            );
        } finally {
            ob_end_clean();
        }
        /* Pas de trackEntityId : cette entité EST l'exemplaire, et le ménage
         * de son porteur l'emporte avec lui. La suivre à part la ferait
         * supprimer avant son exemplaire, que la clé étrangère protège. */

        $placed = $this->link->fetchAssociative(
            'SELECT player_type, race, slot FROM players WHERE id = ?',
            [$entityId]
        );
        $this->assertSame(ItemInstanceService::ENTITY_TYPE, $placed['player_type']);
        $this->assertSame('gladius', $placed['race'], 'son type est celui du catalogue, plus la race fantôme « objet »');
        $this->assertSame(\App\Service\Map\EntityLocationService::SLOT_INSTALLED, $placed['slot']);
        $this->assertSame(
            $entityId,
            (int) $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId]),
            'l\'entité posée EST l\'exemplaire'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM unique_objects WHERE player_id = ?', [$entityId]),
            'plus de pont : il n\'enveloppe rien, il est'
        );

        $taken = (new UniqueObjectService())->takeInstance($entityId, $player->id);
        $this->assertSame($instanceId, $taken);
        $this->assertSame(
            60,
            (int) $this->link->fetchOne('SELECT durability FROM item_instances WHERE id = ?', [$instanceId]),
            'identity survives the entity round trip too'
        );

        $carried = $this->link->fetchAssociative(
            'SELECT coords_id, holder_id FROM players WHERE id = ?',
            [$entityId]
        );
        $this->assertNotFalse($carried, 'ramassé, il existe toujours — ce n\'était pas une doublure');
        $this->assertNull($carried['coords_id'], 'il ne tient plus sa case');
        $this->assertSame($player->id, (int) $carried['holder_id'], 'son porteur est celui qui l\'a pris');
    }
}
