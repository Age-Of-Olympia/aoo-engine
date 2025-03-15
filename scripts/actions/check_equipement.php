<?php

if(!empty($actionJson->useEmplacement)){


    $emplacement = $actionJson->useEmplacement;


    if($player->emplacements->{$emplacement}->data->subtype == 'melee' && $distance > 1){

        exit('Vous n\'êtes pas à bonne distance (arme de mêlée).');
    }
    elseif($player->emplacements->{$emplacement}->data->subtype == 'jet' && $distance < 2){

        exit('Vous n\'êtes pas à bonne distance (arme de jet).');
    }
    elseif($player->emplacements->{$emplacement}->data->subtype == 'tir' ){


        if($distance < 2){

            exit('Vous n\'êtes pas à bonne distance (arme de tir).');
        }


        // if(!$munition = $player->get_munition($player->emplacements->{$emplacement}, $equiped=true)){

        //     exit('Vous devez équiper une munition.');
        // }
    }
}


if(!empty($actionJson->itemConditions)){


    foreach($actionJson->itemConditions as $e){


        if(!isset($player->emplacements->{$e->emplacement})){

            exit('Cette action nécessite un équipement particulier.');
        }


        $item = $player->emplacements->{$e->emplacement};

        $item->get_data();


        if($e->condition == 'craftedWith'){


            // search if $item is crafted with $e->value
            if(!$item->is_crafted_with($e->value)){

                exit('Cette action nécessite un équipement particulier.');
            }
        }

        elseif(!empty($item->data->{$e->condition})){


            if($item->data->{$e->condition} != $e->value){

                exit('Cette action nécessite un équipement particulier.');
            }
        }
    }
}


if(!empty($actionJson->spellMalus)){

    $blocked = false;
    foreach(ITEM_EMPLACEMENT_FORMAT as $emp){


        if(!empty($player->emplacements->{$emp})){


            if(!empty($player->emplacements->{$emp}->data->spellMalus)){

                $blocked = true;
                echo '<font color="red">'. $player->emplacements->{$emp}->data->name .' empêche la magie.</font> ';
            }
        }
    }
    if($blocked){
        exit();
    }
}
