<?php

require_once('config.php');


if(!isset($_POST['coords'])){

    exit('error coords');
}


$coords = explode(',', $_POST['coords']);


$player = new Player($_SESSION['playerId']);

$player->get_coords();


$goCoords = (object) array(
    'x'=>$coords[0],
    'y'=>$coords[1],
    'z'=>$player->coords->z,
    'plan'=>$player->coords->plan
);


if(!is_numeric($goCoords->x) || !is_numeric($goCoords->y)){

    exit('error coords numeric');
}


$coordsId = View::get_coords_id($goCoords);


// trigger
$sql = '
SELECT * FROM map_triggers WHERE coords_id = ?
';

$db = new Db();

$res = $db->exe($sql, $coordsId);

if($res->num_rows){


    while($row = $res->fetch_object()){


        $path = 'scripts/map/triggers/'. $row->name .'.php';

        if(!file_exists($path)){

            exit('error trigger path');
        }

        $triggerId = $row->id;
        $params = $row->params;

        include($path);
    }
}


// plants
$sql = '
SELECT * FROM map_plants WHERE coords_id = ?
';

$db = new Db();

$res = $db->exe($sql, $coordsId);

if($res->num_rows){


    while($row = $res->fetch_object()){


        $path = 'scripts/map/plants.php';

        if(!file_exists($path)){

            exit('error plant path');
        }

        $plantId = $row->id;
        $name = $row->name;

        include($path);
    }
}


// followers
$db = new Db();

$res = $db->get_single_player_id('players_followers', $player->id);

if($res->num_rows){


    while($row = $res->fetch_object()){


        $path = 'scripts/map/followers.php';

        $tile_id = $row->tile_id;

        $position = $row->position;

        include($path);
    }

}


$player->go($goCoords);
