<?php

class PlayerXpCmd extends Command
{
    public function __construct() {
        parent::__construct("player_xp", [new Argument('mat',false), new Argument('n',false)]);
        parent::setDescription(<<<EOT
Ajout d'Xp à un joueur
Exemple:
> player_xp [matricule ou nom] [nombre xp et pi]
> player_xp orcrist 20
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $player=parent::getPlayer($argumentValues[0]);

        $player->put_xp($argumentValues[1]);

        $player->get_data();

        return $argumentValues[1] .'Xp et Pi ajoutés à '. $player->data->name;

    }
}
