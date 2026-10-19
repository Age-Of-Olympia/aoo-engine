<?php

namespace Tests\Player;

use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Every worn item's esquive counts, not only the last one's, and the
 * passives add to it instead of replacing it.
 */
#[Group('items-baseline')]
class ItemEsquiveTest extends LegacyPlayerFixtureTestCase
{
    public function testEveryWornItemAddsItsEsquive(): void
    {
        $player = $this->createRealPlayer('GmEsquive');
        $player->get_caracs();
        $base = (int) $player->caracs->esquive;

        foreach (['tronc' => -2, 'tete' => 1] as $slot => $esquive) {
            $item = $this->sowCatalogItem('zz_esq_' . $slot . '_' . bin2hex(random_bytes(3)), [
                'type' => 'equipement',
                'stats_in_db' => 1,
                'emplacement' => $slot,
                'esquive' => $esquive,
            ]);
            $item->get_data();
            $item->add_item($player, 1);
            $player->equip($item);
        }

        $player->get_caracs();
        $this->assertSame($base - 1, (int) $player->caracs->esquive, 'lorica -2 and helmet +1');

        $player->get_caracs();
        $this->assertSame($base - 1, (int) $player->caracs->esquive, 'a second refresh must not add again');
    }
}
