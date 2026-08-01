<?php

namespace App\Service;

use Classes\Db;
use Throwable;
use Classes\Item;
use Classes\Player;
use Classes\Log;
class BidsAsksService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    private function get_bid_ask_db_dataById($type, $id, $player)
    {
        $sql = '
        SELECT
        *
        FROM
        items_' . $type . '
        where id = ?
        and player_id = ?
        ';

        $res = $this->db->exe($sql,  array($id, $player->id));

        return $res;
    }

    public function Cancel(string $type, int $id, $player): void
    {
        $this->db->start_transaction('cancel_bid_ask');
        $dbData = $this->get_bid_ask_db_dataById($type, $id, $player);
        try {
            if ($type == 'bids') {
                while ($row = $dbData->fetch_object()) {

                    /* Exemplaire séquestré : il revient au coffre par sa
                     * localisation, pas en recréditant une pile — sans
                     * quoi on fabriquerait une unité vierge et on
                     * laisserait l'exemplaire en vente pour toujours. */
                    if (!empty($row->instance_id)) {

                        try {
                            (new ItemInstanceService())
                                ->releaseFromMarket((int) $row->instance_id, (int) $player->id);
                        } catch (\InvalidArgumentException $e) {
                            ExitError($e->getMessage());
                        }

                        continue;
                    }

                    $item = new Item($row->item_id, row: false, checked: true);
                    if (!$item->add_item($player, $row->stock, bank: true)) {
                        ExitError("Erreur lors du retour des objets dans la banque");
                    }
                }
            }

            if ($type == 'asks') {

                while ($row = $dbData->fetch_object()) {

                    //give back gold
                    $gold = Item::get_item_by_name('or', checked: true);
                    $gold->add_item($player, $row->stock * $row->price);
                }
            }

            $values = array('id' => $id, 'player_id' => $player->id);

            $this->db->delete('items_' . $type, $values);
            $this->db->commit_transaction('cancel_bid_ask');
        } catch (Throwable $th) {
            $this->db->rollback_transaction('cancel_bid_ask');
            ExitError("Erreur lors de l'annulation");
        }
        $message = $type == 'asks' ? "La demande a été annulée." : "L'offre a été annulée.";
        ExitSuccess(["message" => $message, "redirect" => "merchant.php?{$type}&targetId={$_GET['targetId']}"]);
    }

    /**
     * @param int|null $instanceId exemplaire individualisé mis en vente.
     *        L'offre porte alors une RÉFÉRENCE : l'usure et le nom
     *        restent sur item_instances, rien n'est recopié. Réservé aux
     *        offres de vente — une demande d'achat n'entiercit que de
     *        l'or et porte un objet de catalogue.
     */
    public function Create(string $type, int $itemId, int $price, int $quantity, $player, ?int $instanceId = null, int $minCondition = 0): void
    {
        $item = new Item($itemId, row: false, checked: true);
        $item->get_data();

        if ($instanceId !== null && $type !== 'bids') {
            ExitError("Un exemplaire ne peut être mis qu'en vente.");
        }

        /* Un exemplaire est unique : la quantité n'a pas de sens, et
         * l'accepter ouvrirait une duplication (une offre « x3 »
         * pointant un seul objet). Le client l'impose déjà, le serveur
         * ne s'y fie pas. */
        if ($instanceId !== null) {
            $quantity = 1;
        }

        if (!empty($item->data->forbid->market)) {
            ExitError("Impossible de créer un contrat sur cet objet");
        }

        if ($price < 1) {
            $auditService = new AuditService();
            $auditService->addAuditLog("Tentative de triche bids/asks");
            ExitError("Prix invalide");
        }

        if ($quantity < 1) {
            $auditService = new AuditService();
            $auditService->addAuditLog("Tentative de triche bids/asks");
            ExitError("Quantité invalide");
        }
        $this->db->start_transaction('create_bid_ask');

        try {
            $values = array(
                'item_id' => $item->id,
                'player_id' => $player->id,
                'n' => $quantity,
                'price' => $price,
                'stock' => $quantity
            );

            if ($type == 'bids') {

                /* Exemplaire : on ne débite AUCUNE pile — c'était le
                 * bug. add_item garde strictement sur la pile, si bien
                 * qu'un vendeur possédant aussi une pile du même objet
                 * la voyait débitée à la place de l'exemplaire cliqué :
                 * son arme usée restait au coffre et une unité vierge
                 * partait au marché, sans message. L'exemplaire change
                 * ici d'emplacement, pas de propriétaire. */
                if ($instanceId !== null) {

                    $instanceService = new ItemInstanceService();

                    try {
                        $instanceService->escrowForMarket($instanceId, (int) $player->id);
                    } catch (\InvalidArgumentException $e) {
                        ExitError($e->getMessage());
                    }

                    $values['instance_id'] = $instanceId;
                    $this->db->insert('items_bids', $values);

                    $label = $instanceService->describe($instanceId);
                    Log::put($player, $player, "Vous avez mis en vente un objet",
                        "hidden_action", "{$label} à {$price} Or.", time());
                } elseif ($item->add_item($player, -$quantity, bank: true)) {
                    $this->db->insert('items_bids', $values);
                    $logTime = time();
                    $targetLog = "Vous avez mis en vente des objets";
                    $objects = "{$quantity} {$item->row->name} à {$price} Or l'unité.";
                    Log::put($player, $player, $targetLog, "hidden_action", $objects, $logTime);
                } else {
                    ExitError("Vous ne possédez pas assez d'objets dans votre banque");
                }
            }

            if ($type == 'asks') {
                $total = $quantity * $price;

                /* L'acheteur bloque son or À L'AVANCE : il doit pouvoir
                 * dire quel état il accepte, sinon il paie sans savoir
                 * ce qu'on lui livrera. Un palier inconnu vaut « aucune
                 * contrainte » plutôt que d'échouer — l'ancien client
                 * n'envoie rien. */
                $values['min_durability_pct'] =
                    isset(\App\Service\ItemInstanceService::CONDITION_LEVELS[$minCondition])
                        ? $minCondition
                        : 0;

                //remove money to "block" it
                $gold = Item::get_item_by_name('or', checked: true);
                if ($gold->add_item($player, -$total)) {
                    $this->db->insert('items_asks', $values);
                    $logTime = time();
                    $targetLog = "Vous avez créé une demande d'Achat";
                    $objects = "{$quantity} {$item->row->name} à {$price} Or l'unité.";
                    Log::put($player, $player, $targetLog, "hidden_action", $objects, $logTime);
                } else {
                    ExitError("Vous ne possédez pas assez d'Or pour acheter {$quantity} {$item->row->name}.");
                }
            }
            $this->db->commit_transaction('create_bid_ask');
        } catch (Throwable $th) {
            $this->db->rollback_transaction('create_bid_ask');
            ExitError("Erreur lors de la création de l'offre/demande");
        }
        ExitSuccess(["message" => "L'offre/demande a été créée.", "redirect" => "merchant.php?{$type}&targetId={$_GET['targetId']}"]);
    }

    /**
     * @param int|null $instanceId exemplaire que le VENDEUR livre pour
     *        satisfaire une demande d'achat. Une demande n'entiercit que
     *        de l'or : l'exemplaire va donc directement de la banque du
     *        vendeur à celle de l'acheteur, sans étape intermédiaire.
     */
    public function Accept(string $type, int $id, int $quantity, $player, ?int $instanceId = null): void
    {
         if ($quantity < 1) {
                $auditService = new AuditService();
                $auditService->addAuditLog("Tentative de triche bids/asks");
                ExitError("Quantité invalide");
            }
        $this->db->start_transaction('accept_bid_ask');
        try {

            $res = $this->db->get_single('items_' . $type, $id);

            if (!$res->num_rows) {
                ExitError("Aucun contrat trouvé.");
            }
            $row = $res->fetch_object();

            /* Un exemplaire ne s'achète pas par lots : l'offre part en
             * entier ou pas du tout. */
            if (!empty($row->instance_id)) {
                $quantity = 1;
            }

            if ($quantity > $row->stock) {
                ExitError("Erreur de stock");
            }
            if($row->price < 1) {
                ExitError("Prix invalide");
            }
            // total cost
            $total = $quantity * $row->price;

            if ($type == 'asks') {
                // player sells item to target and receives gold

                $target = new Player($row->player_id);

                $item = new Item($row->item_id, row: false, checked: true);

                /* Livraison d'un EXEMPLAIRE : une demande n'entiercit
                 * que de l'or, il n'y a donc pas de séquestre à
                 * dénouer — l'objet passe de la banque du vendeur à
                 * celle de l'acheteur, et c'est son propriétaire qui
                 * change. Le seuil d'état est vérifié ICI : le client
                 * ne propose que des exemplaires éligibles, mais un
                 * POST se forge. */
                if ($instanceId !== null) {

                    $quantity = 1;
                    $total = $row->price;

                    $instanceService = new ItemInstanceService();
                    $state = (new Db())->exe(
                        'SELECT ' . ItemInstanceService::WEAR_SELECT . '
                           FROM item_instances i
                           JOIN items it ON it.id = i.item_id
                           ' . ItemInstanceService::WEAR_JOIN . '
                         JOIN players_items_instances l ON l.instance_id = i.id
                         WHERE i.id = ? AND l.player_id = ? AND i.destroyed = 0',
                        array($instanceId, $player->id)
                    )->fetch_object();

                    if ($state === null) {
                        ExitError("Cet exemplaire ne vous appartient pas.");
                    }

                    if (!ItemInstanceService::meetsCondition(
                        (int) $state->durability,
                        (int) $state->durability_max,
                        (int) ($row->min_durability_pct ?? 0)
                    )) {
                        ExitError("Cet exemplaire est trop abîmé pour cette demande.");
                    }

                    $label = $instanceService->describe($instanceId);

                    try {
                        $instanceService->deliverEscrow(
                            $instanceId,
                            (int) $player->id,
                            (int) $target->id,
                            ItemInstanceService::LOCATION_BANK
                        );
                    } catch (\InvalidArgumentException $e) {
                        ExitError($e->getMessage());
                    }

                    $gold = Item::get_item_by_name('or', checked: true);
                    $gold->add_item($player, $total, bank: true);

                    $logTime = time();
                    Log::put($player, $player, "Vous avez vendu un objet.",
                        "hidden_action", "{$label} à {$row->price} Or.", $logTime);
                    Log::put($target, $target, "Un objet que vous demandiez vous a été vendu.",
                        "hidden_action", "{$label} à {$row->price} Or.", $logTime);
                } else {

                    // transfer item to target bank
                    if (!$item->give_item($player, $target, $quantity, bank: true)) {
                        ExitError("Vous n'avez pas assez de cet objet en banque.");
                    }

                    // transfer gold to player bank from market
                    $gold = Item::get_item_by_name('or', checked: true);
                    $gold->add_item($player, $total, bank: true);

                    $logTime = time();
                    $targetLog = "Vous avez vendus des objets.";
                    $objects = "{$quantity} {$item->row->name} à {$row->price} Or l'unité.";
                    Log::put($player, $player, $targetLog, "hidden_action", $objects, $logTime);

                    $targetLog = "Des objets que vous demandez vous ont été vendus.";
                    $objects = "{$quantity} {$item->row->name} à {$row->price} Or l'unité.";
                    Log::put($target, $target, $targetLog, "hidden_action", $objects, $logTime);
                }

            } elseif ($type == 'bids') {
                // player buys item from target and send gold

                // transfer gold to target bank
                $target = new Player($row->player_id);
                $gold = Item::get_item_by_name('or', checked: true);

                if (!$gold->give_item($player, $target, $total, bank: true)) {
                    ExitError("Pas assez d'Or.");
                }

                // transfer item from maket to player bank
                $item = new Item($row->item_id, row: false, checked: true);

                /* Exemplaire : c'est le SEUL geste du cycle où
                 * l'exemplaire change de propriétaire. Transfert
                 * conditionnel et atomique — deux acheteurs simultanés,
                 * un seul l'emporte, l'autre lève. Sans ça on livrerait
                 * deux fois un objet qui n'existe qu'en un exemplaire. */
                if (!empty($row->instance_id)) {

                    $instanceService = new ItemInstanceService();
                    $label = $instanceService->describe((int) $row->instance_id);

                    try {
                        $instanceService->deliverEscrow(
                            (int) $row->instance_id,
                            (int) $target->id,
                            (int) $player->id,
                            ItemInstanceService::LOCATION_MARKET
                        );
                    } catch (\InvalidArgumentException $e) {
                        ExitError($e->getMessage());
                    }

                    $logTime = time();
                    Log::put($player, $player, "Vous avez acheté un objet.",
                        "hidden_action", "{$label} à {$row->price} Or.", $logTime);
                    Log::put($target, $target, "Un objet que vous vendiez vous a été acheté.",
                        "hidden_action", "{$label} à {$row->price} Or.", $logTime);
                } else {

                    if (!$item->add_item($player, $quantity, bank: true)) {
                        ExitError("Erreur lors du transfert de l'objet depuis la banque.");
                    }
                    $logTime = time();
                    $targetLog = "Vous avez acheté des objets.";
                    $objects = "{$quantity} {$item->row->name} à {$row->price} Or l'unité.";
                    Log::put($player, $player, $targetLog, "hidden_action", $objects, $logTime);

                    $targetLog = "Des objets que vous vendez vous ont été achetés.";
                    $objects = "{$quantity} {$item->row->name} à {$row->price} Or l'unité.";
                    Log::put($target, $target, $targetLog, "hidden_action", $objects, $logTime);
                }
            }


            $sql = 'UPDATE items_' . $type . ' SET stock = stock - ? WHERE id=?';

            $this->db->exe($sql, array($quantity, $row->id));


            /* Purge SCOPÉE à l'offre réglée. Le DELETE portait sur
             * « stock = 0 » sans autre critère : il balayait les
             * contrats épuisés de TOUS les joueurs, y compris ceux
             * qu'une autre transaction venait de vider. Anodin sur des
             * piles, destructeur dès qu'un exemplaire y transite — sa
             * ligne d'offre est la seule trace de son séquestre. */
            $this->db->exe(
                'DELETE FROM items_' . $type . ' WHERE id = ? AND stock <= 0',
                array($row->id)
            );

            $this->db->commit_transaction('accept_bid_ask');

        } catch (Throwable $th) {
            $this->db->rollback_transaction('accept_bid_ask');
            ExitError("Erreur lors de l'acceptation");
        }
        ExitSuccess(["message" => "L'offre a été acceptée.", "redirect" => "merchant.php?{$type}&targetId={$_GET['targetId']}"]);
    }
}
