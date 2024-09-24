<?php

class MissiveCmd extends Command
{
    public function __construct() {
        parent::__construct("missive", [new Argument('mat',false),new Argument('missive',false)]);
        parent::setDescription(<<<EOT
Ajoute un personnage en tant que destinataire d'une missive.
Exemple:
> missive Orcrist 1725546204
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $player=parent::getPlayer($argumentValues[0]);
        $player->get_data();

        ob_start();


        $topJson = json()->decode('forum/topics', $argumentValues[1]);

        //Check missive exists and decoded
        if(!$topJson){
            return '<font color="red">Unknown missive</font>';
        }

        $db = new Db();

        $sql = 'SELECT COUNT(*) AS n FROM players_forum_missives WHERE player_id = ? AND name = ?';

        $res = $db->exe($sql, array($player->id, $topJson->name));

        $row = $res->fetch_object();

        if($row->n){

            echo $player->data->name .' est déjà destinataire de cette missive.';
        }
        else{


            $values = array('player_id'=>$player->id, 'name'=>$topJson->name);

            $db->insert('players_forum_missives', $values);

            echo $player->data->name .' ajouté en tant que destinataire de la missive: '. $topJson->title .'.';
        }

        return ob_get_clean();

    }
}
