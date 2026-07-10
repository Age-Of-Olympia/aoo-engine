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
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\PlayerLookupService;
use App\Service\PlayerOptionsService;
use App\Service\PlayerPnjService;
use App\Service\PnjAdminService;

// Enforce the same level as the PNJ menu, so a direct POST can't bypass a
// superadmin-only setting on that menu.
(new AdminMenuAccessService())->enforce('pnjs.php');

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

/**
 * Load an existing PNJ and enforce the super-admin guard, or flash + redirect.
 * Shared by every mutation on an existing PNJ so the not-found handling and the
 * privilege guard can never drift apart (or be forgotten on a new branch).
 * Returns the PNJ row; never returns when the PNJ is missing (redirectTo exits).
 *
 * @return array{id:int, name:string, race:string, xp:int, lastLoginTime:int}
 */
$resolveAndGuardPnj = static function (int $pnjId) use ($service): array {
    $pnj = $service->getPnj($pnjId);
    if ($pnj === null) {
        setFlash('warning', 'PNJ introuvable.');
        redirectTo('/admin/pnjs.php');
    }
    // A PNJ holding isSuperAdmin can only be modified by a super-admin.
    if ((new PlayerOptionsService())->hasOption($pnjId, 'isSuperAdmin')) {
        AdminAuthorizationService::DoSuperAdminCheck();
    }
    return $pnj;
};

/* ---------------------------------------------------------------------- */
if ($action === 'set_retire_plan') {
    // Changing where PNJs are dumped is a config decision → super-admins only.
    AdminAuthorizationService::DoSuperAdminCheck();

    $stored = $service->setRetirePlan((string) ($_POST['retire_plan'] ?? ''));
    if ($stored === null) {
        setFlash('warning', 'Nom de plan invalide.');
    } else {
        setFlash('success', 'Plan des PNJ retirés : « ' . e($stored) . ' ».');
    }
    redirectTo('/admin/pnjs.php');
}

/* ---------------------------------------------------------------------- */
if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $race = (string) ($_POST['race'] ?? '');

    if ($name === '') {
        setFlash('warning', 'Le nom est requis.');
        redirectTo('/admin/pnjs.php?action=new');
    }
    // Whitelist against the same creatable-races set the dropdown offers, so a
    // race without a JSON/faction can never reach put_player.
    if (!in_array($race, $service->availableRaces(), true)) {
        setFlash('warning', 'Race non reconnue ou non disponible pour un PNJ.');
        redirectTo('/admin/pnjs.php?action=new');
    }

    try {
        $id = $service->createPnj($name, $race);
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
    $resolveAndGuardPnj($pnjId);

    $name = trim((string) ($_POST['name'] ?? ''));
    $race = (string) ($_POST['race'] ?? '');

    if ($name === '') {
        setFlash('warning', 'Le nom est requis.');
        redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
    }
    if (!in_array($race, $service->availableRaces(), true)) {
        setFlash('warning', 'Race non reconnue ou non disponible pour un PNJ.');
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
    $resolveAndGuardPnj($pnjId);

    $term = trim((string) ($_POST['player'] ?? ''));

    // Owner must be a real player (id > 0), resolved by matricule or exact name.
    $matches = (new PlayerLookupService())->resolve($term, ['real']);
    if (count($matches) === 0) {
        setFlash('warning', 'Aucun joueur réel trouvé pour « ' . e($term) . ' ».');
        redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
    }
    if (count($matches) > 1) {
        setFlash('warning', 'Plusieurs joueurs portent ce nom — utilisez le matricule.');
        redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
    }
    $playerId = $matches[0]['id'];

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
    $resolveAndGuardPnj($pnjId);

    $playerId = (int) ($_POST['player_id'] ?? 0);
    (new PlayerPnjService())->deleteByPlayerIdAndPnjId($playerId, $pnjId);
    setFlash('success', 'PNJ désassigné du joueur #' . $playerId . '.');
    redirectTo('/admin/pnjs.php?action=edit&id=' . $pnjId);
}

/* ---------------------------------------------------------------------- */
if ($action === 'retire') {
    $pnjId = (int) ($_POST['pnj_id'] ?? 0);
    $resolveAndGuardPnj($pnjId);

    $service->softRetire($pnjId);
    setFlash('success', 'PNJ retiré : désassigné, passé en incognito + anonyme et déplacé sur le plan des PNJ retirés.');
    redirectTo('/admin/pnjs.php');
}

/* Unknown action */
setFlash('warning', 'Action inconnue.');
redirectTo('/admin/pnjs.php');
