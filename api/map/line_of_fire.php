<?php
/**
 * Ligne de tir à la demande (clic droit / appui long sur une case du
 * damier — js/view.js) : cases traversées et premier obstacle entre le
 * joueur actif et la case visée. Le tracé n'est plus embarqué dans
 * chaque réponse observe : il ne s'affiche que sur demande explicite.
 */

use App\Service\BuildingService;
use App\Tutorial\TutorialHelper;
use Classes\Player;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

header('Content-Type: application/json; charset=utf-8');

$player = new Player(TutorialHelper::getActivePlayerId());
$player->getCoords();

$x = (int) ($_GET['x'] ?? 0);
$y = (int) ($_GET['y'] ?? 0);

$fireReport = (new BuildingService())->lineOfFireReport(
    $player->coords,
    (object) ['x' => $x, 'y' => $y, 'z' => $player->coords->z, 'plan' => $player->coords->plan]
);

echo json_encode([
    'tiles' => $fireReport['tiles'],
    'from' => [(int) $player->coords->x, (int) $player->coords->y],
    'to' => [$x, $y],
    'blocker' => $fireReport['blocker'],
    'blockerName' => $fireReport['blockerName'],
]);
