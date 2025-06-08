<?php
use Classes\Command;
use Classes\Argument;

class OptionCmd extends Command
{
    public function __construct() {
        parent::__construct("option", [new Argument('mat',false),new Argument('option',false)]);
        parent::setDescription(<<<EOT
Ajout ou suppression d'une option à un joueur (si il a l'option, ça lui enlève s'il ne l'a pas ça a ajoute).
Exemple:
> option [matricule ou nom] [nom option]
> option 1 isMerchant
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $player=parent::getPlayer($argumentValues[0]);
        $player->get_data();

        if ( $argumentValues[1] == 'isSuperAdmin'){
            include $_SERVER['DOCUMENT_ROOT'].'/checks/super-admin-check.php';
        }

        if($player->have('options', $argumentValues[1])){


            $player->end_option($argumentValues[1]);

            return 'Option '. $argumentValues[1] .' enlevé à '. $player->data->name .'';
        }

        else{

            $player->add_option($argumentValues[1]);

            return 'Option '. $argumentValues[1] .' ajouté à '. $player->data->name .'';
        }

    }
}
