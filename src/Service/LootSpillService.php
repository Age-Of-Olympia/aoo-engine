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
        // death() loads data first; vanish() does not.
        if (!isset($entity->data)) {
            $entity->get_data();
        }

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

            $nbLoot = $this->rollFor((int) $row->n, $this->chanceFor($entity, $loot, $row));

            if ($nbLoot > 0) {
                $entity->drop($loot, $nbLoot);
                $lootList[] = $loot->data->name . ' x' . $nbLoot;
            }
        }

        $lootList = array_merge($lootList, $this->spillChildren($entity));

        if (count($lootList)) {
            $text = $entity->data->name . ' a perdu des objets: ' . implode(', ', $lootList) . '.';
            Log::put($entity, $entity, $text, type: "loot");
        }

        return $lootList;
    }

    /**
     * How many of $n units survive the fall, at $chance percent each.
     *
     * @return int units that drop
     */
    private function rollFor(int $n, int $chance): int
    {
        if ($chance >= 100) {
            return $n;
        }

        $dropped = 0;
        for ($i = 0; $i < $n; $i++) {
            if (random_int(1, 100) <= $chance) {
                $dropped++;
            }
        }

        return $dropped;
    }

    /**
     * Held entities take the same roll as a stack unit, and the same chance
     * rules — the exemplar keeps its identity when it lands, and is lost
     * outright when the roll fails, exactly as an undropped unit is.
     *
     * @return string[] labels of what fell
     */
    private function spillChildren(Player $entity): array
    {
        $coordsId = (int) ($entity->data->coords_id ?? 0);

        if ($coordsId === 0) {
            return array();
        }

        $location = new \App\Service\Map\EntityLocationService();
        $fallen = array();

        foreach ($this->heldExemplars((int) $entity->id) as $child) {
            $loot = new Item((int) $child['item_id'], (object) $child);
            $loot->get_data();

            if ($this->rollFor(1, $this->chanceFor($entity, $loot, (object) $child)) === 0) {
                // Lost with its holder, like a unit the roll left behind.
                $location->shelve((int) $child['id']);
                continue;
            }

            $location->dropOnCell((int) $child['id'], $coordsId);
            $fallen[] = $child['label'];
        }

        return $fallen;
    }

    /**
     * What the entity holds, shaped like a `players_items` row so the chance
     * rules read it without knowing it is an exemplar.
     *
     * @return list<array{id: int, item_id: int, n: int, equiped: string, name: string, lootChance: int|null, label: string}>
     */
    private function heldExemplars(int $entityId): array
    {
        $rows = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection()
            ->fetchAllAssociative(
                "SELECT p.id, ii.item_id, 1 AS n,
                        IF(p.slot IN (?, ?, ?), '', p.slot) AS equiped,
                        it.name, it.lootChance,
                        IF(ii.custom_name <> '', ii.custom_name, it.name) AS label
                   FROM players p
                   JOIN item_instances ii ON ii.entity_id = p.id
                   JOIN items it ON it.id = ii.item_id
                  WHERE p.holder_id = ?",
                [
                    \App\Service\Map\EntityLocationService::SLOT_CARRIED,
                    \App\Service\Map\EntityLocationService::SLOT_DROPPED,
                    \App\Service\Map\EntityLocationService::SLOT_INSTALLED,
                    $entityId,
                ]
            );

        /** @var list<array{id: int, item_id: int, n: int, equiped: string, name: string, lootChance: int|null, label: string}> */
        return $rows;
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
