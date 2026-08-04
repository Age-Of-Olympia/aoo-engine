<?php

namespace App\Service;

use Classes\Db;

/**
 * The materials resting in an entity's walls — the 'fabric' SLOT of the
 * one stack inventory (players_items.slot), of which this service is
 * the sole writer.
 *
 * '' is the bag every legacy reader means and was scoped to; 'fabric'
 * holds what construire poured into the walls and what the admin hides
 * there. LootSpillService rolls it with the loot rules when the walls
 * fall; future modes have their dimension ready.
 */
class FabricService extends BaseService
{
    public const SLOT = 'fabric';

    /**
     * Put units in the walls, resolved by item NAME — the shape a recipe
     * answers in (RecipeService::ingredientsForResult). Unknown names are
     * skipped: a recipe naming a retired item must not block the build.
     *
     * @param array<string, int> $ingredientsByName name => units
     */
    public function storeByName(int $entityId, array $ingredientsByName): void
    {
        $db = new Db();

        foreach ($ingredientsByName as $itemName => $units) {
            if ((int) $units <= 0) {
                continue;
            }

            $db->exe(
                'INSERT INTO players_items (player_id, item_id, n, slot)
                 SELECT ?, id, ?, ? FROM items WHERE name = ?
                 ON DUPLICATE KEY UPDATE n = n + VALUES(n)',
                [$entityId, (int) $units, self::SLOT, (string) $itemName]
            );
        }
    }

    /** The admin's gesture: set one line, 0 removes it. */
    public function setUnits(int $entityId, int $itemId, int $units): void
    {
        $db = new Db();

        if ($units <= 0) {
            $db->exe(
                'DELETE FROM players_items WHERE player_id = ? AND item_id = ? AND slot = ?',
                [$entityId, $itemId, self::SLOT]
            );

            return;
        }

        $db->exe(
            'INSERT INTO players_items (player_id, item_id, n, slot) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE n = VALUES(n)',
            [$entityId, $itemId, $units, self::SLOT]
        );
    }

    /** @return array<int, array{item_id: int, name: string, n: int}> */
    public function contentsOf(int $entityId): array
    {
        $res = (new Db())->exe(
            'SELECT pi.item_id, i.name, pi.n
               FROM players_items pi
               JOIN items i ON i.id = pi.item_id
              WHERE pi.player_id = ? AND pi.slot = ?
              ORDER BY i.name',
            [$entityId, self::SLOT]
        );

        $rows = [];
        while ($row = $res->fetch_object()) {
            $rows[] = ['item_id' => (int) $row->item_id, 'name' => (string) $row->name, 'n' => (int) $row->n];
        }

        return $rows;
    }

    public function clear(int $entityId): void
    {
        (new Db())->exe(
            'DELETE FROM players_items WHERE player_id = ? AND slot = ?',
            [$entityId, self::SLOT]
        );
    }
}
