<?php

use App\Factory\PlayerFactory;
use App\Service\PlayerExchangeService;
use App\View\PlayerExchangeView;

require_once('config.php');

$player = PlayerFactory::active();
$target = PlayerFactory::legacy((int) ($_GET['targetId'] ?? 0));
$player->get_data();
$target->get_data();
try {
    (new PlayerExchangeService())->assertContact($player, $target);
} catch (InvalidArgumentException $error) {
    echo '<p>' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}
PlayerExchangeView::render($player, $target);
