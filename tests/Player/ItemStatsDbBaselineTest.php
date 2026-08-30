<?php

namespace Tests\Player;

use App\Service\ItemStatsSeeder;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Items JSON→DB — the equivalence proof: what the seeder writes, get_data()
 * serves back, key by key, including the keys that only travel through the
 * lossless `extra` catch-all. If that holds, the ~110 Item call sites are
 * safe by construction.
 *
 * Proved on a fixture rather than on the live catalog. The sweep over every
 * `stats_in_db = 1` row proved the migration when it ran and then became a
 * trap: the admin sheet is the source of truth now, so an edited item is
 * SUPPOSED to differ from its frozen JSON, and any ordinary admin edit
 * turned the suite red.
 *
 * Skipped keys are the ones the seed deliberately does not source from
 * JSON (identity + flags whose DB is already the source, and stray copies
 * of joined rows found in some legacy files).
 */
#[Group('items-baseline')]
class ItemStatsDbBaselineTest extends LegacyPlayerFixtureTestCase
{
    /**
     * The seeder is lossless: every key of an item JSON reaches get_data().
     *
     * This used to sweep the LIVE catalog, comparing each `stats_in_db = 1`
     * row against its historical JSON. That proved the migration at the time
     * and then rotted into a trap: the admin sheet is the source of truth
     * now, so any legitimate item edit made the suite red — the DB is
     * SUPPOSED to diverge from a frozen JSON once someone edits it. It fired
     * the day a torch was given an equip slot.
     *
     * The property worth keeping is the seeder's, not the catalog's, so it
     * is proved on a fixture: a JSON built here, seeded, read back key by
     * key. Deterministic, and indifferent to what admins do afterwards.
     */
    public function testTheSeederCarriesEveryJsonKeyThrough(): void
    {
        $name = 'zz_baseline_' . bin2hex(random_bytes(4));
        $docroot = sys_get_temp_dir() . '/baseline_' . $name;
        mkdir($docroot . '/datas/public/items', 0777, true);

        $json = [
            'name' => $name,
            'price' => 42,
            'pv' => 3,
            'cc' => -2,
            'text' => 'Une relique de test.',
            'emplacement' => 'main1',
            'type' => 'equipement',
            'subtype' => 'melee',
            'race' => 'nain',
            'munitions' => ['fleche'],
            'addEffects' => [['name' => 'feu', 'duration' => 2]],
            // A key no column owns travels through the lossless `extra`.
            'uneProprieteInconnue' => 'gardee telle quelle',
        ];
        file_put_contents(
            $docroot . '/datas/public/items/' . $name . '.json',
            (string) json_encode($json)
        );

        $this->sowCatalogItem($name, ['stats_in_db' => 0]);
        (new ItemStatsSeeder())->seed($this->link, $docroot);

        $item = Item::get_item_by_name($name);
        $this->assertNotFalse($item, 'the fixture item exists');
        $data = $item->get_data();

        foreach ($json as $key => $expected) {
            if (in_array($key, ItemStatsSeeder::SKIP_KEYS, true)) {
                continue;
            }
            $this->assertEquals(
                $expected,
                json_decode((string) json_encode($data->$key ?? null), true),
                "key '{$key}' did not survive the JSON -> DB -> get_data() trip"
            );
        }

        unlink($docroot . '/datas/public/items/' . $name . '.json');
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
