<?php
use Classes\Command;
use Classes\Argument;
use Classes\Db;
use Classes\Player;
use Classes\View;

class TpCmd extends Command
{
    public function __construct() {
        parent::__construct("tp",[new Argument('action',true), new Argument('target',true)]);
        parent::setDescription(<<<EOT
Usage:
> tp <player_name> <x,y,z,plan> (téléporte le joueur aux coordonnées)
> tp <player_name> <other_player> (téléporte à côté d'un autre joueur)
> tp everyone <x,y,z,plan> (change tous le monde de plan)
> tp list-plans (affiche tous les plans disponibles)
EOT);
    }

    public function execute(array $argumentValues) : string
    {
        if (empty($argumentValues[0])) {
            return $this->getDescription();
        }

        if ($argumentValues[0] === 'list-plans') {
            $db = new Db();
            $sql = 'SELECT DISTINCT plan FROM `coords` ORDER BY plan';
            $res = $db->exe($sql);

            $plans = [];
            while ($row = $res->fetch_object()) {
                $plans[] = $row->plan;
            }
            return "Plans disponibles: " . implode(", ", $plans);
        }
        if (empty($argumentValues[1])) {
            return '<font color="red">target coordinates or player name required</font>';
        }

        $coordsTbl = explode(',', $argumentValues[1]);

        if(is_numeric($argumentValues[1]) || count($coordsTbl) == 1){

            $player=parent::getPlayer($argumentValues[0]);
            $player->get_data();

            $target = parent::getPlayer($argumentValues[1]);
            $target->get_data();

            $goCoords = $target->getCoords();

            $coordsId = View::get_free_coords_id_arround($goCoords);
            $goCoords->coordsId = $coordsId;
            $player->go($goCoords);

            $player->refresh_view();

            return 'tp '. $player->data->name .' near '. $target->data->name .'';
        }

        $playersTbl = array();

        if($argumentValues[0] == 'everyone'){


            $admin = new Player($_SESSION['playerId']);
            $admin->getCoords();

            $db = new Db();

            $sql = '
            SELECT p.id
            FROM players AS p
            INNER JOIN coords AS c
            ON p.coords_id = c.id
            WHERE c.plan = ?
            ';

            $res = $db->exe($sql, $admin->coords->plan);

            while($row = $res->fetch_object()){


                $playersTbl[] = new Player($row->id);
            }
        }
        else{
            $player=parent::getPlayer($argumentValues[0]);
            $player->getCoords();
            //allow tp of sigle player with no Z or plan 
            if(!isset($coordsTbl[2])){
               
                $coordsTbl[2] = $player->coords->z;
            }
            if(!isset($coordsTbl[3])){
                $coordsTbl[3] = $player->coords->plan;
            }
            $playersTbl = array($player);
        }

         if(count($coordsTbl) != 4){

            return '<font color="red">invalid coords (must be x,y,z,plan)</font>';
        }

        list($x, $y, $z, $plan) = $coordsTbl;

        // clean function outputs
        ob_start();


        foreach($playersTbl as $player){


            $player->get_data(false);
            $player->getCoords(false);


            $goX = (!is_numeric($x)) ? $player->coords->x : $x;
            $goY = (!is_numeric($z)) ? $player->coords->y : $y;
            $goZ = (!is_numeric($z)) ? $player->coords->z : $z;


            $coords = (object) array(
                'x'=>$goX,
                'y'=>$goY,
                'z'=>$goZ,
                'plan'=>$plan
            );


            $player->go($coords);

            $player->refresh_view();

            echo 'tp '. $player->data->name .' to '. $goX .','. $goY .','. $goZ .','. $plan .'<br />';
        }


        return ob_get_clean();
    }
}
