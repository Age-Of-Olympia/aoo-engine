<?php
use App\Service\Map\TriggerRequirements;
use Classes\View;

/* params : « x,y,z,plan » et, depuis que la couche des déclencheurs se peint,
   une condition facultative en cinquième position. Un `need` et un `tp` sur
   la même case faisaient une porte gardée ; une case ne portant qu'une tuile,
   le tp porte la condition lui-même. La limite de explode() garde les virgules
   de la condition : « item:clef:1,spell:feu » reste d'un seul tenant. */
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
