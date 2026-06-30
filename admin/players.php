<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\PlayerSkillsService;
use App\View\Player\PlayerSearchView;

$term = trim((string) ($_GET['q'] ?? ''));
$players = $term !== '' ? (new PlayerSkillsService())->searchPlayers($term) : [];

$body = (new PlayerSearchView())->render($term, $players);

echo admin_layout('Compétences des joueurs', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/player-skills.css'],
]);
