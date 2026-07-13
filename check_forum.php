<?php

use App\Factory\PlayerFactory;
use App\Service\ForumService;

require_once('config.php');

/*
 * Décompte des sujets de forum non lus, pour rafraîchir la pastille
 * orange du bouton Forum sans recharger la page (js/hud.js, après la
 * lecture d'un sujet en panneau). Même cache de session que le rendu
 * du bandeau haut (ForumService::GetUnreadCount, invalidé par
 * Forum::put_view).
 */

header('Content-Type: application/json; charset=utf-8');

$player = PlayerFactory::legacy($_SESSION['playerId']);
$player->get_data(false);

echo json_encode(['n' => (new ForumService())->GetUnreadCount($player)]);
