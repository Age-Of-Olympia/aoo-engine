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
> action 1 pack [+/-race]
> action 1 startpack [+/-race]
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        if($argumentValues[1] == 'startpack'){


            ob_start();

            $player=parent::getPlayer($argumentValues[0]);
            $player->get_data();
            $raceJson = json()->decode('races', $player->data->race);

            echo 'delete all actions for '. $player->data->name .'...<br />';

            $db = new Db();
            $values = array('player_id'=>$player->id);
            $db->delete('players_actions', $values);

            echo 'done!';

            echo 'ajout du pack de base de '. $raceJson->name .'...<br />';

            foreach($raceJson->actions as $e){


                echo $e .': ';

                $player->add_action($e);

                echo 'done!<br />';
            }

            echo '<font color="lime">startpack successfully updated!</font>';

            return ob_get_clean();
        }

        if($argumentValues[1] == 'pack'){


            ob_start();

            $player=parent::getPlayer($argumentValues[0]);
            $player->get_data();

            $race = (!empty($argumentValues[2])) ? $argumentValues[2] : $player->data->race;

            $raceJson = json()->decode('races', $race);

            echo 'delete all actions for '. $player->data->name .'...<br />';

            $db = new Db();
            $values = array('player_id'=>$player->id);
            $db->delete('players_actions', $values);

            echo 'done!';

            echo 'ajout du pack de sort '. $raceJson->name .'...<br />';

            foreach($raceJson->actionsPack as $e){


                echo $e .': ';

                $player->add_action($e);

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

            return 'Action '. $argumentValues[1] .' ajouté à '. $player->data->name .'';
        }

    }
}
