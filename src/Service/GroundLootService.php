<?php

namespace App\Service;

use Classes\Db;
use Classes\Item;
use Classes\Log;
use Classes\Player;

/**
 * Ramassage du contenu d'une case : piles map_items, instances au sol ET
 * plantes, en un seul geste.
 *
 * Les plantes ont rejoint la bourse au sol quand marcher a cessé de
 * ramasser : elles se récoltaient toutes seules au passage, par un chemin
 * à part (go.php incluait scripts/map/plants.php). Une fleur se cueille
 * maintenant comme un objet se ramasse — on la voit, on la prend.
 *
 * Ce qu'une plante rapporte reste tiré au sort à la récolte, et son
 * journal reste de type « harvest » : cueillir n'est pas ramasser, même
 * si le geste est le même.
 */
class GroundLootService
{
    /**
     * Contenu au sol d'une case, côté LECTURE (panneau d'observation) :
     * piles map_items et instances — le pendant read-only de collect(),
     * même périmètre.
     *
     * @return array{stacks: array<int, object>, instances: array<int, object>, plants: array<int, object>}
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

        /* Les plantes portent le NOM de l'objet qu'elles rendent : c'est ainsi
         * que la récolte les traduisait en butin, et rien ne les relie à
         * `items` autrement. */
        $plants = [];
        $res = $db->exe(
            'SELECT p.id, p.name
             FROM map_plants AS p
             INNER JOIN coords AS c ON p.coords_id = c.id
             WHERE c.x = ? AND c.y = ? AND c.z = ? AND c.plan = ?
             ORDER BY p.name',
            [$x, $y, $z, $plan]
        );
        while ($row = $res->fetch_object()) {
            $plants[] = $row;
        }

        return ['stacks' => $stacks, 'instances' => $instances, 'plants' => $plants];
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

        /* Les plantes se cueillent dans le même geste, mais gardent leur
         * journal : la récolte n'est pas le ramassage. */
        $harvest = $this->harvestPlants($player, $coordsId, $logCoords);

        if (!$res->num_rows && !$hadInstances) {
            return $harvest;
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

        return array_merge($lootList, $harvest);
    }

    /**
     * Cueillir les plantes de la case : chacune rend 1 à 3 de l'objet dont
     * elle porte le nom, puis disparaît.
     *
     * Le tirage, le libellé et le journal « harvest » viennent de
     * scripts/map/plants.php, que le déplacement incluait à l'arrivée. Seul
     * le déclencheur change : on ne cueille plus en passant.
     *
     * @param object $logCoords coords {x,y,z,plan} portées par le journal
     *
     * @return string[] libellés de ce qui a été cueilli ([] = aucune plante)
     */
    private function harvestPlants(Player $player, int $coordsId, object $logCoords): array
    {
        $db = new Db();
        $res = $db->exe('SELECT id, name FROM map_plants WHERE coords_id = ?', $coordsId);

        if (!$res->num_rows) {
            return [];
        }

        $picked = [];
        $texts = [];

        while ($row = $res->fetch_object()) {

            $item = Item::get_item_by_name($row->name);

            /* Une plante dont l'objet a disparu du catalogue reste en terre :
             * la retirer sans rien donner en échange serait pire. */
            if ($item === false) {
                continue;
            }

            $quantity = rand(1, 3);
            $item->add_item($player, $quantity);
            $item->get_data();

            $db->delete('map_plants', ['id' => $row->id]);

            $label = ucfirst((string) $item->data->name) . ' x' . $quantity;
            $picked[] = $label;
            $texts[] = $player->data->name . ' a récolté ' . $label . '.';
        }

        if ($texts === []) {
            return [];
        }

        /* Log::put lit les coords sur l'objet joueur — même substitution que
         * pour le butin, et même restauration quoi qu'il arrive. */
        $coordBackup = $player->coords ?? null;
        $player->coords = $logCoords;
        try {
            foreach ($texts as $text) {
                Log::put($player, $player, $text, 'harvest');
            }
        } finally {
            $player->coords = $coordBackup;
        }

        return $picked;
    }
}
