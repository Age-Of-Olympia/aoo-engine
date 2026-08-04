<?php

use App\Service\FactionService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ExitError('Invalid request');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true) ?: $_POST;

/* The ACTOR is the session, never a parameter: the service re-checks the
 * role flag server-side for every gesture. */
$actorId = (int) $_SESSION['playerId'];
$service = new FactionService();

try {
    switch ((string) ($POST_DATA['action'] ?? '')) {
        case 'add':
            $name = $service->addMember($actorId, (string) ($POST_DATA['name'] ?? ''));
            ExitSuccess(['message' => $name . ' rejoint la faction.']);
            break;
        case 'kick':
            $service->kickMember($actorId, (int) ($POST_DATA['targetId'] ?? 0));
            ExitSuccess(['message' => 'Membre renvoyé.']);
            break;
        case 'role':
            $service->assignRole($actorId, (int) ($POST_DATA['targetId'] ?? 0), (int) ($POST_DATA['position'] ?? -1));
            ExitSuccess(['message' => 'Rang changé.']);
            break;
        case 'role-def':
            $service->updateRoleDefinition(
                $actorId,
                (int) ($POST_DATA['position'] ?? -1),
                (string) ($POST_DATA['name'] ?? ''),
                is_array($POST_DATA['flags'] ?? null) ? $POST_DATA['flags'] : []
            );
            ExitSuccess(['message' => 'Rang réglé.']);
            break;
        default:
            ExitError('action inconnue');
    }
} catch (\RuntimeException $e) {
    ExitError($e->getMessage());
}
