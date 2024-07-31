<?php


$raceJson = json()->decode('races', $player->data->race);


$player->end_action('tuto/attaquer');


foreach($raceJson->actions as $e){

    $player->add_action($e);
}


$goCoords = $player->coords;

$goCoords->plan = 'gaia2';

$player->go($goCoords);


include('scripts/actions/on_hide_reload_view.php');
