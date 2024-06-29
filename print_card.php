<?php

define('NO_LOGIN', true);


require_once('config.php');


if(!isset($_GET['playerId'])){

    exit('error playerId');
}


$player = new Player(explode(',', $_GET['playerId'])[0]);

$player->get_data();


$ui = new Ui('Carte de '. $player->data->name);


$dataName = '<a href="infos.php?targetId='. $player->id .'">'. $player->data->name .'</a>';


$raceJson = json()->decode('races', $player->data->race);


$data = (object) array(
    'bg'=>$player->data->portrait,
    'name'=>$dataName,
    'img'=>'',
    'type'=>$raceJson->name,
    'text'=>'',
    'race'=>$player->data->race,
    'noClose'=>1
);

$card = Ui::get_card($data);

echo $card;
