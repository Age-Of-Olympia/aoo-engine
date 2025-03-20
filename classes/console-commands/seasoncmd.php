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

        if ($action == 'cancelExchanges') {
            $this->checkEnvState($forced,0);
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
            $this->result->Log($count.'/'.sizeof($exchanges).' Exchanges anulés');
            return '';
        }

        if ($action == 'refundDeprecatedItems') {
            $this->checkEnvState($forced,1);
            
            $this->refund_deprecated_objects(true);
            $this->refund_deprecated_objects(false);
            Command::SetEnvVariable("seasoncmd",2);
            return '';
        }

        if ($action == 'convertItems') {
            $this->checkEnvState($forced,2);

            $convertionData = array(
                'adonis' => array('new_item' => "cuivre", 'mult' => 1),
                'pierre' => array('new_item' => "fer", 'mult' => 1),
                'bois_petrifie' => array('new_item' => "pierre_mana", 'mult' => 1),
                'bois' => array('new_item' => "bois_petrifie", 'mult' => 1),
            );

            foreach ($convertionData as $name => $data) {
                $this->convert_objects(true, $name, $data);
                $this->convert_objects(false, $name, $data);
            }
            Command::SetEnvVariable("seasoncmd",3);
            return '';
        }

        if ($action == 'processXP') {
            $this->checkEnvState($forced,3);
            
            $this->update_overxP_players();
         
            Command::SetEnvVariable("seasoncmd",4);
            return '';
        }


        $this->result->Error('Action : ' . $action . ' unknown');
        return '';
    }

function refund_deprecated_objects(bool $bank)
{
    $deprecatedObjects =  $this->get_deprecated_objects($bank);
    $count = 0;
    $ingredientsCount = 0;
    foreach ($deprecatedObjects as $row) {
    $player = new Player($row['player_id']);
        $item = new Item($row['item_id']);
        $item->get_data();
        $this->db->beginTransaction();
        try {

            $reciep = $item->get_recipe(true);
            if (sizeof($reciep) > 0) { // skip intentionelle des objets sans recette, ceux ci doient etre traités par convertions
                foreach ($reciep as $k => $e) {
                    $craftedWithItem = Item::get_item_by_name($k);
                    $craftedWithItem->add_item($player, $e * $row['n']);
                    $ingredientsCount += $e * $row['n'];
                }
                $item->add_item($player, -$row['n'], $bank);
                $count++;
            }

            $this->db->commit();
        } catch (Throwable $th) {
            $this->db->rollBack();
            $this->result->Log('objets remboursés :'.$count.'/'.sizeof($deprecatedObjects) .', soit ' . $ingredientsCount . ' ingredients' . (($bank) ? ' en banque' : ''));
            $this->result->Error('Erreur lors du remboursement de l\'objet:' . $row['item_id'] . ' du joueur: ' . $player->id);
            
            throw $th;
        }
    }

    $this->result->Log(' objets remboursés '.$count.'/'.sizeof($deprecatedObjects) .', soit ' . $ingredientsCount . ' ingredients' . (($bank) ? ' en banque' : ''));
    $this->result->Log('information: les objets sans recette ne sont pas remboursés, ils doivent être traités par conversion');
}
function update_overxP_players()
{
    // si un joueur as + de 3500, set son xp a 3500 etmet l'overflow dans bonus_points
    $sql = 'UPDATE players SET xp = 3500, bonus_points = bonus_points + (xp - 3500) WHERE xp > 3500';
    $res =  $this->db->executeQuery($sql);
    $this->result->Log($res->rowCount().' joueurs avec trop d\'xp convertis');
    return $res;
}

function get_deprecated_objects(bool $bank)
{
    $bankTable = ($bank) ? '_bank' : '';
    $sql = 'SELECT player_id, item_id,n FROM players_items' . $bankTable . ' INNER JOIN items ON item_id = items.id WHERE items.is_deprecated = 1';

    $res =  $this->db->fetchAllAssociative($sql);

    return $res;
}
function get_objects_by_name(bool $bank,string $name)
{
    $bankTable = ($bank) ? '_bank' : '';
    $sql = 'SELECT player_id, item_id,n FROM players_items' . $bankTable . ' INNER JOIN items ON item_id = items.id WHERE items.name = ?';

    $res =  $this->db->fetchAllAssociative($sql, array($name));

    return $res;
}
function convert_objects(bool $bank, string $name, $convertionData)
{
    $newItem = Item::get_item_by_name($convertionData['new_item']);
    $ItemstoConvert =  $this->get_objects_by_name($bank,$name);
    $countp = 0;
    $counto = 0;
    foreach ($ItemstoConvert as $row) {
        $player = new Player($row['player_id']);
        $item = new Item($row['item_id']);
        $item->get_data();
        $this->db->beginTransaction();
        try {
            $newItem->add_item($player, $row['n'] * $convertionData['mult']);
            $item->add_item($player, -$row['n'], $bank);
            $this->db->commit();
            $counto += $row['n'];
            $countp++;
        }
        catch (Throwable $th) {
            $this->db->rollBack();
            $this->result->Log($counto.' '.$name.' convertis en '.$counto * $convertionData['mult'].' '.$convertionData['new_item']. (($bank) ? ' en banque' : '').' pour '.$countp.'joueurs');
            $this->result->Error('Erreur lors de la conversion de l\'objet '.$name.' du joueur: ' . $player->id);
            throw $th;
        }
    }
    $this->result->Log($counto.' '.$name.' convertis en '.$counto * $convertionData['mult'].' '.$convertionData['new_item']. (($bank) ? ' en banque' : '').' pour '.$countp.'joueurs');
}

function checkEnvState($forced,$expectedState)
{
    if($forced) return true;
    if(Command::GetEnvVariable("seasoncmd",0)==$expectedState)return true;
    throw new Exception("Error: invalid state, utilisez les commande de saison dans un script ou assumer les consequence en utilisant --forced");
    return false;
}

}