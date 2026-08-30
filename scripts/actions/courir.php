<?php
$mvt = 1;

// pouvoir divin
$pouvoir = '';

if($player->data->godId == '4'){

    if($player->data->pf > 0){

        $mvt += 1;

        $player->put_pf(-1);

        $pouvoir = '+1 (pouvoir d\'Hermès)';
    }
}

// route
$route = '';

if ((new \App\Service\Map\RoadService())->hasRoadAt((int) $player->data->coords_id)) {

    $route = '+1 (route)';

    $mvt += 1;
}

$bonus = array('a'=>-1, 'mvt'=>$mvt);

echo '
Vous courez et gagnez '. $mvt .' Mouvements.

<div class="action-details">+'. 1 .' '. $route .' '. $pouvoir .'</div>
';


$playerXp = 1;
