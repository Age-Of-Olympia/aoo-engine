<?php


class SeasonCmd extends AdminCommand
{
    public function __construct()
    {
        parent::__construct("season", [new Argument('action', false),new Argument('force', true)]);
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
        $forced = isset($argumentValues[1]) && $argumentValues[1]=="--forced";
        $result = '';
        if ($action == 'cancelExchanges') {
            if(!$this->checkEnvState($forced,0,$result)) return $result;
            $count = 0;
            $exchanges = Exchange::get_all_open_exchanges();
            foreach ($exchanges as $exchange) {
                $exchange->db->beginTransaction();
                try {
                    $offeringPlayer = new Player($exchange->playerId);
                    $targetPlayer = new Player($exchange->targetId);
                    //refund items
                    $exchange->give_items(from_player: $offeringPlayer, to_player: $offeringPlayer);
                    $exchange->give_items(from_player: $targetPlayer, to_player: $targetPlayer);

                    $exchange->cancel_exchange();
                    $exchange->db->commit();

                } catch (Throwable $th) {
                    $exchange->db->rollBack();
                    throw new Exception($th->getMessage().'<br>'.($count.'/'.sizeof($exchanges).' Exchanges anulés + Erreur lors de l\'annulation de l\'échange:' . $exchange->id));
                }
                $count++;
            }
            Command::SetEnvVariable("seasoncmd",1);
            return $count.'/'.sizeof($exchanges).' Exchanges anulés';
        }

        if ($action == 'refundDeprecatedItems') {
            if(!$this->checkEnvState($forced,1,$result)) return $result;
            
            $result =  $this->refund_deprecated_objects(true);
            $result +=  $this->refund_deprecated_objects(false);
            Command::SetEnvVariable("seasoncmd",2);
            return $result;
        }

        if ($action == 'convertItems') {
            if(!$this->checkEnvState($forced,2,$result)) return $result;

            $convertionData = array(
                //'rocher' => array('new_item' => "caillou", 'mult' => 3),
                'adonis' => array('new_item' => "cuivre", 'mult' => 1),
                'pierres' => array('new_item' => "fer", 'mult' => 1),
                'bois P' => array('new_item' => "pierres de mana", 'mult' => 1),
                'bois' => array('new_item' => "bois P", 'mult' => 1),
            );
            $result = '';
            foreach ($convertionData as $name => $data) {
                $result +=  $this->convert_objects(true, $name, $data);
                $result +=  $this->convert_objects(false, $name, $data);
            }
            Command::SetEnvVariable("seasoncmd",3);
            return $result;
        }


        $this->result->Error('Action : ' . $action . ' unknown');
    }

function refund_deprecated_objects(bool $bank)
{
    $deprecatedObjects =  $this->get_deprecated_objects($bank);
    $count = 0;
    $ingredientsCount = 0;
    foreach ($deprecatedObjects as $object) {
        $player = new Player($object->player_id);
        $item = new Item($object->item_id);
        $item->get_data();
        $player->db->beginTransaction();
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

            $player->db->commit();
        } catch (Throwable $th) {
            $player->db->rollBack();
            throw new Exception($th->getMessage().'<br>'.'objets remboursés'.$count.'/'.sizeof($deprecatedObjects) .', soit' . $ingredientsCount . 'ingredients' . (($bank) ? ' en banque' : '').'+ Erreur lors du remboursement de l\'objet:' . $object->id . ' du joueur: ' . $player->id);
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
    $ItemstoConvert =  $this->get_objects_by_name($bank,$name);
    $count = 0;
    foreach ($ItemstoConvert as $object) {
        $player = new Player($object->player_id);
        $item = new Item($object->item_id);
        $item->get_data();
        $player->db->beginTransaction();
        try {
            $newItem->add_item($player, $object->n * $convertionData['mult']);
            $item->add_item($player, -$object->n, $bank);
            $player->db->commit();
            $count++;
        }
        catch (Throwable $th) {
            $player->db->rollBack();
            throw new Exception($th->getMessage().'<br>'.$name.'objets convertis en'.$convertionData['new_item'].':'.$count.'/'.sizeof($ItemstoConvert). (($bank) ? ' en banque' : '').'+ Erreur lors du remboursement de l\'objet:' . $object->id . ' du joueur: ' . $player->id);
        }
    }
}

function checkEnvState($forced,$expectedState, &$result)
{
    if($forced) return true;
    if(Command::GetEnvVariable("seasoncmd",0)==$expectedState)return true;
    $result="Error:invalid state, utilisez les commande de saison dans un script ou assumer les consequence en utilisant --forced";
    return false;
}

}