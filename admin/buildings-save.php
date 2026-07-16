<?php
/**
 * Building management — mutations (POST only). Companion to
 * admin/buildings.php.
 *
 * Routed on ?action: place | repair | remove. Every branch is
 * CSRF-validated and enforces the same menu level as buildings.php so a
 * direct POST can't bypass the dashboard gate. Redirects back (PRG) with a
 * flash. Business validation (type de structure au catalogue, faction du catalogue,
 * propriétaire existant) lives in BuildingService, shared with the console
 * command.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Factory\PlayerFactory;
use App\Service\AdminMenuAccessService;
use App\Service\BuildingService;
use App\Service\CsrfProtectionService;

(new AdminMenuAccessService())->enforce('buildings.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/buildings.php');
}

$csrf = new CsrfProtectionService();
try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/buildings.php');
}

$action = $_GET['action'] ?? '';
$service = new BuildingService();

if ($action === 'place') {
    $goCoords = (object) [
        'x' => (int) ($_POST['x'] ?? 0),
        'y' => (int) ($_POST['y'] ?? 0),
        'z' => 0,
        'plan' => trim((string) ($_POST['plan'] ?? 'gaia')),
    ];

    // Propriétaire : matricule numérique ou nom exact d'un joueur réel.
    $ownerId = null;
    $ownerInput = trim((string) ($_POST['owner'] ?? ''));
    if ($ownerInput !== '') {
        if (ctype_digit($ownerInput)) {
            $ownerId = (int) $ownerInput;
        } else {
            $owner = PlayerFactory::entityByName($ownerInput);
            if ($owner === null) {
                setFlash('warning', "Propriétaire introuvable : « {$ownerInput} ».");
                redirectTo('/admin/buildings.php');
            }
            $ownerId = (int) $owner->getId();
        }
    }

    $name = trim((string) ($_POST['name'] ?? ''));

    try {
        $id = $service->place(
            trim((string) ($_POST['type'] ?? '')),
            $goCoords,
            $ownerId,
            trim((string) ($_POST['faction'] ?? '')),
            $name !== '' ? $name : null
        );
    } catch (\InvalidArgumentException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/buildings.php');
    }

    setFlash('success', "Bâtiment #{$id} posé en ({$goCoords->x}, {$goCoords->y}) sur {$goCoords->plan}.");
    redirectTo('/admin/buildings.php');
}

if ($action === 'restore') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($service->restore($id)) {
        setFlash('success', "Structure #{$id} restaurée (PV au maximum, état « construit »).");
    } else {
        setFlash('warning', "Aucune structure #{$id}.");
    }
    redirectTo('/admin/buildings.php');
}

if ($action === 'remove') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($service->remove($id)) {
        setFlash('success', "Bâtiment #{$id} retiré.");
    } else {
        setFlash('warning', "Aucun bâtiment #{$id}.");
    }
    redirectTo('/admin/buildings.php');
}

setFlash('warning', 'Action inconnue.');
redirectTo('/admin/buildings.php');
