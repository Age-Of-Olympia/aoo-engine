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


// distance
if(View::get_distance($player->coords, $goCoords) > 1){

    exit('error distance');
}


$coordsId = View::get_coords_id($goCoords);


$db = new Db();


$sql = '
SELECT *, "triggers" AS whichTable FROM map_triggers WHERE coords_id = ?
UNION
SELECT *, "plants" AS whichTable FROM map_plants WHERE coords_id = ?

ORDER BY id DESC
';

$res = $db->exe($sql, array($coordsId, $coordsId));

if($res->num_rows){


    while($row = $res->fetch_object()){


        if($row->whichTable == 'triggers'){


            $path = 'scripts/map/triggers/'. $row->name .'.php';

            if(!file_exists($path)){

                exit('error trigger path');
            }

            $triggerId = $row->id;
            $params = $row->params;
        }

        elseif($row->whichTable == 'plants'){


            $path = 'scripts/map/plants.php';

            if(!file_exists($path)){

                exit('error plant path');
            }

            $plantId = $row->id;
            $name = $row->name;
        }


        include($path);
    }
}


// underground
if($goCoords->z < 0){


    $values = array(
        'name'=>'caverne',
        'coords_id'=>$coordsId
    );

    $db->delete('map_tiles', $values);

    $db->insert('map_tiles', $values);
}


// sky
elseif($goCoords->z > 0){


    $sql = 'SELECT COUNT(*) AS n FROM map_tiles WHERE coords_id = ?';

    $res = $db->exe($sql, $coordsId);

    $row = $res->fetch_object();

    if(!$row->n && !$player->have_effect('vol')){

        echo '<script>alert("Il faut pouvoir voler pour accéder à ce lieu.");</script>';

        exit();
    }

    elseif(!$row->n && $player->have_effect('vol')){

        // vol
        include('scripts/map/vol.php');
    }
}



// loots
$sql = '
SELECT * FROM map_items WHERE coords_id = ?
';

$res = $db->exe($sql, $coordsId);

if($res->num_rows){


    while($row = $res->fetch_object()){


        $item = new Item($row->item_id);

        $item->add_item($player, $row->n);
    }


    $values = array(
        'coords_id'=>$coordsId
    );

    $db->delete('map_items', $values);
}


// cost (neg bonus)
$bonus = array('mvt'=>-1);

$player->put_bonus($bonus);


$player->go($goCoords);
