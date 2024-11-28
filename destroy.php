<?php

require_once('config.php');


$player = new Player($_SESSION['playerId']);


if(!isset($_POST['wallId'])){

    exit('error wall id');
}

$wallId = preg_replace('/[^0-9]/', '', $_POST['wallId']);


if($player->get_left('a') < 1){

    exit('Pas assez d\'Actions.');
}


$sql = '
SELECT
*,
map_walls.id AS id
FROM
map_walls
INNER JOIN
coords
ON
coords.id = map_walls.coords_id
WHERE
map_walls.id = ?
';

$db = new Db();

$res = $db->exe($sql, $wallId);


if(!$res->num_rows){

    exit('error wall');
}

$row = $res->fetch_object();


$wallCoords = (object) array(
    'x'=>$row->x,
    'y'=>$row->y,
    'z'=>$row->z,
    'plan'=>$row->plan
);


$distance = View::get_distance($player->get_coords(), $wallCoords);


if($distance > 1){

    exit('error distance');
}


if(!isset(WALLS_PV[$row->name])){

    exit('Cet objet est indestructible!');
}

$pvMax = WALLS_PV[$row->name];


$player->get_caracs();

$main1 = $player->emplacements->main1;


if($main1->data->name == 'poings'){

    exit('Impossible de détruire un objet avec les Poings.');
}

if($main1->data->subtype != 'melee'){

    exit('Il faut une arme de mêlée pour détruire cet objet.');
}


$damages = $player->caracs->f;

if(!empty($main1->data->demolition)){

    $damages += $main1->data->demolition;
}


$name = $row->name;

if(strpos($row->name, '_broken') !== false && file_exists('img/walls/'. $row->name .'_broken.png')){

    $name = $row->name .'_broken';

    $refresh = true;
}

$sql = 'UPDATE map_walls SET name = ?, damages = damages + ? WHERE id = ?';

$db->exe($sql, array($name, $damages, $row->id));

$itemJson = json()->decode('items', $row->name);
if($itemJson)
{
    $text = $player->data->name .' a attaqué '.$itemJson->name;
}
else
{
    $text = $player->data->name .' a attaqué une structure';
}


if($row->damages + $damages >= $pvMax){


    $db->delete('map_walls', array('id'=>$row->id));

    $refresh = true;

    $text .= ' et l\'a détruite';
}

$text .='.'; 



if(!empty($refresh) && $refresh){


    // refresh_view
    View::refresh_players_svg($player->coords);
}


$player->put_bonus($bonus=array('a'=>-1));

$player->put_xp(1);


Log::put($player, $player, $text, $type="destroy");


echo 'Vous infligez '. $damages .' dégâts (+1Xp).';
