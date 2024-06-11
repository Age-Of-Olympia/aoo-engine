<?php

require_once('config.php');


if(!isset($_POST['targetId']) || !is_numeric($_POST['targetId'])){

    exit('error wall id');
}


$db = new Db();


$sql = 'SELECT * FROM altars WHERE wall_id = ?';

$res = $db->exe($sql, $_POST['targetId']);

if(!$res->num_rows){

    exit('error wall');
}


$row = $res->fetch_object();

$god = new Player($row->player_id);


$sql = '
SELECT
x,y,z,plan
FROM
coords AS c
INNER JOIN
map_walls AS w
ON
w.coords_id = c.id
WHERE
w.id = ?
';

$res = $db->exe($sql, $row->wall_id);

if(!$res->num_rows){

    exit('error coords');
}


$row = $res->fetch_object();


$coords = (object) array(
    'x'=>$row->x,
    'y'=>$row->y,
    'z'=>$row->z,
    'plan'=>$row->plan
);


$player = new Player($_SESSION['playerId']);


// distance
$distance = Player::get_distance($player->get_coords(), $coords);

if($distance > 1){

    exit('Vous n\'êtes pas à bonne distance.');
}


$player->change_god($god);


echo 'Vous vénérez désormais '. $god->row->name .'.';
