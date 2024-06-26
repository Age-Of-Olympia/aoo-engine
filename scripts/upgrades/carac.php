<?php

if(!isset(CARACS[$_POST['carac']])){

    exit('error carac');
}


$k = $_POST['carac'];

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


$values = array(
    'player_id'=>$player->id,
    'name'=>$k,
    'cost'=>$cost
);

$db->insert('players_upgrades', $values);


if($k == 'p'){

    $player->refresh_view();
}


$player->refresh_caracs();


exit('Vous avez augmenté '. CARACS[$k] .' pour '. $cost .'Pi.');
