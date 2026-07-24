<?php

namespace App\Service;

use Classes\Db;

/**
 * Qui détient un objet du catalogue — inventaire et banque confondus,
 * mêmes sources que le compteur « Joueurs » de admin/items.php
 * (items_owner_counts) : la liste détaille ce que le compteur agrège.
 */
class ItemOwnershipService
{
    /**
     * Détenteurs de l'objet, nom-ordonnés : quantité en inventaire,
     * emplacement équipé éventuel, quantité en banque.
     *
     * @return array<int, array{id:int, name:string, race:string, inv:int, equiped:string, bank:int}>
     */
    public function itemOwners(int $itemId): array
    {
        $sql = 'SELECT p.id, p.name, p.race,
                       COALESCE(pi.n, 0) AS inv,
                       COALESCE(pi.equiped, "") AS equiped,
                       COALESCE(pb.n, 0) AS bank
                FROM players p
                LEFT JOIN players_items pi ON pi.player_id = p.id AND pi.item_id = ?
                LEFT JOIN players_items_bank pb ON pb.player_id = p.id AND pb.item_id = ?
                WHERE pi.item_id IS NOT NULL OR pb.item_id IS NOT NULL
                ORDER BY p.name ASC';

        $res = (new Db())->exe($sql, [$itemId, $itemId]);

        $owners = [];
        while ($row = $res->fetch_assoc()) {
            $owners[] = [
                'id'      => (int) $row['id'],
                'name'    => (string) $row['name'],
                'race'    => (string) $row['race'],
                'inv'     => (int) $row['inv'],
                'equiped' => (string) $row['equiped'],
                'bank'    => (int) $row['bank'],
            ];
        }

        return $owners;
    }
}
