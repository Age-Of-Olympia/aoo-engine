<?php
use Classes\Player;
use Classes\View;
use Classes\Db;
use App\Tutorial\TutorialHelper;

require_once('config.php');


if(!isset($_POST['targetId']) || !is_numeric($_POST['targetId'])){

    exit('error wall id');
}


$db = new Db();


$sql = 'SELECT * FROM map_triggers WHERE id = ?';

$res = $db->exe($sql, $_POST['targetId']);

if(!$res->num_rows){echo $_POST['targetId'];

    exit('error wall');
}


$row = $res->fetch_object();

$god = new Player($row->params);

$god->get_data();


$coords = View::get_coords('triggers', $row->id);

// Get active player ID (tutorial player if in tutorial mode, otherwise main player)
$playerId = TutorialHelper::getActivePlayerId();

$player = new Player($playerId);

$player->get_data();


// distance
$distance = View::get_distance($player->getCoords(), $coords);

if($distance > 1){

    exit('Vous n\'êtes pas à bonne distance.');
}


if($player->data->godId == $god->id){

    exit('<font color="red">Vous vénérez déjà ce Dieu.</font>');
}


$player->change_god($god);


echo 'Vous vénérez désormais le Dieu '. $god->data->name .'.';
