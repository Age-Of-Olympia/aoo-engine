<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\PlayerLoadoutService;
use App\View\Player\PlayerSearchView;

$term = trim((string) ($_GET['q'] ?? ''));
$players = $term !== '' ? (new PlayerLoadoutService())->searchPlayers($term) : [];

$body = (new PlayerSearchView())->render($term, $players);

echo admin_layout('Player loadouts', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/player-loadout.css'],
]);
