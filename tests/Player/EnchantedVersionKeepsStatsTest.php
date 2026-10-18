<?php

namespace Tests\Player;

use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * An enchanted (or cursed, vorpal…) version of an item is a new catalogue row:
 * it carries its source's stats, read from the DB like any other item.
 */
#[Group('items-baseline')]
class EnchantedVersionKeepsStatsTest extends LegacyPlayerFixtureTestCase
{
    public function testTheVersionCopiesItsSourceStats(): void
    {
        $source = $this->sowCatalogItem('zz_lame_' . bin2hex(random_bytes(3)), [
            'type' => 'equipement', 'stats_in_db' => 1, 'emplacement' => 'main1', 'cc' => 3, 'price' => 42,
        ]);

        $versionId = $source->get_version(['enchanted' => 1]);

        $this->assertNotSame((int) $source->id, (int) $versionId, 'a new row');
        $version = new Item($versionId);
        $version->get_data();
        $this->assertSame(1, (int) $version->row->enchanted);
        $this->assertSame(1, (int) $version->row->stats_in_db, 'served from the DB');
        $this->assertSame(3, (int) $version->data->cc);
        $this->assertSame(42, (int) $version->data->price);
        $this->assertSame((int) $versionId, (int) $source->get_version(['enchanted' => 1]), 'asked again, the same row');
    }
}
