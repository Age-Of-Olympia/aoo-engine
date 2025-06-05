<?php
use Classes\Db;
if(!isset(CARACS[$_POST['carac']])){

    exit('error carac');
}


$k = $_POST['carac'];


if($k == 'spd'){

    exit('error carac: spd');
}


$cost = return_cost($trio[$k], $player->upgrades->$k);


if($player->row->pi < $cost){

    exit('Pas assez de Pi.');
}


$db = new Db();

$sql = '
UPDATE
players
SET
pi = pi - ?
WHERE
id = ?
AND
pi >= ?
';

$db->exe($sql, array($cost, $player->id, $cost));


$player->put_upgrade($k,$cost);


exit('Vous avez augmenté '. CARACS[$k] .' pour '. $cost .'Pi.');
