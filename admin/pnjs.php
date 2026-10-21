<?php
/**
 * PNJ management (admin dashboard → Joueurs → PNJ).
 *
 * Three views, routed on ?action:
 *   - list  (default): roster of every PNJ with owner(s), activity, XP
 *            (search / facets / paging by admin-list.js) and a create button.
 *            Each row → edit, or soft-retire (inline confirm form).
 *   - new   : create-a-PNJ form (name + race) → Player::put_player(..., pnj).
 *   - edit  : rename / change race, manage controlling owners (assign /
 *             unassign), and soft-retire.
 *
 * All mutations POST to pnjs-save.php (CSRF-validated, PRG). This page only
 * renders. Admin-gated by layout.php (DoAdminCheck).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\NpcAdminService;
use App\View\Player\PlayerSearchView;

/**
 * Race options for the create/edit dropdowns — only races that can actually be
 * created (JSON + faction present), from the one source NpcAdminService owns and
 * the save endpoint validates against.
 *
 * @return array<string,string>
 */
function pnj_race_options(): array
{
    $out = [];
    foreach ((new NpcAdminService())->availableRaces() as $race) {
        $out[$race] = ucfirst($race);
    }
    return $out;
}

/**
 * @param array<int, array{id:int, name:string, race:string, xp:int, lastLoginTime:int, active:bool, owners:?string, owner_count:int}> $pnjs
 */
function pnj_render_list(array $pnjs, string $csrfToken, bool $canEditRetirePlan): string
{
    $rows = '';
    foreach ($pnjs as $pnj) {
        $assigned = $pnj['owner_count'] > 0;
        $owners = $pnj['owners'] !== null ? e($pnj['owners']) : '<span class="text-muted">— non assigné —</span>';

        $retireForm = '<form method="post" action="/admin/pnjs-save.php?action=retire" class="d-inline"'
            . ' data-confirm="Retirer ce PNJ ? Il sera désassigné de tous les joueurs, passé en incognito + anonyme et déplacé sur le plan des PNJ retirés. Réversible.">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">Retirer</button></form>';

        $rows .= '<tr data-status="' . ($pnj['active'] ? 'Actif' : 'Inactif') . '"'
            . ' data-assigned="' . ($assigned ? 'Assigné' : 'Non assigné') . '">'
            . '<td>' . (int) $pnj['id'] . '</td>'
            . '<td>' . e($pnj['name']) . '</td>'
            . '<td>' . e(character_race_choices()[$pnj['race']] ?? ucfirst($pnj['race'])) . '</td>'
            . '<td>' . PlayerSearchView::statusBadge($pnj['active']) . '</td>'
            . '<td>' . $owners . '</td>'
            . '<td>' . (int) $pnj['xp'] . '</td>'
            . '<td class="text-nowrap">' . embodyButton((int) $pnj['id'], $csrfToken) . ' '
            . '<a class="btn btn-sm btn-outline-primary" href="/admin/pnjs.php?action=edit&amp;id='
            . (int) $pnj['id'] . '">Gérer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" href="/admin/player-edit.php?id='
            . (int) $pnj['id'] . '">Fiche</a> '
            . '<a class="btn btn-sm btn-outline-secondary" href="/admin/player-skills.php?id='
            . (int) $pnj['id'] . '">Compétences</a> '
            . $retireForm
            . '</td>'
            . '</tr>';
    }
    if ($rows === '') {
        $rows = '<tr><td colspan="7" class="text-muted">Aucun PNJ.</td></tr>';
    }

    // Settings: the plan retired PNJs are parked on (configurable, not hardcoded).
    $service = new NpcAdminService();
    $currentPlan = $service->getRetirePlan();
    $planOptions = '';
    foreach ($service->listPlans() as $plan) {
        $planOptions .= '<option value="' . e($plan) . '"></option>';
    }
    // Secondary setting: kept compact and collapsed so it doesn't crowd the page.
    // Editing the retirement plan is super-admin only; plain admins see it
    // read-only (enforcement also lives server-side in pnjs-save.php).
    if ($canEditRetirePlan) {
        $settingsCard = '<details class="pnj-retire-setting text-muted small mb-3">'
            . '<summary>⚙ Plan des PNJ retirés : <strong>' . e($currentPlan) . '</strong></summary>'
            . '<form method="post" action="/admin/pnjs-save.php?action=set_retire_plan"'
            . ' class="d-flex flex-wrap align-items-center gap-2 mt-2">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="text" name="retire_plan" list="pnj-plan-list" value="' . e($currentPlan) . '"'
            . ' class="form-control form-control-sm" style="max-width:14rem" required>'
            . '<datalist id="pnj-plan-list">' . $planOptions . '</datalist>'
            . '<button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>'
            . '<span>Les PNJ retirés y sont déplacés (case libre).</span>'
            . '</form></details>';
    } else {
        $settingsCard = '<p class="text-muted small mb-3">'
            . '⚙ Plan des PNJ retirés : <strong>' . e($currentPlan) . '</strong>'
            . ' (modifiable par un super-admin)</p>';
    }

    return $settingsCard
        . '<table class="table table-striped table-hover" data-admin-list data-page-size="50"'
        . ' data-search-placeholder="Nom ou matricule…" data-facets="status:Statut,assigned:Affectation">'
        . '<thead><tr><th>Matricule</th><th>Nom</th><th>Race</th><th>Statut</th>'
        . '<th>Contrôlé par</th><th>XP</th><th></th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>';
}

