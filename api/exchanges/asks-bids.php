<?php

use App\Service\BidsAsksService;
use Classes\Market;
use Classes\ActorInterface;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SanitizeIntChecked($_GET['targetId'], 'error no merchant');

    $player = new ActorInterface($_SESSION['playerId']);
    $player->get_data();

    $target = new ActorInterface($_GET['targetId']);

    $marketAccessError = Market::CheckMarketAccess($player, $target);
    if ($marketAccessError != null) {

        ExitError($marketAccessError);
    }

    $POST_DATA = json_decode(file_get_contents('php://input'), true);
    if (!isset($POST_DATA['action']) || !in_array($POST_DATA['action'], ['accept', 'create', 'cancel'])) {
        ExitError(INVALID_REQ);
    }

    if (!isset($POST_DATA['type']) || !in_array($POST_DATA['type'], ['bids', 'asks'])) {
        ExitError(INVALID_REQ);
    }
    $bidsAsksService = new BidsAsksService();

    if ($POST_DATA['action'] == 'cancel') {

        SanitizeIntChecked($POST_DATA['id']);
        $bidsAsksService->Cancel($POST_DATA['type'], $POST_DATA['id'], $player);

    } elseif ($POST_DATA['action'] == 'create') {

        SanitizeIntChecked($POST_DATA['price']);
        SanitizeIntChecked($POST_DATA['quantity']);
        SanitizeIntChecked($POST_DATA['item_id']);

        $bidsAsksService->Create($POST_DATA['type'], $POST_DATA['item_id'], $POST_DATA['price'], $POST_DATA['quantity'], $player);
    }
    elseif ($POST_DATA['action'] == 'accept') {
        
        SanitizeIntChecked($POST_DATA['id']);
        SanitizeIntChecked($POST_DATA['quantity']);
        $bidsAsksService->Accept($POST_DATA['type'], $POST_DATA['id'],$POST_DATA['quantity'], $player);
    }
    else {
        ExitError(INVALID_REQ);
    }
}
