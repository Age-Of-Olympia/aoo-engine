<?php

namespace App\Service;

use Classes\Db;

/**
 * Qui détient un objet du catalogue, et OÙ.
 *
 * Un exemplaire n'est jamais à deux endroits : les quatre colonnes
 * s'additionnent sans doublon, parce que chaque transfert débite
 * réellement l'emplacement de départ.
 *
 *   - inventaire : players_items (pile) + les instances « inventory » ;
 *   - banque     : players_items_bank (pile) + les instances « bank » ;
 *   - en vente   : items_bids.stock — mettre en vente DÉBITE la banque
 *                  (BidsAsksService), les objets attendent dans l'offre
 *                  et y retournent à l'annulation ;
 *   - en échange : players_items_exchanges.n — proposer un objet DÉBITE
 *                  aussi la banque (api/exchanges/exchanges-edit.php),
 *                  il y retourne au refus.
 *
 * Sans les deux dernières, un objet engagé dans une vente ou un échange
 * disparaissait purement et simplement de cette liste : il n'était plus
 * ni en inventaire ni en banque, et rien ne disait où il était passé.
 * (Les demandes d'achat, items_asks, ne séquestrent que de l'or : aucun
 * objet à compter de ce côté.)
 *
 * Sous-requêtes corrélées plutôt que jointures : quatre LEFT JOIN vers
 * des tables à plusieurs lignes par joueur multiplieraient les lignes et
 * fausseraient tous les totaux.
 */
class ItemOwnershipService
{
    /**
     * Détenteurs de l'objet, nom-ordonnés, avec la répartition par
     * emplacement et l'emplacement équipé éventuel.
     *
     * @return array<int, array{id:int, name:string, race:string, inv:int,
     *                          equiped:string, bank:int, market:int, exchange:int}>
     */
    public function itemOwners(int $itemId): array
    {
        $sql = "SELECT p.id, p.name, p.race,
                       COALESCE(pi.n, 0)
                           + COALESCE((SELECT COUNT(*) FROM players ei
                                       JOIN item_instances ii ON ii.entity_id = ei.id
                                       WHERE ei.holder_id = p.id AND ii.item_id = ?
                                         AND ii.destroyed = 0
                                         AND ei.slot NOT IN (" . \App\Service\ItemInstanceService::heldElsewhereSlots() . ")), 0) AS inv,
                       COALESCE(pi.equiped, '') AS equiped,
                       COALESCE(pb.n, 0)
                           + COALESCE((SELECT COUNT(*) FROM players eb
                                       JOIN item_instances ib ON ib.entity_id = eb.id
                                       WHERE eb.holder_id = p.id AND ib.item_id = ?
                                         AND ib.destroyed = 0 AND eb.slot = 'bank'), 0) AS bank,
                       COALESCE((SELECT SUM(b.stock) FROM items_bids b
                                 WHERE b.player_id = p.id AND b.item_id = ?), 0) AS market,
                       COALESCE((SELECT SUM(e.n) FROM players_items_exchanges e
                                 WHERE e.player_id = p.id AND e.item_id = ?), 0) AS exchange
                FROM players p
                LEFT JOIN players_items pi ON pi.player_id = p.id AND pi.item_id = ? AND pi.slot = ''
                LEFT JOIN players_items_bank pb ON pb.player_id = p.id AND pb.item_id = ?
                HAVING inv > 0 OR bank > 0 OR market > 0 OR exchange > 0
                ORDER BY p.name ASC";

        $res = (new Db())->exe($sql, array_fill(0, 6, $itemId));

        $owners = [];
        while ($row = $res->fetch_assoc()) {
            $owners[] = [
                'id'       => (int) $row['id'],
                'name'     => (string) $row['name'],
                'race'     => (string) $row['race'],
                'inv'      => (int) $row['inv'],
                'equiped'  => (string) $row['equiped'],
                'bank'     => (int) $row['bank'],
                'market'   => (int) $row['market'],
                'exchange' => (int) $row['exchange'],
            ];
        }

        return $owners;
    }
}
