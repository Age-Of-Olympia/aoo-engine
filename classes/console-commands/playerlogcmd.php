<?php

class PlayerLogCmd extends Command
{
    public function __construct() {
        parent::__construct("player_log", [new Argument('mat',false),new Argument('target_mat',false)]);
        parent::setDescription(<<<EOT
Ajout d'un log à un joueur
Exemple:
> player_log [matricule ou nom] [matricule ou nom perso cible] [.... reste du log]
> player_log orcrist leyrion Envoi une boule de neige droit dans le visage.
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $player=parent::getPlayer($argumentValues[0]);
        $target=parent::getPlayer($argumentValues[1]);

        unset($argumentValues[0]);
        unset($argumentValues[1]);

        $target->get_data();
        $player->get_data();

        $text = implode(' ', $argumentValues);

        Log::put($player, $target, $text);

        return  'Evénement de '.$player->data->name. ' à '.$target->data->name .' '. $text ;

    }
}
