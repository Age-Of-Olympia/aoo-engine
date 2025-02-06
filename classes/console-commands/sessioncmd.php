<?php

class SessionCmd extends Command
{
    public function __construct() {
        parent::__construct("session",[new Argument('action',false), new Argument('mat',true)]);
        parent::setDescription(<<<EOT
open: permet de se connecter au compte d'un personnage (sans login)
destroy: ferme la session (logout)
Exemple:
> session open Orcrist
> session destroy 
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        // OPEN
        if($argumentValues[0] == 'open'){


            $login = $argumentValues[1];

            if(!is_numeric($login)){


                $player = Player::get_player_by_name($login);
            }
            else{


                $player = new Player($login);
            }

            $player->get_data();

            if($player->have('options','isSuperAdmin') && $player->id != $_SESSION['originalPlayerId'] ){
                include $_SERVER['DOCUMENT_ROOT'].'/checks/super-admin-check.php';
            }

            $_SESSION['playerId'] = $_SESSION['mainPlayerId'] = $player->id;
            if(isset($argumentValues[2]) && $argumentValues[2] == '-reactive'){
               unset($_SESSION['nonewturn']);
            }
            else{
                $_SESSION['nonewturn'] = true;
            }

            return 'Session ouverte pour joueur '. $player->data->name .'.';
        }

        // DESTROY
        if($argumentValues[0] == 'destroy'){

            unset($_SESSION['mainPlayerId']);
            unset($_SESSION['playerId']);
            unset($_SESSION['nonewturn']);
            session_destroy();

            return 'session destroyed';
        }

        return 'unknown action, nothing done';
    }
}
