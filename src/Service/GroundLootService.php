<?php

namespace App\Service;

use Classes\Db;
use Classes\Item;
use Classes\Log;
use Classes\Player;

/**
 * Ramassage du contenu d'une case : piles map_items ET instances au
 * sol, en un seul geste. Extrait de go.php (arrivée sur la case) pour
 * servir aussi pickup.php — ramasser SA PROPRE case (objet lâché par
 * erreur) sans devoir sortir puis revenir.
 */
class GroundLootService
{
    /**
     * Contenu au sol d'une case, côté LECTURE (panneau d'observation) :
     * piles map_items et instances — le pendant read-only de collect(),
     * même périmètre.
     *
     * @return array{stacks: array<int, object>, instances: array<int, object>}
     */
    public function listAt(int $x, int $y, int $z, string $plan): array
    {
        $db = new Db();

        $stacks = [];
        $res = $db->exe(
            'SELECT mi.n, i.id AS item_id, i.name
             FROM map_items AS mi
             INNER JOIN coords AS c ON mi.coords_id = c.id
             INNER JOIN items AS i ON i.id = mi.item_id
             WHERE c.x = ? AND c.y = ? AND c.z = ? AND c.plan = ?
             ORDER BY i.name',
            [$x, $y, $z, $plan]
        );
        while ($row = $res->fetch_object()) {
            $stacks[] = $row;
        }

        $instances = [];
        $res = $db->exe(
            'SELECT i.id, i.custom_name, i.durability, i.durability_max, it.name
             FROM map_items_instances AS g
             INNER JOIN coords AS c ON g.coords_id = c.id
             INNER JOIN item_instances AS i ON i.id = g.instance_id
             INNER JOIN items AS it ON it.id = i.item_id
             WHERE c.x = ? AND c.y = ? AND c.z = ? AND c.plan = ?',
            [$x, $y, $z, $plan]
        );
        while ($row = $res->fetch_object()) {
            $instances[] = $row;
        }

        return ['stacks' => $stacks, 'instances' => $instances];
    }

    /**
     * Collect everything on the tile into the player's inventory,
     * write the loot log, refresh the inventory cache.
     *
     * @param object $logCoords coords {x,y,z,plan} stamped on the log entry
     *
     * @return string[] display labels of what was taken ([] = nothing)
     */
    public function collect(Player $player, int $coordsId, object $logCoords): array
    {
        $db = new Db();

        $res = $db->exe('SELECT * FROM map_items WHERE coords_id = ?', $coordsId);

        // Instances au sol : identité (usure, nom) préservée.
        $lootList = (new ItemInstanceService())->collectAt($coordsId, (int) $player->id);
        $hadInstances = count($lootList) > 0;

        if (!$res->num_rows && !$hadInstances) {
            return [];
        }

        while ($row = $res->fetch_object()) {
            $item = new Item($row->item_id);
            $item->get_data();
            $item->add_item($player, $row->n);
            $lootList[] = $item->data->name . ' x' . $row->n;
        }

        $db->delete('map_items', ['coords_id' => $coordsId]);

        // add_item invalide le cache pour les piles ; les instances doivent
        // aussi apparaître dès l'ouverture de l'inventaire.
        if ($hadInstances) {
            $player->refresh_invent();
        }

        $text = $player->data->name . ' a ramassé des objets: ' . implode(', ', $lootList) . '.';
        // Log::put lit les coords sur l'objet joueur : substitution
        // temporaire, restaurée QUOI QU'IL ARRIVE — une exception ne doit
        // pas laisser l'objet partagé corrompu pour la suite de la requête.
        $coordBackup = $player->coords ?? null;
        $player->coords = $logCoords;
        try {
            Log::put($player, $player, $text, type: 'loot');
        } finally {
            $player->coords = $coordBackup;
        }

        return $lootList;
    }
}
