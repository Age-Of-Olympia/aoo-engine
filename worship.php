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

$god->get_data();


$coords = View::get_coords('walls', $row->wall_id);


$player = new Player($_SESSION['playerId']);

$player->get_data();


// distance
$distance = View::get_distance($player->get_coords(), $coords);

if($distance > 1){

    exit('Vous n\'êtes pas à bonne distance.');
}


if($player->data->godId == $god->id){

    exit('<font color="red">Vous vénérez déjà ce Dieu.</font>');
}


$player->change_god($god);


echo 'Vous vénérez désormais le Dieu '. $god->data->name .'.';
