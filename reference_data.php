<?php

require_once('config.php');

$data_type = isset($_GET['data_type']) ? $_GET['data_type'] : '';

if($data_type =='player_name'){

    $term = isset($_GET['term']) ? $_GET['term'] : '';

    $playersJson = json()->decode('players', 'list');


    if (!$playersJson) {

        Player::refresh_list();
        $playersJson = json()->decode('players', 'list');
    }


    $suggestions = array();

    foreach ($playersJson as $player) {
        if(mb_stripos($player->name,$term) !== false){
            $suggestions[] = $player->name;
        }
    }

    echo json_encode($suggestions);
}





