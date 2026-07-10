<?php
/**
 * PNJ management (admin dashboard → Joueurs → PNJ).
 *
 * Three views, routed on ?action:
 *   - list  (default): roster of every PNJ with owner(s), activity, XP, plus
 *            client-side filters (search / statut / affectation) and a create
 *            button. Each row → edit, or soft-retire (inline confirm form).
 *   - new   : create-a-PNJ form (name + race) → Player::put_player(..., pnj).
 *   - edit  : rename / change race, manage controlling owners (assign /
 *             unassign), and soft-retire.
 *
 * All mutations POST to pnjs-save.php (CSRF-validated, PRG). This page only
 * renders. Admin-gated by layout.php (DoAdminCheck).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\PnjAdminService;
use Classes\Player;

/**
 * Race options for the create/edit dropdowns — only races that can actually be
 * created (JSON + faction present), from the one source PnjAdminService owns and
 * the save endpoint validates against.
 *
 * @return array<string,string>
 */
function pnj_race_options(): array
{
    $out = [];
    foreach ((new PnjAdminService())->availableRaces() as $race) {
        $out[$race] = ucfirst($race);
    }
    return $out;
}

function pnj_status_badge(bool $active): string
{
    return $active
        ? '<span class="badge badge-success">Actif</span>'
        : '<span class="badge badge-warning">Inactif</span>';
}

/**
 * @param array<int, array{id:int, name:string, race:string, xp:int, lastLoginTime:int, active:bool, owners:?string, owner_count:int}> $pnjs
 */
function pnj_render_list(array $pnjs, string $csrfToken, bool $canEditRetirePlan): string
{
    $rows = '';
    foreach ($pnjs as $pnj) {
        $assigned = $pnj['owner_count'] > 0;
        $needle = strtolower($pnj['name'] . ' ' . $pnj['id']);
        $owners = $pnj['owners'] !== null ? e($pnj['owners']) : '<span class="text-muted">— non assigné —</span>';

        $retireForm = '<form method="post" action="/admin/pnjs-save.php?action=retire" style="display:inline"'
            . ' onsubmit="return confirm(\'Retirer ce PNJ ? Il sera désassigné de tous les joueurs, passé en incognito + anonyme et déplacé sur le plan des PNJ retirés. Réversible.\')">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">Retirer</button></form>';

        $rows .= '<tr data-filter="' . e($needle) . '"'
            . ' data-active="' . ($pnj['active'] ? '1' : '0') . '"'
            . ' data-assigned="' . ($assigned ? '1' : '0') . '">'
            . '<td>' . (int) $pnj['id'] . '</td>'
            . '<td>' . e($pnj['name']) . '</td>'
            . '<td>' . e(ucfirst($pnj['race'])) . '</td>'
            . '<td>' . pnj_status_badge($pnj['active']) . '</td>'
            . '<td>' . $owners . '</td>'
            . '<td>' . (int) $pnj['xp'] . '</td>'
            . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/pnjs.php?action=edit&amp;id='
            . (int) $pnj['id'] . '">Gérer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" href="/admin/player-skills.php?id='
            . (int) $pnj['id'] . '">Compétences</a> '
            . $retireForm
            . '</td>'
            . '</tr>';
    }
    if ($rows === '') {
        $rows = '<tr><td colspan="7" class="text-muted">Aucun PNJ.</td></tr>';
    }

    $filters = '<div class="d-flex flex-wrap mb-3" style="gap:.5rem">'
        . '<input type="search" id="pnj-filter" class="form-control" style="max-width:22rem"'
        . ' placeholder="Filtrer par nom ou matricule…" autocomplete="off">'
        . '<select id="pnj-status" class="form-control" style="max-width:12rem">'
        . '<option value="">Tous les statuts</option><option value="active">Actifs</option>'
        . '<option value="inactive">Inactifs</option></select>'
        . '<select id="pnj-assign" class="form-control" style="max-width:14rem">'
        . '<option value="">Assignés + non</option><option value="1">Assignés</option>'
        . '<option value="0">Non assignés</option></select>'
        . '</div>';

    // Settings: the plan retired PNJs are parked on (configurable, not hardcoded).
    $service = new PnjAdminService();
    $currentPlan = $service->getRetirePlan();
    $planOptions = '';
    foreach ($service->listPlans() as $plan) {
        $planOptions .= '<option value="' . e($plan) . '"></option>';
    }
    // Secondary setting: kept compact and collapsed so it doesn't crowd the page.
    // Editing the retirement plan is super-admin only; plain admins see it
    // read-only (enforcement also lives server-side in pnjs-save.php).
    if ($canEditRetirePlan) {
        $settingsCard = '<details class="pnj-retire-setting text-muted" style="margin-bottom:1rem;font-size:.9em">'
            . '<summary style="cursor:pointer">⚙ Plan des PNJ retirés : <strong>' . e($currentPlan) . '</strong></summary>'
            . '<form method="post" action="/admin/pnjs-save.php?action=set_retire_plan"'
            . ' class="d-flex flex-wrap align-items-center" style="gap:.5rem;margin-top:.5rem">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="text" name="retire_plan" list="pnj-plan-list" value="' . e($currentPlan) . '"'
            . ' class="form-control form-control-sm" style="max-width:14rem" required>'
            . '<datalist id="pnj-plan-list">' . $planOptions . '</datalist>'
            . '<button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>'
            . '<span>Les PNJ retirés y sont déplacés (case libre).</span>'
            . '</form></details>';
    } else {
        $settingsCard = '<p class="text-muted" style="margin-bottom:1rem;font-size:.9em">'
            . '⚙ Plan des PNJ retirés : <strong>' . e($currentPlan) . '</strong>'
            . ' <span style="font-size:.9em">(modifiable par un super-admin)</span></p>';
    }

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">Gestion des PNJ</h1>'
        . '<a class="btn btn-primary" href="/admin/pnjs.php?action=new">+ Créer un PNJ</a></div>'
        . $settingsCard
        . $filters
        . '<p class="text-muted mb-2"><span id="pnj-count">' . count($pnjs) . '</span> PNJ</p>'
        . '<table class="table table-striped table-hover" id="pnj-table">'
        . '<thead><tr><th>Matricule</th><th>Nom</th><th>Race</th><th>Statut</th>'
        . '<th>Contrôlé par</th><th>XP</th><th></th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>'
        . '<p class="text-muted" id="pnj-empty" style="display:none">Aucun PNJ ne correspond.</p>'
        . pnj_list_script();
}

