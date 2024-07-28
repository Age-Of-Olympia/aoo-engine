<?php

class ActionCmd extends Command
{
    public function __construct() {
        parent::__construct("action", [new Argument('mat',false),new Argument('option',false)]);
        parent::setDescription(<<<EOT
Ajout ou suppression d'une action à un joueur (si il a l'action, ça lui enlève s'il ne l'a pas ça a ajoute).
Exemple:
> action [matricule ou nom] [nom option]
> action 1 soins/imposition_des_mains
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        if($argumentValues[0] == 'pack'){


            ob_start();

            $admin = new Player($_SESSION['playerId']);
            $admin->get_data();
            $raceJson = json()->decode('races', $argumentValues[1]);

            echo 'delete all actions for '. $admin->data->name .'...<br />';

            $db = new Db();
            $values = array('player_id'=>$admin->id);
            $db->delete('players_actions', $values);

            echo 'done!';

            echo 'ajout du pack de sort '. $raceJson->name .'...<br />';

            foreach($raceJson->actionsPack as $e){


                echo $e .': ';

                $admin->add_action($e);

                echo 'done!<br />';
            }

            echo '<font color="lime">pack successfully updated!</font>';

            return ob_get_clean();
        }

        $player=parent::getPlayer($argumentValues[0]);
        $player->get_data();

        if($player->have('actions', $argumentValues[1])){


            $player->end_action($argumentValues[1]);

            return 'Action '. $argumentValues[1] .' enlevé à '. $player->data->name .'';
        }

        else{

            $player->add_action($argumentValues[1]);

            return 'Actoin '. $argumentValues[1] .' ajouté à '. $player->data->name .'';
        }

    }
}
