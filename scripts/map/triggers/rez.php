<?php
use Classes\Log;
use Classes\View;

// The old gaia2 first-spawn branch (starter gold/stick/avatar + effect
// reset) was retired with the legacy tutorial. The new tutorial grants the
// starter pack via TutorialHelper::grantStarterPack() in the
// complete/skip/cancel endpoints. This trigger now only handles the
// faction-respawn teleport (underworld exit).

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
