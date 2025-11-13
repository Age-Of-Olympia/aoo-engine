<?php
use Classes\Player;
use Classes\Db;
use Classes\View;
use Classes\Log;
use Classes\Item;
use Classes\Element;
require_once('config.php');


if(!isset($_POST['coords'])){

    exit('error coords');
}


$coords = explode(',', $_POST['coords']);

// Use tutorial character if in tutorial mode
$playerId = $_SESSION['playerId'];
if (!empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_player_id'])) {
    $playerId = $_SESSION['tutorial_player_id'];
}

$player = new Player($playerId);

if($player->getRemaining('mvt') < 1){


    echo '<script>alert("Pas assez de Mouvements.");document.location.reload();</script>';
    exit();
}

$player->getCoords();


$goCoords = (object) array(
    'x'=>$coords[0],
    'y'=>$coords[1],
    'z'=>$player->coords->z,
    'plan'=>$player->coords->plan
);

$originalGooCoords=$goCoords;

if(!is_numeric($goCoords->x) || !is_numeric($goCoords->y)){

    exit('error coords numeric');
}


// distance
if(View::get_distance($player->coords, $goCoords) > 1){

    exit('error distance');
}


$coordsId = View::get_coords_id($goCoords);
$goCoords->coordsId=$coordsId;

$db = new Db();


// check invalid location
$inPlayerSql = '';
$values = $coordsId;

if($planJson = json()->decode('plans', $player->coords->plan)){

    $inPlayerSql = '
    OR
    id IN(
        SELECT coords_id FROM players WHERE coords_id = ?
        )
    ';

    $values = array($coordsId, $coordsId);
}


$sql = '
SELECT COUNT(*) AS n
FROM coords
WHERE
id IN(
    SELECT coords_id FROM map_walls WHERE coords_id = ?
    )
'. $inPlayerSql .'
';

$res = $db->exe($sql, $values);

$row = $res->fetch_object();

if($row->n){


    echo '<script>alert("Coordonnées invalides");document.location.reload();</script>';

    exit();
}


$sql = '
SELECT *, "triggers" AS whichTable FROM map_triggers WHERE coords_id = ? and name != "grow"
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


    /*$sql = '
    SELECT COUNT(*) AS n
    FROM map_tiles
    WHERE
    coords_id = ?
    AND
    name = "caverne"
    ';*/

    $sql = '
    SELECT COUNT(*) AS n
    FROM map_tiles
    WHERE
    coords_id = ?
    ';

    $res = $db->exe($sql, $coordsId);

    $row = $res->fetch_object();

    if(!$row->n){


        if(!isset($player->caracs)){


            $player->get_caracs();
        }


        if($player->getRemaining('a') < 1){


            echo '<script>alert("Pas assez d\'Actions.");document.location.reload();</script>';
            exit();
        }


        $bonus = array('a'=>-1);
        $player->putBonus($bonus);

        $player->put_xp(XP_PER_MINE);


        if($player->emplacements->main1->data->name != 'Pioche'){


            $player->put_malus(MALUS_PER_MINE);
            
            echo '<script>alert("Creuser sans Pioche, qu\'est-ce que ça fatigue !");document.location.reload();</script>';
        }


        $pierre = Item::get_item_by_name('pierre');
        $pierre->add_item($player, 1);

        $text = $player->data->name .' a creusé et a trouvé 1 pierre.';
        Log::put($player, $player, $text, type:"loot");

        $values = array(
            'name'=>'caverne',
            'coords_id'=>$coordsId
        );

        $db->insert('map_tiles', $values);
    }
}


// sky
elseif($goCoords->z > 0){


    $sql = 'SELECT COUNT(*) AS n FROM map_tiles WHERE coords_id = ?';

    $res = $db->exe($sql, $coordsId);

    $row = $res->fetch_object();

    if(!$row->n && !$player->haveEffect('vol')){

        echo '<script>alert("Il faut pouvoir voler pour accéder à ce lieu."); document.location.reload();</script>';

        exit();
    }
}



// loots
$sql = '
SELECT * FROM map_items WHERE coords_id = ?
';

$res = $db->exe($sql, $coordsId);

if($res->num_rows){


    $lootList = array();

    while($row = $res->fetch_object()){


        $item = new Item($row->item_id);

        $item->get_data();

        $item->add_item($player, $row->n);

        $lootList[] = $item->data->name .' x'. $row->n;
    }


    $values = array(
        'coords_id'=>$coordsId
    );

    $db->delete('map_items', $values);


    $text = $player->data->name .' a ramassé des objets: '. implode(', ', $lootList) .'.';
    $coordBackup = $player->coords;
    $player->coords = $goCoords;
    Log::put($player, $player, $text, type:"loot");
    $player->coords = $coordBackup;
}



if($planJson){


    // cost (neg bonus)
    $bonus = array('mvt'=>-1);
    $player->putBonus($bonus);
}

if(!$player->have_option('incognitoMode'))
{
    $footstep='trace_pas_';
    if($originalGooCoords->y>$player->coords->y){
        $footstep.='n';
    }
    elseif($originalGooCoords->y<$player->coords->y){
        $footstep.='s';
    }
    if($originalGooCoords->x>$player->coords->x){
        $footstep.='e';
    }
    elseif($originalGooCoords->x<$player->coords->x){
        $footstep.='o';
    }

    $footstepDuration = 16 * ONE_HOUR;
    if ($player->haveEffect("boue")) {
        $footstepDuration = 32 * ONE_HOUR;
    }
    Element::put($footstep, $player->data->coords_id, $footstepDuration);
}

$player->go($goCoords);