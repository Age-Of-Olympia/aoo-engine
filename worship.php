<?php
use App\Factory\PlayerFactory;
use Classes\View;
use Classes\Db;

require_once('config.php');


if(!isset($_POST['targetId']) || !is_numeric($_POST['targetId'])){

    exit('error wall id');
}


$db = new Db();


/* Un déclencheur, oui, mais UN AUTEL : la requête acceptait n'importe
   lequel des 14 233 déclencheurs de la carte, et allait chercher un dieu
   dans son `params` — un plan pour un téléporteur, un texte pour une
   pancarte. */
$sql = 'SELECT * FROM map_triggers WHERE id = ? AND name = "altar"';

$res = $db->exe($sql, $_POST['targetId']);

if(!$res->num_rows){echo $_POST['targetId'];

    exit('error wall');
}


$row = $res->fetch_object();

/* `params` est du texte libre : il ne désigne un dieu que sur un autel bien
   formé. Passé tel quel à un chargement par identifiant, il tuait la requête
   avant même le contrôle de distance. */
if(!is_numeric($row->params)){

    exit('Cet autel ne désigne aucun Dieu.');
}

$god = PlayerFactory::legacy((int) $row->params);

$god->get_data();

if(($god->data->race ?? '') !== 'dieu'){

    exit('Cet autel ne désigne aucun Dieu.');
}


$coords = View::get_coords('triggers', $row->id);

$player = PlayerFactory::active();

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
