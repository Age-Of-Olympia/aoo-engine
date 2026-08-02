<?php

namespace Tests\Player;

use App\Service\ItemStatsSeeder;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Items JSON→DB — the equivalence proof: for EVERY catalog item whose
 * stats were seeded into columns (stats_in_db = 1), the DB-served
 * Item::get_data() must expose the exact values of its historical JSON
 * file, key by key — including the keys that only travelled through
 * the lossless `extra` catch-all. If this holds for the whole seeded
 * catalog, the ~110 Item call sites are safe by construction.
 *
 * Skipped keys are the ones the seed deliberately does not source from
 * JSON (identity + flags whose DB is already the source, and stray
 * copies of joined rows found in some legacy files).
 */
#[Group('items-baseline')]
class ItemStatsDbBaselineTest extends LegacyPlayerFixtureTestCase
{
    public function testDbServedDataMatchesEveryHistoricalJsonKey(): void
    {
        try {
            $rows = $this->link->fetchAllAssociative(
                'SELECT id, name, private FROM items WHERE stats_in_db = 1'
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('items stats columns unavailable (run migrations): ' . $e->getMessage());
        }

        if ($rows === []) {
            $this->markTestSkipped('no seeded item in this environment (run admin/item-seed.php).');
        }

        $root = dirname(__DIR__, 2);
        $checked = 0;

        foreach ($rows as $row) {
            $dir = ((int) $row['private']) ? 'private' : 'public';
            $path = $root . '/datas/' . $dir . '/items/' . $row['name'] . '.json';
            if (!is_file($path)) {
                continue; // seeded elsewhere; nothing to compare against here
            }

            $json = json_decode((string) file_get_contents($path));
            $item = new Item((int) $row['id']);
            $data = $item->get_data();

            foreach (get_object_vars($json) as $key => $expected) {
                if (in_array($key, ItemStatsSeeder::SKIP_KEYS, true)) {
                    continue;
                }
                // get_data() post-processing: name is ucfirst'd, img/mini defaulted.
                if (in_array($key, ['name', 'img', 'mini'], true)) {
                    continue;
                }

                $actual = $data->$key ?? null;
                $this->assertEquals(
                    $expected,
                    $actual,
                    "item '{$row['name']}' key '{$key}': DB-served data diverged from the historical JSON"
                );
            }
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'at least one seeded item must have been compared');
    }

    public function testAppliedCaracsAreIdenticalThroughTheDbPath(): void
    {
        // The end consumer that matters most: an equipped seeded weapon
        // must contribute exactly its JSON caracs — pinned end to end by
        // the existing equip golden master (gladius cc +1), re-run here
        // against the DB-backed catalog for explicitness.
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        if (empty($item->row->stats_in_db)) {
            $this->markTestSkipped('gladius stats not in DB in this environment.');
        }
        $item->get_data();

        $this->assertSame('main1', $item->data->emplacement);
        $this->assertSame(1, (int) $item->data->cc);

        $player = $this->createRealPlayer('GmDbStats');
        $item->add_item($player, 1);
        $player->get_caracs();
        $nudeCc = (int) $player->nude->cc;
        $player->equip($item);
        $player->get_caracs();

        $this->assertSame($nudeCc + 1, (int) $player->caracs->cc, 'DB-served caracs flow through applyItemCaracs');
    }
}
