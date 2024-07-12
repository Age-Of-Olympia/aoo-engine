<?php

class PlayerItemCmd extends Command
{
    public function __construct() {
        parent::__construct("player_item", [new Argument('action',false),new Argument('mat',false),
            new Argument('item_name',false),  new Argument('n',true)]);
        parent::setDescription(<<<EOT
Gestion objets d'un joueur.
add: ajouter un objet à un joueur
Exemple:
> player-item add [matricule ou nom] [id objet ou nom objet] [nombre d'objet, par défaut 1]
> player-item add 1 pierre 10
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $action = $argumentValues[0];
        if($action == 'add'){
            $player=parent::getPlayer($argumentValues[1]);

            $player->get_data();

            if(is_numeric($argumentValues[2])){

                $item = new Item($argumentValues[2]);
            }
            else{

                $item = Item::get_item_by_name($argumentValues[2]);
            }

            $item->add_item($player, $argumentValues[3] ?? 1);

            return 'Item '. $argumentValues[2] .' ajouté à '. $player->get_data()->name .')';
        }



        return '<font color="orange">Action : '.$action.' unknown</font>';
    }
}
