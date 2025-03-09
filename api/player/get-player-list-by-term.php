<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

use App\Service\PlayerService;

$data_type = isset($_GET['data_type']) ? $_GET['data_type'] : '';

if($data_type =='player_name'){

    $term = isset($_GET['term']) ? $_GET['term'] : '';

    $playerService = new PlayerService();

    $suggestions = $playerService->searchNonAnonymePlayer($term);
    
    echo json_encode($suggestions);
}