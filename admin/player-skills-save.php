<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\PlayerSkillsService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/players.php');
    exit;
}

$csrf = new CsrfProtectionService();
$service = new PlayerSkillsService();
$playerId = (int) ($_POST['player_id'] ?? 0);

if (!$csrf->validateToken($_POST['csrf_token'] ?? null)) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/player-skills.php?id=' . $playerId);
}

try {
    if ($service->getPlayerSummary($playerId) === null) {
        setFlash('warning', 'Joueur introuvable.');
        redirectTo('/admin/players.php');
    }

    // Unchecked boxes are simply absent from POST; missing arrays mean "none".
    $desiredActions = array_map('strval', (array) ($_POST['actions'] ?? []));
    $desiredPassives = array_map('intval', (array) ($_POST['passives'] ?? []));

    $changes = $service->applySkills($playerId, $desiredActions, $desiredPassives);

    $touched = array_sum($changes);
    if ($touched === 0) {
        setFlash('info', 'Aucune modification.');
    } else {
        setFlash('success', sprintf(
            'Compétences enregistrées : actions +%d / −%d, passifs +%d / −%d.',
            $changes['actions_added'],
            $changes['actions_removed'],
            $changes['passives_added'],
            $changes['passives_removed']
        ));
    }
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'enregistrement des compétences.");
}

header('Location: /admin/player-skills.php?id=' . $playerId);
exit;
