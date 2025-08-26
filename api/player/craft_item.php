<?php

use Classes\Player;
use Classes\Db;
use Classes\Item;
use App\Entity\Recipe;
use App\Service\RecipeService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
$player = new Player($_SESSION['playerId']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $POST_DATA = json_decode(file_get_contents('php://input'), true);

    if (!isset($POST_DATA['craft_id'])) {
        ExitError('Invalid request');
    }
    $craftID = (int)$POST_DATA['craft_id'];
    $recipeService = new RecipeService();

    $reciep = $recipeService->getRecipeById($craftID);
    // this item
    if (!$reciep) {
        ExitError('Recette introuvable');
    }
    $message = '';
    if ($recipeService->TryCraftRecipe($reciep, $player, $message)) {
        ExitSuccess($message);
    } else {
        ExitError($message);
    }
}
