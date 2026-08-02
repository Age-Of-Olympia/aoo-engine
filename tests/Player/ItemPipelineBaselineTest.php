<?php

namespace Tests\Player;

use App\Enum\EquipResult;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Phase 0 golden masters for the ITEM pipeline
 * (docs/design-items-instances.md §5): pins the persisted contract of
 * stack arithmetic, equip/unequip and the equipment effect on caracs,
 * BEFORE the instances migration starts touching players_items.
 *
 * What is pinned on purpose:
 *   - add_item() stack math: accumulate, decrement, refuse overdraw,
 *     delete the row at zero — the invariants the instances migration
 *     must preserve for stackable kinds;
 *   - equip(): flips players_items.equiped to the emplacement, shows up
 *     in get_equiped_list(), and applyItemCaracs adds the weapon's
 *     caracs on top of the race base; unequip restores everything;
 *   - give_item(): transfer moves the stack between two players.
 *
 * Uses the real 'gladius' catalog row (main1, cc +1). Skips cleanly
 * when the catalog isn't seeded.
 */
#[Group('items-baseline')]
class ItemPipelineBaselineTest extends LegacyPlayerFixtureTestCase
{
    private function gladiusOrSkip(): Item
    {
        $item = $this->itemOrSkip('gladius');
        if (($item->data->emplacement ?? '') !== 'main1') {
            $this->markTestSkipped('gladius is not a main1 weapon in this catalog.');
        }

        return $item;
    }

    public function testStackArithmeticAccumulatesDecrementsAndRefusesOverdraw(): void
    {
        $player = $this->createRealPlayer('GmStock');
        $item = $this->gladiusOrSkip();

        $this->assertSame(0, $item->get_n($player), 'fresh player owns nothing');

        $this->assertTrue($item->add_item($player, 5));
        $this->assertSame(5, $item->get_n($player), 'stacks accumulate');

        $this->assertTrue($item->add_item($player, -2));
        $this->assertSame(3, $item->get_n($player), 'stacks decrement');

        $this->assertFalse($item->add_item($player, -10), 'overdraw must be refused');
        $this->assertSame(3, $item->get_n($player), 'a refused overdraw must not change the stack');

        $this->assertTrue($item->add_item($player, -3));
        $this->assertSame(0, $item->get_n($player));
        $this->assertFalse(
            $this->link->fetchOne(
                'SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ?',
                [$player->id, $item->id]
            ),
            'an emptied stack must delete its row, not keep n = 0'
        );
    }

    public function testEquipFlipsTheEmplacementAndAddsTheWeaponCaracs(): void
    {
        $player = $this->createRealPlayer('GmSquire');
        $item = $this->gladiusOrSkip();
        $item->add_item($player, 1);

        $player->get_caracs();
        $nudeCc = (int) $player->nude->cc;

        $this->assertSame(EquipResult::Equip, $player->equip($item), 'first use equips');

        // Phase 1c: equipping PROMOTES — the emplacement now lives on the
        // instance link, and the promoted unit has left the stack.
        $this->assertSame(
            'main1',
            $this->link->fetchOne(
                'SELECT l.equiped FROM players_items_instances l
                 JOIN item_instances i ON i.id = l.instance_id
                 WHERE l.player_id = ? AND i.item_id = ?',
                [$player->id, $item->id]
            ),
            'equipping must create an equipped instance'
        );
        $this->assertFalse(
            $this->link->fetchOne(
                'SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ?',
                [$player->id, $item->id]
            ),
            'the promoted unit must leave the stack'
        );

        $equipped = Item::get_equiped_list($player);
        $this->assertArrayHasKey($item->id, $equipped, 'the weapon must appear in the equipped list');
        $this->assertSame(1, $item->get_n($player, equiped: true), 'get_n(equiped) must see it');

        // applyItemCaracs: gladius carries cc +1 on top of the race base.
        $player->get_caracs();
        $this->assertSame(
            $nudeCc + 1,
            (int) $player->caracs->cc,
            'the equipped gladius must add its cc to the caracs'
        );
        $this->assertSame($nudeCc, (int) $player->nude->cc, 'nude caracs must stay item-free');
    }

    public function testUnequipRestoresTheBaseCaracs(): void
    {
        $player = $this->createRealPlayer('GmSquire');
        $item = $this->gladiusOrSkip();
        $item->add_item($player, 1);

        $player->get_caracs();
        $nudeCc = (int) $player->nude->cc;
        $this->assertSame(EquipResult::Equip, $player->equip($item));

        $this->assertSame(EquipResult::Unequip, $player->equip($item), 'second use unequips');

        // Phase 1c: a PRISTINE instance silently returns to its stack —
        // the invisible round trip that keeps data lean.
        $this->assertSame(
            1,
            (int) $this->link->fetchOne(
                'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
                [$player->id, $item->id]
            ),
            'the pristine unequipped unit must be back in the stack'
        );
        $this->assertFalse(
            $this->link->fetchOne(
                'SELECT 1 FROM players_items_instances l JOIN item_instances i ON i.id = l.instance_id
                 WHERE l.player_id = ? AND i.item_id = ?',
                [$player->id, $item->id]
            ),
            'no instance may linger after a pristine unequip'
        );

        $player->get_caracs();
        $this->assertSame($nudeCc, (int) $player->caracs->cc, 'caracs must return to the race base');
        $this->assertSame(0, $item->get_n($player, equiped: true));
    }

    public function testAWornInstanceSurvivesUnequipAndABrokenOneStopsContributing(): void
    {
        $player = $this->createRealPlayer('GmSquire');
        $item = $this->gladiusOrSkip();
        $item->add_item($player, 1);

        $player->get_caracs();
        $nudeCc = (int) $player->nude->cc;
        $player->equip($item);

        $instanceId = (int) $this->link->fetchOne(
            'SELECT l.instance_id FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id WHERE l.player_id = ? AND i.item_id = ?',
            [$player->id, $item->id]
        );
        $this->setRemainingLife($instanceId, 40);

        $player->equip($item); // unequip

        $this->assertNotFalse(
            $this->link->fetchOne('SELECT 1 FROM item_instances WHERE id = ?', [$instanceId]),
            'a WORN instance must survive unequip as its own line'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ?', [$player->id, $item->id]),
            'a worn unit must NOT rejoin the stack'
        );
        $this->assertSame(1, $item->get_n($player), 'the worn instance still counts as owned');

        // Re-equip reuses the worn instance; broken (<= 0) stops contributing caracs.
        $player->equip($item);
        $this->setRemainingLife($instanceId, 0);
        $player->get_caracs();
        $this->assertSame($nudeCc, (int) $player->caracs->cc, 'a broken weapon contributes no caracs');
        $this->assertSame('gladius', (string) $player->emplacements->main1->row->name, 'but it is still worn/visible');
    }

    public function testGiveItemMovesTheStackBetweenPlayers(): void
    {
        $giver = $this->createRealPlayer('GmGiver');
        $taker = $this->createRealPlayer('GmTaker');
        $item = $this->gladiusOrSkip();
        $item->add_item($giver, 4);

        $item->give_item($giver, $taker, 3);

        $this->assertSame(1, $item->get_n($giver), 'giver keeps the remainder');
        $this->assertSame(3, $item->get_n($taker), 'taker receives the given quantity');
    }
}
