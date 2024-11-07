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


// enfers
if($player->coords->plan == 'enfers'){

    exit('Impossible d\'attaquer aux Enfers.');
}
