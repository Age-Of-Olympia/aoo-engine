<?php

namespace Tests\Various;

use Classes\Item;
use PHPUnit\Framework\TestCase;

/**
 * The XP a consumable grants shows in its carac line (inventory, item sheet),
 * like PF and malus do.
 */
class ItemCaracXpDisplayTest extends TestCase
{
    public function testAPositiveXpIsShownAsABonus(): void
    {
        $this->assertContains('<font color="blue">XP+5</font>', Item::get_item_carac((object) ['xp' => 5], []));
    }

    public function testANegativeXpIsShownAsAMalus(): void
    {
        $this->assertContains('<font color="red">XP-3</font>', Item::get_item_carac((object) ['xp' => -3], []));
    }

    public function testNoXpShowsNothing(): void
    {
        $this->assertSame([], Item::get_item_carac((object) [], []));
    }
}
