<?php
/**
 * Building management — mutations (POST only). Companion to
 * admin/buildings.php.
 *
 * Routed on ?action: place | restore | remove | dialog. Every branch is
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
    // Coordonnées obligatoires : un POST malformé ne doit pas poser le
    // bâtiment en (0,0) avec un flash de succès.
    if (!is_numeric($_POST['x'] ?? null) || !is_numeric($_POST['y'] ?? null)) {
        setFlash('warning', 'Coordonnées X/Y requises.');
        redirectTo('/admin/buildings.php');
    }
    $goCoords = (object) [
        'x' => (int) $_POST['x'],
        'y' => (int) $_POST['y'],
        'z' => is_numeric($_POST['z'] ?? null) ? (int) $_POST['z'] : 0,
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

    // Dialogue optionnel du formulaire de pose — même validation que le
    // sélecteur par ligne (catalogue dialogs), après création de la ligne.
    $dialog = trim((string) ($_POST['dialog'] ?? ''));
    if ($dialog !== '') {
        try {
            $service->setDialog($id, $dialog);
        } catch (\InvalidArgumentException $e) {
            setFlash('warning', "Bâtiment #{$id} posé, mais dialogue non attaché : " . $e->getMessage());
            redirectTo('/admin/buildings.php');
        }
    }

    setFlash('success', "Bâtiment #{$id} posé en ({$goCoords->x}, {$goCoords->y}, {$goCoords->z}) sur {$goCoords->plan}.");
    redirectTo('/admin/buildings.php');
}

if ($action === 'dialog') {
    $id = (int) ($_POST['id'] ?? 0);
    $dialog = trim((string) ($_POST['dialog'] ?? ''));

    try {
        $service->setDialog($id, $dialog);
    } catch (\InvalidArgumentException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/buildings.php');
    }

    setFlash('success', $dialog !== ''
        ? "Dialogue « {$dialog} » attaché au bâtiment #{$id}."
        : "Dialogue détaché du bâtiment #{$id}.");
    redirectTo('/admin/buildings.php');
}

if ($action === 'toggle-open') {
    $id = (int) ($_POST['id'] ?? 0);
    $open = !empty($_POST['open']);

    try {
        $service->setOpen($id, $open);
    } catch (\InvalidArgumentException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/buildings.php');
    }

    setFlash('success', $open
        ? "Bâtiment #{$id} ouvert."
        : "Bâtiment #{$id} fermé — son dialogue se tait.");
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
