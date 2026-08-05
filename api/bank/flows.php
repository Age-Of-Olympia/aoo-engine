<?php

use App\Factory\PlayerFactory;
use App\Service\BankService;
use Classes\Market;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// Joueur ACTIF (tutoriel / PNJ), comme le reste du flux marchand.
$player = PlayerFactory::active();
$player->get_data();

$target = PlayerFactory::legacy((int) ($POST_DATA['targetId'] ?? 0));

// Le guichet d'abord : mêmes gardes que l'écran (accès marché, puis
// l'onglet — le dialogue fait foi, une échoppe n'a pas de coffre-fort).
$marketAccessError = Market::CheckMarketAccess($player, $target);
if ($marketAccessError != null) {
    ExitError($marketAccessError);
}
if (!(new \App\Service\BuildingService())->servesCounter((int) $target->id, 'merchant.php', 'bank')) {
    ExitError('On ne sert pas cela à ce comptoir.');
}

$service = new BankService();
$playerId = (int) $player->id;

try {
    switch ((string) ($POST_DATA['action'] ?? '')) {
        case 'stack-deposit':
            $service->depositStack($playerId, (int) ($POST_DATA['itemId'] ?? 0), (int) ($POST_DATA['n'] ?? 0));
            ExitSuccess(['message' => 'Déposé.']);
            break;
        case 'stack-withdraw':
            $service->withdrawStack($playerId, (int) ($POST_DATA['itemId'] ?? 0), (int) ($POST_DATA['n'] ?? 0));
            ExitSuccess(['message' => 'Retiré.']);
            break;
        case 'exemplar-deposit':
            $service->depositExemplar($playerId, (int) ($POST_DATA['instanceId'] ?? 0));
            ExitSuccess(['message' => 'Déposé.']);
            break;
        case 'exemplar-withdraw':
            $service->withdrawExemplar($playerId, (int) ($POST_DATA['instanceId'] ?? 0));
            ExitSuccess(['message' => 'Retiré.']);
            break;
        default:
            ExitError('action inconnue');
    }
} catch (\RuntimeException | \InvalidArgumentException $e) {
    ExitError($e->getMessage());
}
