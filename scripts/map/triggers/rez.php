<?php


// first spawn : change avatar
if($player->coords->plan == 'gaia'){


    $item = Item::get_item_by_name('or');
    $item->add_item($player, 20);


    $item = Item::get_item_by_name('baton_marche');
    $item->add_item($player, 1);


    // if($player->data->xp == 0){
    //
    //     $player->put_xp('500');
    // }


    $player->change_avatar('1.png');
}


$raceJson = json()->decode('races', $player->data->race);


$spawnPlan = $raceJson->plan;


$goCoords = (object) array(
    'x'=>0,
    'y'=>0,
    'z'=>0,
    'plan'=>$spawnPlan
);


$coordsId = View::get_coords_id($goCoords);


$text = $player->data->name .' est arrivé sur Olympia.';

Log::put($player, $player, $text, $type="rez");
