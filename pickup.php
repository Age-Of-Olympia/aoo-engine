<?php
/**
 * Ramasser le contenu de sa case — le seul chemin depuis que marcher
 * dessus ne prend plus rien. Le geste est explicite : on voit ce qui
 * traîne sur la case observée, et on décide de le prendre.
 *
 * POST sans paramètre : la case est toujours celle du joueur actif
 * (tutoriel compris), rien d'autre n'est atteignable ici.
 */

use App\Factory\PlayerFactory;
use App\Service\GroundLootService;
use App\Tutorial\TutorialHelper;

require_once('config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('error method');
}

$player = PlayerFactory::legacy(TutorialHelper::getActivePlayerId());
$player->get_data();
$player->getCoords();

/* Line-by-line when asked (the wood without the barrel): a stack by
 * its item, an exemplar by its instance; the full sweep otherwise. */
$itemId = (int) ($_POST['itemId'] ?? 0);
$instanceId = (int) ($_POST['instanceId'] ?? 0);
$service = new GroundLootService();

header('Content-Type: text/plain; charset=utf-8');

try {
    if ($instanceId > 0) {
        $lootList = $service->collectInstance($player, (int) $player->data->coords_id, $instanceId, $player->coords);
    } elseif ($itemId > 0) {
        $lootList = $service->collectStack($player, (int) $player->data->coords_id, $itemId, $player->coords);
    } else {
        $lootList = $service->collect($player, (int) $player->data->coords_id, $player->coords);
    }
} catch (\RuntimeException $e) {
    echo $e->getMessage();
    exit();
}

if ($lootList === []) {
    echo 'Rien à ramasser ici.';
    exit();
}

echo 'Ramassé : ' . implode(', ', $lootList) . '.';
