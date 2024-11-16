<?php


if(!isset($target->coords)){

    $player->get_coords();
}

$goCoords = $player->coords;

$coordsId = View::get_free_coords_id_arround($goCoords);

$target->go($goCoords);

echo $target->data->name .' est projetté!';

include('scripts/actions/on_hide_reload_view.php');
