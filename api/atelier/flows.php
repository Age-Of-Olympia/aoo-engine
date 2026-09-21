<?php

use App\Factory\PlayerFactory;
use App\Service\BuildingService;
use App\Service\RepairService;
use Classes\Market;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$player = PlayerFactory::active();
$player->get_data();

$target = PlayerFactory::legacy((int) ($POST_DATA['targetId'] ?? 0));

/* The counter first: same guards as the screen (access, then the tab the
 * dialog serves — an atelier repairs, a bank does not). */
$marketAccessError = Market::CheckMarketAccess($player, $target);
if ($marketAccessError != null) {
    ExitError($marketAccessError);
}

$action = (string) ($POST_DATA['action'] ?? '');
$tab = $action === 'exemplar-recycle' ? 'recycle' : 'repair';
if (!(new BuildingService())->servesCounter((int) $target->id, 'merchant.php', $tab)) {
    ExitError('On ne sert pas cela à ce comptoir.');
}

$service = new RepairService();
$playerId = (int) $player->id;
$instanceId = (int) ($POST_DATA['instanceId'] ?? 0);

try {
    switch ($action) {
        case 'exemplar-repair-resources':
            $service->repairWithResources($playerId, $instanceId);
            ExitSuccess(['message' => 'Réparé.']);
            break;
        case 'exemplar-repair-gold':
            $service->repairWithGold($playerId, $instanceId);
            ExitSuccess(['message' => 'Réparé.']);
            break;
        case 'exemplar-recycle':
            $service->recycle($playerId, $instanceId);
            ExitSuccess(['message' => 'Recyclé.']);
            break;
        default:
            ExitError('action inconnue');
    }
} catch (\RuntimeException | \InvalidArgumentException $e) {
    ExitError($e->getMessage());
}
