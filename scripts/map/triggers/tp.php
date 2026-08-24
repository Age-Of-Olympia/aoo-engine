<?php
use App\Service\Map\TriggerRequirements;
use Classes\View;

/* params: "x,y,z,plan[,condition]". The explode limit keeps the condition
   whole, commas included. */
$coordsTbl = explode(',', $params, 5);

$need = trim((string) ($coordsTbl[4] ?? ''));

if ($need !== '' && !TriggerRequirements::met($player, $need)) {

    echo '<script>alert("'. TriggerRequirements::REFUSAL .'");</script>';

    exit();
}

$coords = (object) array();

$coords->x = (is_numeric($coordsTbl[0])) ? $coordsTbl[0] : $goCoords->x;
$coords->y = (is_numeric($coordsTbl[1])) ? $coordsTbl[1] : $goCoords->y;
$coords->z = (is_numeric($coordsTbl[2])) ? $coordsTbl[2] : $goCoords->z;
$coords->plan = ($coordsTbl[3] != 'plan') ? $coordsTbl[3] : $goCoords->plan;

$goCoords = $coords;

$coordsId = View::get_free_coords_id_arround($goCoords);


View::refresh_players_svg($player->coords);
