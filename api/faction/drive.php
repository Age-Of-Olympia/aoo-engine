<?php

use App\Service\AuditService;
use App\Service\BuildingService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

/* The ACTOR is the session: taking command re-checks the household rule
 * and the thing's own state server-side; releasing only ever returns to
 * the account's main character. */
$actorId = (int) $_SESSION['playerId'];

try {
    switch ((string) ($POST_DATA['action'] ?? '')) {
        case 'take':
            $buildingId = (int) ($POST_DATA['buildingId'] ?? 0);
            (new BuildingService())->assertDrivable($buildingId, $actorId);

            /* Same gesture as the PNJ switch, one method (the door —
             * assertDrivable here, the PNJ list there — stays each
             * caller's own). */
            (new \App\Service\ImpersonationService())->driveAs($buildingId);
            (new AuditService())->addAuditLog("#{$actorId} prend les commandes du bâtiment #{$buildingId}");
            ExitSuccess(['message' => 'Vous prenez les commandes.']);
            break;
        case 'release':
            (new \App\Service\ImpersonationService())->release();
            ExitSuccess(['message' => 'Vous reprenez votre personnage.']);
            break;
        default:
            ExitError('action inconnue');
    }
} catch (\RuntimeException $e) {
    ExitError($e->getMessage());
}
