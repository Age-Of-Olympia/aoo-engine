<?php

namespace App\Service;

use Classes\Db;
use Classes\Item;
use Classes\Log;
use Classes\Player;

/**
 * Ce qu'une entité laisse tomber en mourant.
 *
 * Extrait de `Player::death()`, où la répartition du butin était mêlée à ce qui
 * ne concerne QUE les personnages : la descente aux enfers, la purge des
 * effets, la remise à zéro des dégâts. Un coffre ne va pas en enfer, mais il
 * répand son contenu — c'est exactement la même chose que mourir, moins le
 * reste.
 *
 * Le geste s'adresse à une ENTITÉ, pas à un joueur : toute ligne `players` qui
 * possède des objets peut les répandre. C'est ce qui permet à un coffre de se
 * casser comme on meurt, au lieu de voir son contenu disparaître avec lui.
 */
final class LootSpillService
{
    /**
     * Répand au sol ce que l'entité possède, chaque objet selon sa chance de
     * butin, et journalise la perte.
     *
     * @return string[] libellés de ce qui est tombé ([] = rien)
     */
    public function spill(Player $entity): array
    {
        $db = new Db();

        $res = $db->exe(
            'SELECT item_id, n, equiped, i.name, i.lootChance
               FROM players_items AS pi
               INNER JOIN items AS i ON pi.item_id = i.id
              WHERE player_id = ?',
            $entity->id
        );

        $lootList = array();

        while ($row = $res->fetch_object()) {

            $loot = new Item($row->item_id, $row);
            $loot->get_data();

            $lootChance = $this->chanceFor($entity, $loot, $row);

            $nbLoot = 0;
            if ($lootChance >= 100) {
                $nbLoot = $row->n;
            } else {
                for ($i = 0; $i < $row->n; $i++) {
                    if (random_int(1, 100) <= $lootChance) {
                        $nbLoot++;
                    }
                }
            }

            if ($nbLoot > 0) {
                $entity->drop($loot, $nbLoot);
                $lootList[] = $loot->data->name . ' x' . $nbLoot;
            }
        }

        if (count($lootList)) {
            $text = $entity->data->name . ' a perdu des objets: ' . implode(', ', $lootList) . '.';
            Log::put($entity, $entity, $text, type: "loot");
        }

        return $lootList;
    }

    /**
     * La chance qu'un objet tombe : le catalogue, puis les stats propres de
     * l'objet, puis le sort particulier de l'équipé et des PNJ.
     */
    private function chanceFor(Player $entity, Item $loot, object $row): int
    {
        $lootChance = LOOT_CHANCE_DEFAULT;

        // catalog loot chance (items.lootChance, ex-constante LOOT_CHANCE)
        if (!empty($row->lootChance)) {
            $lootChance = (int) $row->lootChance;
        }

        // custom loot chance (source des stats : JSON legacy possible)
        if (!empty($loot->data->lootChance)) {
            $lootChance = $loot->data->lootChance;
        }

        if ($row->equiped) {
            // pnj will not drop equiped item
            if ($entity->id < 0) {
                $lootChance = 0;
            } else {
                $lootChance = floor($lootChance / 2);
            }
        } elseif ($entity->id < 0) {
            // if pnj and not equiped, will drop everytime
            $lootChance = 100;
        }

        return (int) $lootChance;
    }
}
