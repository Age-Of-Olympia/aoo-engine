<?php

use App\Service\PlantsService;
use Classes\Db;


//on recupère les triggers de type "grow" pour lesquels il n'y a pas de plants correspondant
$res = PlantsService::getTriggerGrow();

while($row = $res->fetch_object()){

    //pour chaque trigger sans plants, il y a un chance de pousser
    PlantsService::growSeed($row->params, $row->coords_id);
}

echo 'done';
