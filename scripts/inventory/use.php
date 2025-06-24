<?php

use App\Enum\EquipResult;
use Classes\Item;
use Classes\Log;

$player->get_data();


$item = new Item($_POST['itemId']);
$item->get_data();


$text = $player->data->name .' a utilisé '. $item->data->name .'.';


if(!empty($item->data->emplacement)){


    $return = $player->equip($item);

    if($return == EquipResult::Equip){

        if($player->getRemaining('ae') < 1){


            // undo equip
            $player->equip($item);

            exit('error ae');
        }

        $text = $player->data->name .' a équipé '. $item->data->name .'.';

        $ae = 1;
    }

    elseif($return == EquipResult::Unequip){

        $text = $player->data->name .' a déséquipé '. $item->data->name .'.';
    }
}

elseif($item->row->spell != ''){


    if($player->getRemaining('ae') < 1){

        exit('error ae');
    }

    $raceJson = json()->decode('races', $player->data->race);

    $charges = false;

    if(!in_array($item->row->spell, $raceJson->spells)){

        $charges = 1;
    }


    if(!$item->add_item($player, -1)){

        exit('error add item');
    }

    $player->add_action($item->row->spell, $charges);


    $text = $player->data->name .' a lu '. $item->data->name .'.';

    $ae = 1;
}
elseif($item->data->type == 'consommable'){
    //cas des objets consommables :
    //coûte 1A pour être consommés

    //On verifie que le joueur a assez d'action
    if($player->getRemaining('a') < 1){

        exit('error a');
    }

    //ajout des bonus de l'objet consommé
    foreach($item->data as $bonus => $qte){

        switch ($bonus) {
            case "pv":
            case "pm":
            case "mvt":
            case "a":
            case "ae":
                $player->putBonus([$bonus=>$qte], false);
            break;
        
            case "malus":
                $player->put_malus($qte);
                break;

            case "pr":
                $player->put_pr($qte);
                break;

            case "pf":
                $player->put_pf($qte);
                break;

            case "effet":
                //dans le json de l'objet, les effet sont dans un tableau du type ["-sang","poison"]
                foreach($qte as $effet){
                    //supression d'un effet
                    if(str_starts_with($effet, '-')){ 

                        $player->endEffect(str_replace("-","",$effet));

                    }
                    //ajout d'un effet
                    else { 
                        if(in_array($effet, EFFECTS_HIDDEN) || $effet == "poison" || $effet == "poison_magique"){

                            $player->addEffect($effet, 0);

                        }
                        else {

                            $player->addEffect($effet, ONE_DAY);
                            
                        }

                    }
                }
                break;
        }

    }

    //on enlève l'action utilisée
    $player->putBonus(array('a'=>-1));

    //on enlève un exemplaire de l'objet
    $item->add_item($player, -1);

    //coût en Ae à 0
    $ae = 0;

}


// use ae
if(!empty($ae)){

    $player->putBonus(array('ae'=>-$ae));
}


Log::put($player, $player, $text, type:'use');
