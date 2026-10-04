<?php

namespace Tests\Player;

use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Equipping onto a taken slot replaces the worn item and refunds its turn
 * bonuses, even when the caller loaded only get_data() (the inventory page).
 */
#[Group('items-baseline')]
class EquipReplacesWornItemTest extends LegacyPlayerFixtureTestCase
{
    public function testEquipReplacesTheWornItemAndRefundsItsBonus(): void
    {
        $spec = ['type' => 'equipement', 'stats_in_db' => 1, 'durability_max' => 100];
        $heavy = $this->sowCatalogItem('zz_lourde_' . bin2hex(random_bytes(3)), $spec + ['emplacement' => 'deuxmains', 'pm' => 2]);
        $light = $this->sowCatalogItem('zz_legere_' . bin2hex(random_bytes(3)), $spec + ['emplacement' => 'main1']);

        $player = $this->createRealPlayer('GmSwap');
        $heavy->add_item($player, 1);
        $light->add_item($player, 1);
        $player->get_caracs();
        $player->equip($heavy);
        $this->assertSame(-2, $this->pmBonus((int) $player->id), 'equipping costs the PM bonus');

        $fresh = new Player($player->id);
        $fresh->get_data();
        $fresh->equip($light);

        $this->assertSame('', $this->slotOfInstance($this->instanceHeldBy((int) $player->id, (int) $heavy->id)), 'the two-handed weapon is unequipped');
        $this->assertSame(0, $this->pmBonus((int) $player->id), 'its PM bonus is refunded');
    }

    private function pmBonus(int $playerId): int
    {
        return (int) $this->link->fetchOne("SELECT n FROM players_bonus WHERE player_id = ? AND name = 'pm'", [$playerId]);
    }
}
