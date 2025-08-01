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

    public function Create(string $type, int $itemId, int $price, int $quantity, $player): void
    {
        $item = new Item($itemId, row: false, checked: true);
        $item->get_data();

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
                if ($item->add_item($player, -$quantity, bank: true)) {
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

    public function Accept(string $type, int $id, int $quantity, $player): void
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

                // transfer item to target bank
                if (!$item->give_item($player, $target, $quantity, bank: true)) {
                    ExitError("Pas assez de cet objet.");
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


            $sql = 'UPDATE items_' . $type . ' SET stock = stock - ? WHERE id=?';

            $this->db->exe($sql, array($quantity, $row->id));


            $values = array('stock' => 0);
            $this->db->delete('items_' . $type, $values);

            $this->db->commit_transaction('accept_bid_ask');

        } catch (Throwable $th) {
            $this->db->rollback_transaction('accept_bid_ask');
            ExitError("Erreur lors de l'acceptation");
        }
        ExitSuccess(["message" => "L'offre a été acceptée.", "redirect" => "merchant.php?{$type}&targetId={$_GET['targetId']}"]);
    }
}
