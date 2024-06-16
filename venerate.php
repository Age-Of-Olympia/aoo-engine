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


$coords = View::get_coords('walls', $row->id);


$player = new Player($_SESSION['playerId']);


// distance
$distance = Player::get_distance($player->get_coords(), $coords);

if($distance > 1){

    exit('Vous n\'êtes pas à bonne distance.');
}


$player->change_god($god);


echo 'Vous vénérez désormais '. $god->row->name .'.';
