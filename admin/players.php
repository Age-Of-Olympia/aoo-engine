<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\PlayerSkillsService;
use App\View\Player\PlayerSearchView;

$players = (new PlayerSkillsService())->listCharacters();

$body = (new PlayerSearchView())->render($players);

echo admin_layout('Compétences des joueurs', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/player-skills.css'],
]);
