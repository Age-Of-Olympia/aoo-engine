<?php

use App\Service\FactionChestService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

/* The ACTOR is the session; the service re-checks rank, bank and the
 * chest's current state on every gesture. */
$actorId = (int) $_SESSION['playerId'];
$chestId = (int) ($POST_DATA['chestId'] ?? 0);
$service = new FactionChestService();

try {
    switch ((string) ($POST_DATA['action'] ?? '')) {
        case 'claim':
            $service->claim($chestId, $actorId);
            ExitSuccess(['message' => 'Coffre repris pour la faction.']);
            break;
        case 'offer':
            $service->offer($chestId, $actorId, (int) ($POST_DATA['memberId'] ?? 0));
            ExitSuccess(['message' => 'Coffre offert.']);
            break;
        case 'abandon':
            $service->abandon($chestId, $actorId);
            ExitSuccess(['message' => 'Coffre abandonné : il est public.']);
            break;
        case 'entrust':
            $service->entrust($chestId, $actorId);
            ExitSuccess(['message' => 'Coffre confié à votre faction.']);
            break;
        case 'floor':
            $open = (int) ($POST_DATA['open'] ?? 0) === 1;
            $service->setFloorOpen($actorId, (string) ($POST_DATA['plan'] ?? ''), (int) ($POST_DATA['z'] ?? 0), $open);
            ExitSuccess(['message' => $open ? 'Niveau ouvert aux coffres.' : 'Niveau fermé aux coffres.']);
            break;
        default:
            ExitError('action inconnue');
    }
} catch (\RuntimeException $e) {
    ExitError($e->getMessage());
}
