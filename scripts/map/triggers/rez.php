<?php
use Classes\Item;
use Classes\Log;
use Classes\View;
Use App\Service\PlayerEffectService;

// first spawn : change avatar
if($player->coords->plan == 'gaia2'){


    $item = Item::get_item_by_name('or');
    $item->add_item($player, 20);


    $item = Item::get_item_by_name('baton_marche');
    $item->add_item($player, 1);


    $playerEffectService = new PlayerEffectService();
    $playerEffectService->removeAllEffectsForPlayer($player->id);

    $player->change_avatar('1.png');
}


$factionJson = json()->decode('factions', $player->data->faction);


$spawnPlan = $factionJson->respawnPlan??"olympia";


$goCoords = (object) array(
    'x'=>0,
    'y'=>0,
    'z'=>0,
    'plan'=>$spawnPlan
);


$coordsId = View::get_free_coords_id_arround($goCoords);

$player->coords->plan = $spawnPlan;

$text = $player->data->name .' est arrivé sur Olympia.';

Log::put($player, $player, $text, type:"rez");
