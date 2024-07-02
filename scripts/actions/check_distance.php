<?php

if($distance > $player->caracs->p){

    exit('La cible est hors de votre Perception.');
}


if(!empty($actionJson->distanceMin)){


    if($distance < $actionJson->distanceMin){

        exit('Vous n\'êtes pas à bonne distance.');
    }
}

if(!empty($actionJson->distanceMax)){


    if($distance > $actionJson->distanceMax){

        exit('Vous n\'êtes pas à bonne distance.');
    }
}

if($player->coords->z < 0 && $distance > 2){

    exit('Dans les souterrains, la portée maximum est de 2 cases.');
}



if(!empty($actionJson->useEmplacement)){


    $emplacement = $actionJson->useEmplacement;


    if($player->$emplacement->data->subtype == 'melee' && $distance > 1){

        exit('Vous n\'êtes pas à bonne distance (arme de mêlée).');
    }
    elseif($player->$emplacement->data->subtype == 'jet' && $distance < 2){

        exit('Vous n\'êtes pas à bonne distance (arme de jet).');
    }
    elseif($player->$emplacement->data->subtype == 'tir' ){


        if($distance < 2){

            exit('Vous n\'êtes pas à bonne distance (arme de tir).');
        }


        if(!$munition = $player->get_munition($player->$emplacement, $equiped=true)){

            exit('Vous devez équiper une munition.');
        }
    }
}
