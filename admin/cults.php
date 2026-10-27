<?php
/** Cult management: prayable gods, their followers and consecrated altars. */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CultAdminService;
use App\Service\CsrfProtectionService;
use App\Service\RaceService;

const CULTS_PER_PAGE = 50;

/** @param array{id:int,name:string,player_type:string,x:int,y:int,z:int,plan:string}|null $god
 *  @param array<string, string> $plans
 */
function cult_render_form(?array $god, array $plans, string $csrfToken): string
{
    $isEdit = $god !== null;
    $plan = $isEdit ? (string) $god['plan'] : (string) (array_key_first($plans) ?? '');
    $position = $isEdit ? $god : ['x' => 0, 'y' => 0, 'z' => 0];
    $form = '<form method="post" action="/admin/cults-save.php" class="row g-2">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="action" value="' . ($isEdit ? 'edit' : 'add') . '">'
        . ($isEdit ? '<input type="hidden" name="id" value="' . (int) $god['id'] . '">' : '')
        . '<div class="col-md-4"><label class="form-label">Nom du dieu</label>'
        . '<input type="text" name="name" class="form-control" maxlength="255" required value="'
        . e($isEdit ? (string) $god['name'] : '') . '"></div>'
        . '<div class="col-md-1"><label class="form-label">X</label><input type="number" name="x" class="form-control" required value="' . (int) $position['x'] . '"></div>'
        . '<div class="col-md-1"><label class="form-label">Y</label><input type="number" name="y" class="form-control" required value="' . (int) $position['y'] . '"></div>'
        . '<div class="col-md-1"><label class="form-label">Z</label><input type="number" name="z" class="form-control" required value="' . (int) $position['z'] . '"></div>'
        . '<div class="col-md-3"><label class="form-label">Plan</label>'
        . formSelect('plan', $plans, $plan, null, 'class="form-control" required') . '</div>'
        . '<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary" type="submit">'
        . ($isEdit ? 'Enregistrer' : 'Ajouter le culte') . '</button></div>'
        . '</form>';

    return '<div class="card mb-4"><div class="card-header">' . ($isEdit ? 'Éditer la divinité' : 'Ajouter un culte')
        . '</div><div class="card-body">' . $form . '</div></div>';
}

/**
 * @param array{x:int,y:int,z:int,plan:string} $row
 * @param array<string, string> $planLabels
 */
function cult_position(array $row, array $planLabels): string
{
    return '(' . (int) $row['x'] . ', ' . (int) $row['y'] . ', ' . (int) $row['z'] . ') · '
        . e($planLabels[$row['plan']] ?? $row['plan']);
}

/**
 * @param array{plan:string,q:string} $filters
 * @param list<string> $plans
 */
function cult_render_filters(array $filters, array $plans, int $total): string
{
    $planLabels = array_intersect_key(plan_choices(), array_flip($plans));
    return '<form method="get" action="/admin/cults.php" class="d-flex flex-wrap gap-2 align-items-center mb-2">'
        . formSelect('plan', $planLabels, $filters['plan'] !== '' ? $filters['plan'] : null, 'Plan : tous', 'class="form-control form-control-sm w-auto" data-autosubmit')
        . '<input type="search" name="q" class="form-control form-control-sm" style="width:14em" placeholder="Nom ou matricule" value="' . e($filters['q']) . '">'
        . '<button class="btn btn-sm btn-outline-secondary" type="submit">Filtrer</button>'
        . '<small class="text-muted">' . $total . ' culte(s)</small></form>';
}

/** @param list<array{id:int,name:string,player_type:string,x:int,y:int,z:int,plan:string}> $gods
 *  @param array<string, string> $planLabels
 */
function cult_render_list(array $gods, string $csrfToken, array $planLabels): string
{
    if ($gods === []) {
        return '<p class="text-muted">Aucun culte pour ces filtres.</p>';
    }

    $rows = '';
    foreach ($gods as $god) {
        $id = (int) $god['id'];
        $actions = '<a class="btn btn-sm btn-outline-primary" href="/admin/cults.php?action=edit&amp;id=' . $id . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" href="/admin/cults.php?action=view&amp;id=' . $id . '">Consulter</a> '
            . '<form method="post" action="/admin/cults-save.php" class="d-inline" data-confirm="'
            . e('Retirer ' . $god['name'] . ' #' . $id . ' ? Ses fidèles perdront leur dieu et tous leurs PF ; les autels seront désaffiliés.') . '">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-sm btn-outline-danger" type="submit">Retirer</button></form>';
        $rows .= '<tr><td>' . e((string) $god['name']) . '</td><td>' . $id . '</td><td>' . cult_position($god, $planLabels)
            . '</td><td class="text-nowrap">' . $actions . '</td></tr>';
    }

    return '<div class="table-responsive"><table class="table table-sm table-striped align-middle">'
        . '<thead><tr><th>Dieu</th><th>Matricule</th><th>Position</th><th>Actions</th></tr></thead><tbody>'
        . $rows . '</tbody></table></div>';
}

