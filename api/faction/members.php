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
            $service->assignRole(
                $actorId,
                (int) ($POST_DATA['targetId'] ?? 0),
                (int) ($POST_DATA['position'] ?? -1),
                (int) ($POST_DATA['variant'] ?? 0)
            );
            ExitSuccess(['message' => 'Rang changé.']);
            break;
        case 'role-def':
            $service->updateRoleDefinition(
                $actorId,
                (int) ($POST_DATA['position'] ?? -1),
                (string) ($POST_DATA['name'] ?? ''),
                is_array($POST_DATA['flags'] ?? null) ? $POST_DATA['flags'] : [],
                (string) ($POST_DATA['nameAlt'] ?? '')
            );
            ExitSuccess(['message' => 'Rang réglé.']);
            break;
        case 'rank-landing':
            $service->setLandingRank($actorId, (int) ($POST_DATA['position'] ?? -1));
            ExitSuccess(['message' => 'Rang d\'accueil désigné.']);
            break;
        case 'rank-add':
            $service->addRank($actorId, (string) ($POST_DATA['name'] ?? ''));
            ExitSuccess(['message' => 'Rang ajouté, juste sous le sommet.']);
            break;
        case 'rank-remove':
            $service->removeRank($actorId, (int) ($POST_DATA['position'] ?? -1));
            ExitSuccess(['message' => 'Rang retiré.']);
            break;
        case 'rank-move':
            $service->moveRank($actorId, (int) ($POST_DATA['position'] ?? -1), (int) ($POST_DATA['direction'] ?? 0));
            ExitSuccess(['message' => 'Rangs échangés.']);
            break;
        default:
            ExitError('action inconnue');
    }
} catch (\RuntimeException $e) {
    ExitError($e->getMessage());
}