function pnj_list_script(): string
{
    // Combines search + statut + affectation filters (all client-side).
    return '<script>(function(){'
        . 'var f=document.getElementById("pnj-filter"),st=document.getElementById("pnj-status"),'
        . 'as=document.getElementById("pnj-assign");'
        . 'var rows=[].slice.call(document.querySelectorAll("#pnj-table tbody tr[data-filter]"));'
        . 'var c=document.getElementById("pnj-count"),e=document.getElementById("pnj-empty");'
        . 'if(!f)return;'
        . 'function apply(){var q=f.value.trim().toLowerCase(),s=st?st.value:"",a=as?as.value:"",n=0;'
        . 'rows.forEach(function(r){'
        . 'var okT=q===""||r.getAttribute("data-filter").indexOf(q)!==-1;'
        . 'var act=r.getAttribute("data-active")==="1";'
        . 'var okS=s===""||(s==="active"?act:!act);'
        . 'var okA=a===""||r.getAttribute("data-assigned")===a;'
        . 'var m=okT&&okS&&okA;r.style.display=m?"":"none";if(m)n++;});'
        . 'if(c)c.textContent=n;if(e)e.style.display=n===0?"":"none";}'
        . 'f.addEventListener("input",apply);if(st)st.addEventListener("change",apply);'
        . 'if(as)as.addEventListener("change",apply);})();</script>';
}

function pnj_render_create_form(string $csrfToken): string
{
    $raceOptions = renderSelectOptions(pnj_race_options(), null, '— Choisir une race —');

    return '<h1 class="mb-3">Créer un PNJ</h1>'
        . '<a class="btn btn-sm btn-outline-secondary mb-3" href="/admin/pnjs.php">← Retour à la liste</a>'
        . '<form method="post" action="/admin/pnjs-save.php?action=create" class="card" style="max-width:32rem">'
        . '<div class="card-body" style="padding:16px">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<div class="mb-3"><label>Nom</label>'
        . '<input type="text" name="name" class="form-control" required maxlength="255" autofocus></div>'
        . '<div class="mb-3"><label>Race</label>'
        . '<select name="race" class="form-control" required>' . $raceOptions . '</select></div>'
        . '<button type="submit" class="btn btn-primary">Créer le PNJ</button>'
        . '</div></form>';
}

/**
 * @param array{id:int, name:string, race:string, xp:int, lastLoginTime:int} $pnj
 * @param array<int, array{player_id:int, name:string, displayed:bool}> $owners
 */
