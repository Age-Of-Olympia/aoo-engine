<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Items Phase 1a golden masters (docs/design-items-instances.md §5c):
 * the instance lifecycle under lazy promotion —
 *
 *   - promote(): stack −1 + instance + link, atomically; refused on an
 *     empty stack (P3);
 *   - create(): craft birth with creator and custom name (the only
 *     naming moment);
 *   - demote(): the REVERSIBILITY proof — a pristine instance returns
 *     to its stack; any diverged state (wear, name) refuses;
 *   - countOwned(): the future dual-read contract of get_n — stack
 *     units + live instances, destroyed excluded (P2).
 */
#[Group('items-golden-master')]
class ItemInstanceServiceGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private function boisOrSkip(): Item
    {
        $item = Item::get_item_by_name('bois');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'bois' row).");
        }
        $item->get_data();

        try {
            $this->link->executeQuery('SELECT 1 FROM item_instances LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('item_instances table unavailable (run migrations): ' . $e->getMessage());
        }

        return $item;
    }

    protected function tearDown(): void
    {
        // Instances of fixture players: links first (FK), then the
        // now-orphaned instance rows.
        if ($this->link !== null) {
            $ids = $this->link->fetchFirstColumn(
                'SELECT l.instance_id FROM players_items_instances l
                 JOIN players p ON p.id = l.player_id
                 WHERE p.name LIKE "Gm%"'
            );
            if ($ids !== []) {
                $in = implode(',', array_map('intval', $ids));
                $this->link->executeStatement("DELETE FROM players_items_instances WHERE instance_id IN ({$in})");
                $this->link->executeStatement("DELETE FROM item_instances WHERE id IN ({$in})");
            }
        }
        parent::tearDown();
    }

    public function testPromoteMovesOneUnitFromStackToInstance(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 3);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);

        $this->assertSame(2, (int) $this->link->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$player->id, $bois->id]
        ), 'the stack loses exactly one unit');

        $instance = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);
        $this->assertNotFalse($instance);
        $this->assertSame($bois->id, (int) $instance['item_id']);
        $this->assertSame((int) $instance['durability_max'], (int) $instance['durability'], 'born pristine');

        $this->assertSame($player->id, (int) $this->link->fetchOne(
            'SELECT player_id FROM players_items_instances WHERE instance_id = ?',
            [$instanceId]
        ), 'the link carries ownership');

        $this->assertSame(3, $service->countOwned($player->id, $bois->id), 'total owned is unchanged by promotion');
    }

    public function testPromoteRefusesAnEmptyStack(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();

        $this->expectException(\RuntimeException::class);
        (new ItemInstanceService())->promote($player->id, $bois->id);
    }

    public function testCreateBirthsANamedInstanceWithProvenance(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();

        $instanceId = (new ItemInstanceService())->create($player->id, $bois->id, $player->id, 'Dette de Thétis');

        $instance = $this->link->fetchAssociative('SELECT * FROM item_instances WHERE id = ?', [$instanceId]);
        $this->assertSame('Dette de Thétis', $instance['custom_name']);
        $this->assertSame($player->id, (int) $instance['creator_id']);
        $this->assertGreaterThan(0, (int) $instance['created_at']);
    }

    public function testDemoteReturnsAPristineInstanceToTheStackAndRefusesDivergedState(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ?', [$player->id, $bois->id]),
            'the emptied stack row is gone after promoting the last unit'
        );

        $this->assertTrue($service->demote($instanceId), 'a pristine instance must demote');
        $this->assertSame(1, (int) $this->link->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$player->id, $bois->id]
        ), 'the unit is back in the stack');
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM item_instances WHERE id = ?', [$instanceId]),
            'the pristine instance row is removed'
        );

        // Diverged state refuses: wear...
        $worn = $service->promote($player->id, $bois->id);
        $this->link->executeStatement('UPDATE item_instances SET durability = durability - 10 WHERE id = ?', [$worn]);
        $this->assertFalse($service->demote($worn), 'a worn instance must NOT demote');

        // ...and a name set at creation.
        $named = $service->create($player->id, $bois->id, $player->id, 'Labrys');
        $this->assertFalse($service->demote($named), 'a named instance must NOT demote');
    }

    public function testCountOwnedExcludesDestroyedInstances(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 2);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);
        $this->assertSame(2, $service->countOwned($player->id, $bois->id));

        $this->link->executeStatement('UPDATE item_instances SET destroyed = 1 WHERE id = ?', [$instanceId]);
        $this->assertSame(1, $service->countOwned($player->id, $bois->id), 'a destroyed instance no longer counts');
    }
}