/** @param array{id:int,name:string,player_type:string,x:int,y:int,z:int,plan:string} $god
 *  @param array<string, string> $planLabels
 */
function cult_render_details(CultAdminService $service, array $god, array $planLabels): string
{
    $raceService = new RaceService();
    $followers = '';
    $followerRows = $service->followers((int) $god['id']);
    foreach ($followerRows as $follower) {
        $race = $raceService->getRaceByName($follower['race'])?->getLabel() ?? $follower['race'];
        $position = $follower['x'] === null ? '<span class="text-muted">—</span>' : cult_position($follower, $planLabels);
        $followers .= '<tr><td>' . e($follower['name']) . ' <small class="text-muted">#' . $follower['id'] . '</small></td>'
            . '<td>' . e($race) . '</td><td>' . $position . '</td><td>' . $follower['pf'] . '</td></tr>';
    }
    $altars = '';
    $altarRows = $service->altars((int) $god['id']);
    foreach ($altarRows as $altar) {
        $altars .= '<tr><td>' . e($altar['name']) . ' <small class="text-muted">#' . $altar['id'] . '</small></td>'
            . '<td>' . cult_position($altar, $planLabels) . '</td><td>' . $altar['pv'] . '</td></tr>';
    }

    $followersTable = $followers !== ''
        ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Fidèle</th><th>Race</th><th>Position</th><th>PF</th></tr></thead><tbody>' . $followers . '</tbody></table></div>'
        : '<p class="text-muted mb-0">Aucun fidèle.</p>';
    $altarsTable = $altars !== ''
        ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Autel</th><th>Position</th><th>PV</th></tr></thead><tbody>' . $altars . '</tbody></table></div>'
        : '<p class="text-muted mb-0">Aucun autel affilié.</p>';

    return '<div class="card mb-3"><div class="card-header">Fidèles (' . count($followerRows) . ')</div><div class="card-body">' . $followersTable . '</div></div>'
        . '<div class="card"><div class="card-header">Autels affiliés (' . count($altarRows) . ')</div><div class="card-body">' . $altarsTable . '</div></div>';
}

$service = new CultAdminService();
$csrfToken = (new CsrfProtectionService())->generateToken();
$plans = $service->plans();
$planLabels = array_intersect_key(plan_choices(), array_flip($plans));
$action = (string) ($_GET['action'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

if ($action === 'edit' || $action === 'view') {
    $god = $service->getGod($id);
    if ($god === null) {
        setFlash('warning', "Aucun culte pour le dieu #{$id}.");
        redirectTo('/admin/cults.php');
    }
    $content = $action === 'edit'
        ? cult_render_form($god, $planLabels, $csrfToken)
        : cult_render_details($service, $god, $planLabels);
    echo admin_layout(
        ($action === 'edit' ? 'Éditer · ' : 'Consulter · ') . e($god['name']) . ' <span class="text-muted">#' . $id . '</span>',
        $content,
        ['back' => ['/admin/cults.php', 'Cultes']]
    );
    exit();
}

$filters = [
    'plan' => (string) ($_GET['plan'] ?? ''),
    'q' => trim((string) ($_GET['q'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = $service->countGods($filters);
$pages = max(1, (int) ceil($total / CULTS_PER_PAGE));
$page = min($page, $pages);
$content = cult_render_form(null, $planLabels, $csrfToken)
    . cult_render_filters($filters, $plans, $total)
    . cult_render_list($service->listGods($filters, CULTS_PER_PAGE, ($page - 1) * CULTS_PER_PAGE), $csrfToken, $planLabels);
if ($pages > 1) {
    $query = array_filter($filters, static fn (string $value): bool => $value !== '');
    $content .= '<div class="d-flex align-items-center gap-2 mt-2">'
        . ($page > 1 ? '<a class="btn btn-sm btn-outline-secondary" href="?'
            . e(http_build_query($query + ['page' => $page - 1])) . '">← Précédent</a>' : '')
        . '<span class="text-muted">page ' . $page . ' / ' . $pages . '</span>'
        . ($page < $pages ? '<a class="btn btn-sm btn-outline-secondary" href="?'
            . e(http_build_query($query + ['page' => $page + 1])) . '">Suivant →</a>' : '')
        . '</div>';
}

echo admin_layout('Cultes', $content, [
    'intro' => 'Gestion des dieux qui peuvent être vénérés, de leurs fidèles et de leurs autels.',
]);