function pnj_render_edit_form(array $pnj, array $owners, string $csrfToken): string
{
    $raceOptions = renderSelectOptions(pnj_race_options(), $pnj['race']);

    // --- Identity form (rename / change race) ---
    $identity = '<form method="post" action="/admin/pnjs-save.php?action=update" class="card mb-3" style="max-width:32rem">'
        . '<div class="card-header"><h3 class="card-title">Identité</h3></div>'
        . '<div class="card-body" style="padding:16px">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
        . '<div class="mb-3"><label>Nom</label>'
        . '<input type="text" name="name" class="form-control" required maxlength="255" value="' . e($pnj['name']) . '"></div>'
        . '<div class="mb-3"><label>Race</label>'
        . '<select name="race" class="form-control" required>' . $raceOptions . '</select></div>'
        . '<button type="submit" class="btn btn-primary">Enregistrer</button>'
        . '</div></form>';

    // --- Owners list + unassign ---
    $ownerRows = '';
    foreach ($owners as $o) {
        $ownerRows .= '<tr><td>' . e($o['name']) . ' <span class="text-muted">(#' . (int) $o['player_id'] . ')</span></td>'
            . '<td><form method="post" action="/admin/pnjs-save.php?action=unassign" style="display:inline">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
            . '<input type="hidden" name="player_id" value="' . (int) $o['player_id'] . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">Désassigner</button></form></td></tr>';
    }
    if ($ownerRows === '') {
        $ownerRows = '<tr><td colspan="2" class="text-muted">Aucun joueur ne contrôle ce PNJ.</td></tr>';
    }

    $assign = '<div class="card mb-3" style="max-width:32rem">'
        . '<div class="card-header"><h3 class="card-title">Contrôlé par</h3></div>'
        . '<div class="card-body" style="padding:16px">'
        . '<table class="table table-sm"><tbody>' . $ownerRows . '</tbody></table>'
        . '<form method="post" action="/admin/pnjs-save.php?action=assign" class="d-flex" style="gap:.5rem;margin-top:8px">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
        . '<input type="text" name="player" class="form-control" placeholder="Matricule ou nom exact du joueur…" required>'
        . '<button type="submit" class="btn btn-secondary">Assigner</button>'
        . '</form></div></div>';

    // --- Danger zone: soft retire ---
    $retire = '<div class="card" style="max-width:32rem;border-color:var(--danger,#d93025)">'
        . '<div class="card-header"><h3 class="card-title">Retrait</h3></div>'
        . '<div class="card-body" style="padding:16px">'
        . '<p class="text-muted">Le retrait, sans rien supprimer :</p>'
        . '<ul class="text-muted" style="margin:0 0 10px 1.1rem">'
        . '<li>le <strong>désassigne</strong> de tous les joueurs qui le contrôlent ;</li>'
        . '<li>le passe en <strong>incognito</strong> (invisible sur la carte et dans les évènements) ;</li>'
        . '<li>le passe en <strong>anonyme</strong> (introuvable dans les recherches de destinataires) ;</li>'
        . '<li>le <strong>déplace sur le plan « ' . e((new PnjAdminService())->getRetirePlan()) . ' »</strong> (case libre), hors du monde vivant.</li>'
        . '</ul>'
        . '<p class="text-muted">Réversible : réassignez-le à un joueur pour le réactiver.</p>'
        . '<form method="post" action="/admin/pnjs-save.php?action=retire"'
        . ' onsubmit="return confirm(\'Retirer ce PNJ ?\')">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="pnj_id" value="' . (int) $pnj['id'] . '">'
        . '<button type="submit" class="btn btn-outline-danger">Retirer ce PNJ</button>'
        . '</form></div></div>';

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">' . e($pnj['name']) . ' <span class="text-muted">(#' . (int) $pnj['id'] . ')</span></h1>'
        . '<a class="btn btn-sm btn-outline-secondary" href="/admin/pnjs.php">← Retour à la liste</a></div>'
        . $identity . $assign . $retire;
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrf = new CsrfProtectionService();
$csrfToken = $csrf->generateToken();
$service = new PnjAdminService();

$action = $_GET['action'] ?? 'list';

if ($action === 'new') {
    $content = pnj_render_create_form($csrfToken);
} elseif ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    $pnj = $service->getPnj($id);
    if ($pnj === null) {
        setFlash('warning', 'PNJ introuvable.');
        redirectTo('/admin/pnjs.php');
    }
    $content = pnj_render_edit_form($pnj, $service->getOwners($id), $csrfToken);
} else {
    // Only super-admins may edit the retirement plan (server-side guard is in
    // pnjs-save.php); plain admins see it read-only.
    $viewerIsSuperAdmin = !empty($_SESSION['isSuperAdmin'])
        || (bool) (new Player($_SESSION['playerId']))->have_option('isSuperAdmin');
    $content = pnj_render_list($service->listPnjs(), $csrfToken, $viewerIsSuperAdmin);
}

echo admin_layout('PNJ', renderFlashMessage() . $content, [
    'styles' => ['/admin/css/pnjs.css'],
]);
