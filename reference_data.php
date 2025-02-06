<?php

require_once('config.php');

$data_type = isset($_GET['data_type']) ? $_GET['data_type'] : '';

if($data_type =='player_name'){

    $term = isset($_GET['term']) ? $_GET['term'] : '';

    $playersJson = Player::get_player_list()->list;

    $suggestions = array();

    foreach ($playersJson as $player) {
        if(mb_stripos($player->name,$term) !== false){
            $suggestions[] = $player->name;
        }
    }

    echo json_encode($suggestions);
}





