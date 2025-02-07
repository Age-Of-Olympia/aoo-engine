<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
Use App\Service\PlayerPnjService;

$result = ["message" => ""];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $POST_DATA = json_decode(file_get_contents('php://input'), true);
  try {
    $playerPnjService = new PlayerPnjService();
    
    $playerPnjService->updatePlayerPnj($POST_DATA['playerId'],$POST_DATA['pnjId'],$POST_DATA['display']);

    $result["message"] .= "Player pnj correctement update";

    exit(json_encode($result));
  } catch (Throwable $th) {
    ExitError('Erreur lors de la modification du player pnj');
  }
}
