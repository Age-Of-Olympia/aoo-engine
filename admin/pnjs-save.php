<?php
/**
 * PNJ management — mutations (POST only). Companion to admin/pnjs.php.
 *
 * Routed on ?action: create | update | assign | unassign | retire.
 * Every branch is CSRF-validated and admin-gated; touching a PNJ that carries
 * isSuperAdmin additionally requires the actor to be a super-admin (mirrors the
 * `pnj` console command guard). Redirects back (PRG) with a flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\PlayerPnjService;
use App\Service\PlayerOptionsService;
use App\Service\PnjAdminService;
use Classes\Db;
use Classes\Player;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/pnjs.php');
}

$csrf = new CsrfProtectionService();
try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/pnjs.php');
}

$action = $_GET['action'] ?? '';
$service = new PnjAdminService();

/** Valid races for create/update — mirrors admin/pnjs.php's dropdown source. */
$validRaces = defined('RACES_EXT') ? RACES_EXT : (defined('RACES') ? RACES : []);

/**
 * Super-admin guard: a PNJ holding isSuperAdmin can only be modified by a
 * super-admin (same rule as console pnjcmd). Called for every mutation on an
 * existing PNJ.
 */
$guardSuperAdminPnj = static function (int $pnjId): void {
    if ((new PlayerOptionsService())->hasOption($pnjId, 'isSuperAdmin')) {
        AdminAuthorizationService::DoSuperAdminCheck();
    }
};

/* ---------------------------------------------------------------------- */
if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $race = (string) ($_POST['race'] ?? '');

    if ($name === '') {
        setFlash('warning', 'Le nom est requis.');
        redirectTo('/admin/pnjs.php?action=new');
    }
    if (!in_array($race, $validRaces, true)) {
        setFlash('warning', 'Race non reconnue.');
        redirectTo('/admin/pnjs.php?action=new');
    }

    try {
        $id = Player::put_player($name, $race, true);
        setFlash('success', 'PNJ « ' . e($name) . ' » créé (#' . (int) $id . ').');
        redirectTo('/admin/pnjs.php?action=edit&id=' . (int) $id);
    } catch (\Throwable $e) {
        setFlash('danger', 'Erreur lors de la création du PNJ.');
        redirectTo('/admin/pnjs.php?action=new');
    }
}

/* ---------------------------------------------------------------------- */
if ($action === 'update') {
    $pnjId = (int) ($_POST['pnj_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $race = (string) ($_POST['race'] ?? '');

    if ($service->getPnj($pnjId) === null) {
        setFlash('warning', 'PNJ introuvable.');
        redirectTo('/admin/pnjs.php');
    }
    $guardSuperAdminPnj($pnjId);

    if ($name === '') {
        setFlash('warning', 'Le nom est requis.');
        redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
    }
    if (!in_array($race, $validRaces, true)) {
        setFlash('warning', 'Race non reconnue.');
        redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
    }

    try {
        $service->updatePnj($pnjId, $name, $race);
        setFlash('success', 'PNJ mis à jour.');
    } catch (\Throwable $e) {
        setFlash('danger', 'Erreur lors de la mise à jour du PNJ.');
    }
    redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
}

/* ---------------------------------------------------------------------- */
if ($action === 'assign') {
    $pnjId = (int) ($_POST['pnj_id'] ?? 0);
    $term = trim((string) ($_POST['player'] ?? ''));

    if ($service->getPnj($pnjId) === null) {
        setFlash('warning', 'PNJ introuvable.');
        redirectTo('/admin/pnjs.php');
    }
    $guardSuperAdminPnj($pnjId);

    // Resolve the controlling player: a real player (id > 0), by matricule or
    // exact name.
    $db = new Db();
    if (is_numeric($term)) {
        $res = $db->exe("SELECT id FROM players WHERE id = ? AND player_type = 'real'", [(int) $term]);
    } else {
        $res = $db->exe("SELECT id FROM players WHERE name = ? AND player_type = 'real'", [$term]);
    }

    if (!$res->num_rows) {
        setFlash('warning', 'Aucun joueur réel trouvé pour « ' . e($term) . ' ».');
        redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
    }
    $playerId = (int) $res->fetch_assoc()['id'];

    $pnjService = new PlayerPnjService();
    if ($pnjService->getByPlayerIdAndPnjId($playerId, $pnjId) !== null) {
        setFlash('info', 'Ce joueur contrôle déjà ce PNJ.');
    } else {
        $pnjService->create($playerId, $pnjId, true);
        setFlash('success', 'PNJ assigné au joueur #' . $playerId . '.');
    }
    redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
}

/* ---------------------------------------------------------------------- */
if ($action === 'unassign') {
    $pnjId = (int) ($_POST['pnj_id'] ?? 0);
    $playerId = (int) ($_POST['player_id'] ?? 0);

    if ($service->getPnj($pnjId) === null) {
        setFlash('warning', 'PNJ introuvable.');
        redirectTo('/admin/pnjs.php');
    }
    $guardSuperAdminPnj($pnjId);

    (new PlayerPnjService())->deleteByPlayerIdAndPnjId($playerId, $pnjId);
    setFlash('success', 'PNJ désassigné du joueur #' . $playerId . '.');
    redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
}

/* ---------------------------------------------------------------------- */
if ($action === 'retire') {
    $pnjId = (int) ($_POST['pnj_id'] ?? 0);

    if ($service->getPnj($pnjId) === null) {
        setFlash('warning', 'PNJ introuvable.');
        redirectTo('/admin/pnjs.php');
    }
    $guardSuperAdminPnj($pnjId);

    $service->softRetire($pnjId);
    setFlash('success', 'PNJ retiré (désassigné de tous les joueurs et masqué).');
    redirectTo('/admin/pnjs.php');
}

/* Unknown action */
setFlash('warning', 'Action inconnue.');
redirectTo('/admin/pnjs.php');
