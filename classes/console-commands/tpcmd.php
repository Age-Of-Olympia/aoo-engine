<?php

class TpCmd extends Command
{
    public function __construct() {
        parent::__construct("tp",[new Argument('mat',false), new Argument('coords',false)]);
        parent::setDescription(<<<EOT
téléporte le joueur [mat] aux coordonnées [coords] (x,y,z,plan).
Exemple:
> tp Orcrist 50,125,-5,eryn_dolen 
> tp Orcrist Sharon (tp Orcrist à côté de Sharon)
> tp everyone x,y,z,eryn_dolen (change tous le monde de plan sans changer x,y,z)
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {


        $coordsTbl = explode(',', $argumentValues[1]);

        if(is_numeric($argumentValues[1]) || count($coordsTbl) == 1){

            $player=parent::getPlayer($argumentValues[0]);
            $player->get_data();

            $target = parent::getPlayer($argumentValues[1]);
            $target->get_data();

            $goCoords = $target->get_coords();

            $coordsId = View::get_free_coords_id_arround($goCoords);
            $goCoords->coordsId = $coordsId;
            $player->go($goCoords);

            $player->refresh_view();

            return 'tp '. $player->data->name .' near '. $target->data->name .'';
        }

        if(count($coordsTbl) != 4){

            return '<font color="red">invalid coords (must be x,y,z,plan)</font>';
        }

        list($x, $y, $z, $plan) = $coordsTbl;


        $playersTbl = array();


        if($argumentValues[0] == 'everyone'){


            $admin = new Player($_SESSION['playerId']);
            $admin->get_coords();

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

            $playersTbl = array($player=parent::getPlayer($argumentValues[0]));
        }


        // clean function outputs
        ob_start();


        foreach($playersTbl as $player){


            $player->get_data();

            $player->get_coords();


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
