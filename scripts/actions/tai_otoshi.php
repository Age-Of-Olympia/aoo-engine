<?php
use Classes\View;

if(!isset($target->coords)){

    $player->getCoords();
}

$goCoords = $player->coords;

$coordsId = View::get_free_coords_id_arround($goCoords);

$target->go($goCoords);

echo $target->data->name .' est projetté!';

include('scripts/actions/on_hide_reload_view.php');
