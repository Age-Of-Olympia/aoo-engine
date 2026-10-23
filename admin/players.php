<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\PlayerSkillsService;
use App\View\Player\PlayerSearchView;

// Only the characters the viewer may open: players and PNJs have separate rights
$access = new AdminMenuAccessService();
$players = array_values(array_filter(
    (new PlayerSkillsService())->listCharacters(),
    static fn(array $p): bool => $access->viewerAllows($access->forType('players.sheet', $p['player_type']))
));

$body = (new PlayerSearchView())->render($players);

echo admin_layout('Personnages', $body, [
    'styles' => ['/admin/css/player-skills.css'],
]);
