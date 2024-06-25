<?php


$coordsTbl = explode(',', $params);

$coords = (object) array();

$coords->x = (is_numeric($coordsTbl[0])) ? $coordsTbl[0] : $goCoords->x;
$coords->y = (is_numeric($coordsTbl[1])) ? $coordsTbl[1] : $goCoords->y;
$coords->z = (is_numeric($coordsTbl[2])) ? $coordsTbl[2] : $goCoords->z;
$coords->plan = ($coordsTbl[3] != 'plan') ? $coordsTbl[3] : $goCoords->plan;

$goCoords = $coords;


$p = 1;

$coordsArround = View::get_coords_arround($coords, $p);


$coordsTaken = View::get_coords_taken($coords);


$coordsArround = array_diff($coordsArround, $coordsTaken);


while(true){


    if(!count($coordsArround)){

        $p++;

        $coordsArround = View::get_coords_arround($coords, $p);

        $coordsArround = array_diff($coordsArround, $coordsTaken);
    }


    shuffle($coordsArround);

    $randCoords = array_pop($coordsArround);


    $goCoords->x = explode(',', $randCoords)[0];
    $goCoords->y = explode(',', $randCoords)[1];

    break;
}


$coordsId = View::get_coords_id($goCoords);
