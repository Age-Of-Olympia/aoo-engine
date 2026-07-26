<?php
/**
 * Gestion des dialogues — mutations (POST only). Pendant de admin/dialogs.php.
 *
 * Routé sur ?action : create | update | delete. Les nœuds sont validés par
 * DialogService::assertValidDialogData avant toute écriture ; la suppression
 * est gardée côté service (register, déclencheurs map_dialogs).
 *
 * CSRF validé ; même niveau d'accès que le menu dialogs.php pour qu'un POST
 * direct ne contourne rien. Redirige (PRG) avec un flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\DialogService;

(new AdminMenuAccessService())->enforce('dialogs.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/dialogs.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/dialogs.php');
}

$service = new DialogService();
$action = $_GET['action'] ?? '';
$name = strtolower(trim((string) ($_POST['name'] ?? '')));

if (!preg_match(DialogService::DIALOG_NAME_PATTERN, $name)) {
    setFlash('warning', 'Code de dialogue invalide (minuscules, chiffres, _ ou -, 100 max).');
    redirectTo('/admin/dialogs.php' . ($action === 'create' ? '?action=new' : ''));
}

if ($action === 'delete') {
    try {
        $service->deleteGameDialog($name);
        setFlash('success', "Dialogue « {$name} » supprimé.");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/dialogs.php?action=edit&name=' . urlencode($name));
    }
    redirectTo('/admin/dialogs.php');
}

if ($action === 'create' || $action === 'update') {
    if ($action === 'create' && $service->gameDialogExists($name)) {
        setFlash('warning', "Le dialogue « {$name} » existe déjà.");
        redirectTo('/admin/dialogs.php?action=edit&name=' . urlencode($name));
    }
    if ($action === 'update' && !$service->gameDialogExists($name)) {
        setFlash('warning', 'Dialogue introuvable.');
        redirectTo('/admin/dialogs.php');
    }

    $nodes = json_decode((string) ($_POST['dialog_data'] ?? ''), true);
    if (!is_array($nodes)) {
        setFlash('warning', 'Nœuds : JSON invalide.');
        redirectTo('/admin/dialogs.php?action=' . ($action === 'create' ? 'new' : 'edit&name=' . urlencode($name)));
    }

    try {
        $service->saveGameDialog($name, $nodes, [
            'npc_name'  => stringWithDefault('npc_name', 'TARGET_NAME'),
            'type'      => stringWithDefault('type', 'pnj'),
            'custom'    => trim((string) ($_POST['custom'] ?? '')),
            'is_active' => booleanCheckbox('is_active'),
        ]);
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/dialogs.php?action=' . ($action === 'create' ? 'new' : 'edit&name=' . urlencode($name)));
    }

    setFlash('success', $action === 'create'
        ? "Dialogue « {$name} » créé."
        : "Dialogue « {$name} » enregistré.");
    redirectTo('/admin/dialogs.php?action=edit&name=' . urlencode($name));
}

setFlash('warning', 'Action inconnue.');
redirectTo('/admin/dialogs.php');