function pnj_render_create_form(string $csrfToken): string
{
    return '<form method="post" action="/admin/pnjs-save.php?action=create" class="card pnj-card">'
        . '<div class="card-body">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<div class="mb-3"><label>Nom</label>'
        . '<input type="text" name="name" class="form-control" required maxlength="255" autofocus></div>'
        . '<div class="mb-3"><label>Race</label>'
        . formSelect('race', pnj_race_options(), null, '— Choisir une race —', 'class="form-control" required') . '</div>'
        . '<button type="submit" class="btn btn-primary">Créer le PNJ</button>'
        . '</div></form>';
}

/**
 * @param array{id:int, name:string, race:string, xp:int, lastLoginTime:int} $pnj
 * @param array<int, array{player_id:int, name:string, displayed:bool}> $owners
 */
function pnj_render_edit_form(array $pnj, array $owners, string $csrfToken): string
{
    // --- Identity form (rename / change race) ---
    $identity = '<form method="post" action="/admin/pnjs-save.php?action=update" class="card pnj-card">'
        . '<div class="card-header">Identité</div>'
        . '<div class="card-body">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
        . '<div class="mb-3"><label>Nom</label>'
        . '<input type="text" name="name" class="form-control" required maxlength="255" value="' . e($pnj['name']) . '"></div>'
        . '<div class="mb-3"><label>Race</label>'
        . formSelect('race', pnj_race_options(), $pnj['race'], null, 'class="form-control" required') . '</div>'
        . '<button type="submit" class="btn btn-primary">Enregistrer</button>'
        . '</div></form>';

    // --- Owners list + unassign ---
    $ownerRows = '';
    foreach ($owners as $o) {
        $ownerRows .= '<tr><td>' . e($o['name']) . ' <span class="text-muted">(#' . (int) $o['player_id'] . ')</span></td>'
            . '<td><form method="post" action="/admin/pnjs-save.php?action=unassign" class="d-inline">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
            . '<input type="hidden" name="player_id" value="' . (int) $o['player_id'] . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">Désassigner</button></form></td></tr>';
    }
    if ($ownerRows === '') {
        $ownerRows = '<tr><td colspan="2" class="text-muted">Aucun joueur ne contrôle ce PNJ.</td></tr>';
    }

    $assign = '<div class="card pnj-card">'
        . '<div class="card-header">Contrôlé par</div>'
        . '<div class="card-body">'
        . '<table class="table table-sm"><tbody>' . $ownerRows . '</tbody></table>'
        . '<form method="post" action="/admin/pnjs-save.php?action=assign" class="d-flex gap-2 mt-2">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
        . '<input type="text" name="player" class="form-control" placeholder="Matricule ou nom exact du joueur…" required>'
        . '<button type="submit" class="btn btn-secondary">Assigner</button>'
        . '</form></div></div>';

    // --- Danger zone: soft retire ---
    $retire = '<div class="pnj-card">' . dangerZone(
        '<p class="text-muted">Le retrait, sans rien supprimer :</p>'
        . '<ul class="text-muted">'
        . '<li>le <strong>désassigne</strong> de tous les joueurs qui le contrôlent ;</li>'
        . '<li>le passe en <strong>incognito</strong> (invisible sur la carte et dans les évènements) ;</li>'
        . '<li>le passe en <strong>anonyme</strong> (introuvable dans les recherches de destinataires) ;</li>'
        . '<li>le <strong>déplace sur le plan « ' . e((new NpcAdminService())->getRetirePlan()) . ' »</strong> (case libre), hors du monde vivant.</li>'
        . '</ul>'
        . '<p class="text-muted">Réversible : réassignez-le à un joueur pour le réactiver.</p>'
        . '<form method="post" action="/admin/pnjs-save.php?action=retire" data-confirm="Retirer ce PNJ ?">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
        . '<button type="submit" class="btn btn-outline-danger">Retirer ce PNJ</button>'
        . '</form>') . '</div>';

    return $identity . $assign . $retire;
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrf = new CsrfProtectionService();
$csrfToken = $csrf->generateToken();
$service = new NpcAdminService();

$action = $_GET['action'] ?? 'list';

$options = ['styles' => ['/admin/css/pnjs.css'], 'back' => ['/admin/pnjs.php', 'PNJ']];
if ($action === 'new') {
    $title = 'Créer un PNJ';
    $content = pnj_render_create_form($csrfToken);
} elseif ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    $pnj = $service->getPnj($id);
    if ($pnj === null) {
        setFlash('warning', 'PNJ introuvable.');
        redirectTo('/admin/pnjs.php');
    }
    $title = e($pnj['name']) . ' <span class="text-muted">(#' . (int) $pnj['id'] . ')</span>';
    $content = pnj_render_edit_form($pnj, $service->getOwners($id), $csrfToken);
} else {
    // Only super-admins may edit the retirement plan (server-side guard is in
    // pnjs-save.php); plain admins see it read-only.
    $viewerIsSuperAdmin = AdminAuthorizationService::isSuperAdmin();
    $title = 'Gestion des PNJ';
    $options = ['styles' => ['/admin/css/pnjs.css'],
        'actions' => '<a class="btn btn-primary" href="/admin/pnjs.php?action=new">+ Créer un PNJ</a>'];
    $content = pnj_render_list($service->listPnjs(), $csrfToken, $viewerIsSuperAdmin);
}

echo admin_layout($title, $content, $options);
