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
#[Group('items-golden-master')]
class ItemPipelineGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private function gladiusOrSkip(): Item
    {
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        $item->get_data();
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

        $this->assertSame(
            'main1',
            $this->link->fetchOne(
                'SELECT equiped FROM players_items WHERE player_id = ? AND item_id = ?',
                [$player->id, $item->id]
            ),
            'equipping must persist the emplacement on the stack row'
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

        $this->assertSame(
            '',
            $this->link->fetchOne(
                'SELECT equiped FROM players_items WHERE player_id = ? AND item_id = ?',
                [$player->id, $item->id]
            ),
            'unequipping must clear the emplacement'
        );

        $player->get_caracs();
        $this->assertSame($nudeCc, (int) $player->caracs->cc, 'caracs must return to the race base');
        $this->assertSame(0, $item->get_n($player, equiped: true));
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
