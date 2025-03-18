<?php


class ItemCmd extends AdminCommand
{
    public function __construct()
    {
        parent::__construct("season", [new Argument('action', false)]);
        parent::setDescription(<<<EOT
outils inter saison : 
ordre recommandé :
- season cancelExchanges
- season refundDeprecatedItems
- season convertItems
EOT);
    }

    public function execute(array $argumentValues): string
    {
        $action = $argumentValues[0];
        if ($action == 'cancelExchanges') {
            $count = 0;
            $exchanges = Exchange::get_all_open_exchanges();
            foreach ($exchanges as $exchange) {
                $exchange->db->start_transaction('cancel_exchange');
                try {
                    $offeringPlayer = new Player($exchange->playerId);
                    $targetPlayer = new Player($exchange->targetId);
                    //refund items
                    $exchange->give_items(from_player: $offeringPlayer, to_player: $offeringPlayer);
                    $exchange->give_items(from_player: $targetPlayer, to_player: $targetPlayer);

                    $exchange->cancel_exchange();
                } catch (Throwable $th) {
                    $exchange->db->rollback_transaction('cancel_exchange');
                    exit($count . ' Exchanges anulés + Erreur lors de l\'annulation de l\'échange:' . $exchange->id);
                }
                $exchange->db->commit_transaction('cancel_exchange');
                $count++;
            }

            return $count . ' Exchanges anulés';
        }

        if ($action == 'refundDeprecatedItems') {

            $result = refund_deprecated_objects(true);
            $result += refund_deprecated_objects(false);
            return $result;
        }

        if ($action == 'convertItems') {
            $convertionData = array(
                //'rocher' => array('new_item' => "caillou", 'mult' => 3),
                'adonis' => array('new_item' => "cuivre", 'mult' => 1),
                'pierres' => array('new_item' => "fer", 'mult' => 1),
                'bois P' => array('new_item' => "pierres de mana", 'mult' => 1),
                'bois' => array('new_item' => "bois P", 'mult' => 1),
            );
            $result = '';
            foreach ($convertionData as $name => $data) {
                $result += convert_objects(true, $name, $data);
                $result += convert_objects(false, $name, $data);
            }
            return $result;
        }


        return '<font color="orange">Action : ' . $action . ' unknown</font>';
    }
}
function refund_deprecated_objects(bool $bank)
{
    $deprecatedObjects = get_deprecated_objects($bank);
    $count = 0;
    $ingredientsCount = 0;
    foreach ($deprecatedObjects as $object) {
        $player = new Player($object->player_id);
        $item = new Item($object->item_id);
        $item->get_data();
        $player->db->start_transaction('refund_deprecated_objects');
        try {

            $reciep = $item->get_recipe(true);
            if (sizeof($reciep) > 0) { // skip intentionelle des objets sans recette, ceux ci doient etre traités par convertions
                foreach ($reciep as $k => $e) {
                    $craftedWithItem = Item::get_item_by_name($k);
                    $craftedWithItem->add_item($player, $e * $object->n);
                    $ingredientsCount += $e * $object->n;
                }
                $item->add_item($player, -$object->n, $bank);
                $count++;
            }

            $player->db->commit_transaction('refund_deprecated_objects');
        } catch (Throwable $th) {
            $player->db->rollback_transaction('refund_deprecated_objects');
            exit('objets remboursés'.$count.'/'.sizeof($deprecatedObjects) .', soit' . $ingredientsCount . 'ingredients' . (($bank) ? ' en banque' : '').'+ Erreur lors du remboursement de l\'objet:' . $object->id . ' du joueur: ' . $player->id);
        }
    }

    return ' objets remboursés'.$count.'/'.sizeof($deprecatedObjects) .', soit' . $ingredientsCount . 'ingredients' . (($bank) ? ' en banque' : '');
}
function get_deprecated_objects(bool $bank)
{
    $return = array();
    $bankTable = ($bank) ? '_bank' : '';
    $sql = 'SELECT player_id, item_id,n FROM players_items' . $bankTable . ' INNER JOIN items ON item_id = items.id WHERE items.is_deprecated = 1';


    $db = new Db();
    $res = $db->exe($sql);

    while ($row = $res->fetch_object()) {
        $return[] = $row;
    }

    return $return;
}
function get_objects_by_name(bool $bank,string $name)
{
    $return = array();
    $bankTable = ($bank) ? '_bank' : '';
    $sql = 'SELECT player_id, item_id,n FROM players_items' . $bankTable . ' INNER JOIN items ON item_id = items.id WHERE items.name = ?';

    $db = new Db();
    $res = $db->exe($sql, array($name));

    while ($row = $res->fetch_object()) {
        $return[] = $row;
    }

    return $return;
}
function convert_objects(bool $bank, string $name, $convertionData)
{
    $newItem = Item::get_item_by_name($convertionData['new_item']);
    $ItemstoConvert = get_objects_by_name($bank,$name);
    $count = 0;
    foreach ($ItemstoConvert as $object) {
        $player = new Player($object->player_id);
        $item = new Item($object->item_id);
        $item->get_data();
        $player->db->start_transaction('convert_objects');
        try {
            $newItem->add_item($player, $object->n * $convertionData['mult']);
            $item->add_item($player, -$object->n, $bank);
            $player->db->commit_transaction('convert_objects');
            $count++;
        }
        catch (Throwable $th) {
            $player->db->rollback_transaction('convert_objects');
            exit($name.'objets convertis en'.$convertionData['new_item'].':'.$count.'/'.sizeof($ItemstoConvert). (($bank) ? ' en banque' : '').'+ Erreur lors du remboursement de l\'objet:' . $object->id . ' du joueur: ' . $player->id);
        }
    }
}