<?php
/**
 * Faction management — mutations (POST only). Companion to admin/factions.php.
 *
 * Routed on ?action: create | update | delete. Delete is guarded: refused as
 * long as any character still references the code (players.faction or
 * players.secretFaction) — retiring a faction in use = check "cachée" instead.
 *
 * Role rows are rebuilt from the POSTed order (array_values): the DOM order
 * of the editor IS the role order, saved as positions 0..n-1 that
 * players.factionRole indexes into.
 *
 * CSRF-validated; enforces the same access level as the factions menu so a
 * direct POST can't bypass it. Redirects back (PRG) with a flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\Faction;
use App\Entity\FactionRole;
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\FactionService;

(new AdminMenuAccessService())->enforce('factions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/factions.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/factions.php');
}

$service = new FactionService();
$action = $_GET['action'] ?? '';
$code = strtolower(trim((string) ($_POST['code'] ?? '')));

// La suppression ne porte que le code : traitée avant la validation des
// champs du formulaire (absents d'un POST de suppression).
if ($action === 'delete') {
    $faction = $service->getFactionByCode($code);
    if ($faction === null) {
        setFlash('warning', 'Faction introuvable.');
        redirectTo('/admin/factions.php');
    }

    try {
        $service->deleteFaction($faction);
        setFlash('success', "Faction « {$code} » supprimée (rôles compris).");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/factions.php?action=edit&code=' . urlencode($code));
    }
    redirectTo('/admin/factions.php');
}

/**
 * Rôles POSTés → liste ordonnée pour FactionService::replaceRoles().
 * L'ordre DOM des lignes fait foi ; les lignes sans nom sont ignorées.
 *
 * @return list<array{name: string, flags: array<string, bool>}>
 */
$rolesFromForm = static function (): array {
    $roles = [];
    foreach (array_values((array) ($_POST['roles'] ?? [])) as $row) {
        if (!is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
            continue;
        }
        $flags = [];
        foreach (FactionRole::FLAG_KEYS as $key) {
            $flags[$key] = !empty($row[$key]);
        }
        $roles[] = ['name' => trim((string) $row['name']), 'flags' => $flags];
    }

    return $roles;
};

/** Apply every scalar form field onto the entity (shared by create/update). */
$applyForm = static function (Faction $faction): void {
    $faction->setName(trim((string) $_POST['name']));
    $faction->setText(trim((string) ($_POST['text'] ?? '')));
    $faction->setRaFont(trim((string) ($_POST['raFont'] ?? '')));
    $faction->setRespawnPlan(stringWithDefault('respawnPlan', plans()->worldPlan()));
    $faction->setHidden(booleanCheckbox('hidden'));
    $faction->setSecret(booleanCheckbox('secret'));
};

if (trim((string) ($_POST['name'] ?? '')) === '') {
    setFlash('warning', 'Le nom affiché est requis.');
    redirectTo('/admin/factions.php');
}

$roles = $rolesFromForm();
$defaultCount = count(array_filter($roles, static fn (array $r): bool => $r['flags']['defaultRole']));
$notice = '';
if ($roles !== [] && $defaultCount === 0) {
    $notice = ' ⚠ Aucun rôle « Défaut » coché : les affectations sans rôle précis retomberont sur le rôle 0.';
} elseif ($defaultCount > 1) {
    $notice = ' ⚠ Plusieurs rôles « Défaut » cochés : le premier de la liste sera utilisé.';
}

if ($action === 'create') {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
        setFlash('warning', 'Code de faction invalide (minuscules, chiffres, _).');
        redirectTo('/admin/factions.php?action=new');
    }
    if ($service->getFactionByCode($code) !== null) {
        setFlash('warning', "La faction « {$code} » existe déjà.");
        redirectTo('/admin/factions.php');
    }

    $faction = new Faction();
    $faction->setCode($code);
    $applyForm($faction);
    $service->save($faction);
    $service->replaceRoles($faction, $roles);

    setFlash('success', "Faction « {$code} » créée." . $notice);
    redirectTo('/admin/factions.php');
}

if ($action === 'update') {
    $faction = $service->getFactionByCode($code);
    if ($faction === null) {
        setFlash('warning', 'Faction introuvable.');
        redirectTo('/admin/factions.php');
    }

    $applyForm($faction);
    $service->save($faction);
    $service->replaceRoles($faction, $roles);

    setFlash('success', 'Faction « ' . $faction->getName() . ' » enregistrée.' . $notice);
    redirectTo('/admin/factions.php?action=edit&code=' . urlencode($code));
}

setFlash('warning', 'Action inconnue.');
redirectTo('/admin/factions.php');
