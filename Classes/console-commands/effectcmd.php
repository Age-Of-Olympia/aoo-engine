<?php
use Classes\Command;
use Classes\Argument;

class EffectCmd extends Command
{
    public function __construct() {
        parent::__construct("effect", [new Argument('mat',false),new Argument('effect',false), new Argument('secondes',true)]);
        parent::setDescription(<<<EOT
Ajout ou suppression d'un effet à un joueur (si il a l'option, ça lui enlève s'il ne l'a pas ça a ajoute).
Exemple:
> effect [matricule ou nom] [nom effet] [secondes]
> effect 1 adrenaline
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $player=parent::getPlayer($argumentValues[0]);
        $player->get_data();

        if($player->have('effects', $argumentValues[1])){


            $player->endEffect($argumentValues[1]);

            return 'Effet '. $argumentValues[1] .' enlevé à '. $player->data->name .'';
        }

        else{
            if (isset($argumentValues[2]) && is_numeric($argumentValues[2])) {
                $duration = (int)$argumentValues[2];
            } else {
                $duration = 0; // Default duration if not specified
            }
            $player->addEffect($argumentValues[1],$duration);

            return 'Effet '. $argumentValues[1] .' ajouté à '. $player->data->name .'';
        }

    }
}
