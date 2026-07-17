<?php
/**
 * Building management (admin dashboard → Bâtiments).
 *
 * One view: the roster of every placed building (position, état, PV,
 * propriétaire, faction) plus the place form. Companion of the console
 * command `building place|remove` — same BuildingService underneath
 * (docs/design-buildings-entities.md §4.7).
 *
 * Structure types are races rows of kind 'structure' created in
 * admin/races.php; their pv column is the building's max PV.
 *
 * All mutations POST to buildings-save.php (CSRF-validated, PRG). This page
 * only renders. Access enforced by layout.php via AdminMenuAccessService.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\BuildingService;
use App\Service\CsrfProtectionService;
use App\Service\DialogService;
use App\Service\FactionService;
use App\Service\PnjAdminService;
use App\Service\RaceService;

/**
 * Structure-type options: races rows of kind 'structure', name => label —
 * the same rule BuildingService::place() enforces. Character races
 * (playable or PNJ) never appear here.
 *
 * @return array<string,string>
 */
function building_type_options(): array
{
    $out = [];
    foreach ((new RaceService())->getRacesByKind(\App\Enum\EntityCategory::Structure->value) as $race) {
        $out[$race->getName()] = $race->getLabel();
    }
    ksort($out);
    return $out;
}

/**
 * Options <select> des dialogues du catalogue, '' = aucun.
 *
 * @param array<int, string> $dialogNames
 */
function building_dialog_select(string $fieldName, string $selected, array $dialogNames): string
{
    $options = '<option value="">— aucun —</option>';
    foreach ($dialogNames as $name) {
        $options .= '<option value="' . e($name) . '"' . ($name === $selected ? ' selected' : '') . '>'
            . e($name) . '</option>';
    }

    return '<select name="' . $fieldName . '" class="form-control form-control-sm d-inline-block" style="width:auto">'
        . $options . '</select>';
}

function building_state_badge(string $state): string
{
    return match ($state) {
        'built' => '<span class="badge badge-success">Construit</span>',
        'construction' => '<span class="badge badge-info">En construction</span>',
        'ruin' => '<span class="badge badge-danger">Ruine</span>',
        default => '<span class="badge badge-secondary">' . e($state) . '</span>',
    };
}

/**
 * @param array<int, array<string, mixed>> $buildings BuildingService::listBuildings() rows
 * @param array<int, string>               $dialogNames
 */
function building_render_list(array $buildings, array $dialogNames, string $csrfToken): string
{
    if ($buildings === []) {
        return '<p class="text-muted">Aucun bâtiment posé pour le moment.</p>';
    }

    $raceService = new RaceService();

    $rows = '';
    foreach ($buildings as $b) {
        $pvCell = $b['current_pv'] . ' / ' . $b['max_pv'];
        if ($b['current_pv'] < $b['max_pv']) {
            $pvCell = '<span class="text-danger">' . $pvCell . '</span>';
        }

        // La porte est le propre des ÉDIFICES ; un mur construit n'en a
        // pas (son is_open = future passabilité, pas de bascule ici).
        $isEdifice = (bool) $raceService->getRaceByName((string) $b['type'])?->isEdifice();

        $actions = '';
        if ($isEdifice) {
            $actions .= '<form method="post" action="/admin/buildings-save.php?action=toggle-open" class="d-inline">'
                . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
                . '<input type="hidden" name="id" value="' . (int) $b['id'] . '">'
                . '<input type="hidden" name="open" value="' . ($b['is_open'] ? 0 : 1) . '">'
                . '<button class="btn btn-sm btn-outline-' . ($b['is_open'] ? 'secondary' : 'success') . '" type="submit"'
                . ' title="Fermeture volontaire : le bâtiment tait son dialogue (il ferme aussi d\'office endommagé, en construction ou en ruine)">'
                . ($b['is_open'] ? 'Fermer' : 'Ouvrir') . '</button>'
                . '</form> ';
        }
        if ($b['current_pv'] < $b['max_pv'] || $b['build_state'] !== 'built') {
            $actions .= '<form method="post" action="/admin/buildings-save.php?action=restore" class="d-inline">'
                . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
                . '<input type="hidden" name="id" value="' . (int) $b['id'] . '">'
                . '<button class="btn btn-sm btn-outline-primary" type="submit" title="PV au maximum, état construit — remise à neuf admin, distincte de la future action de réparation en jeu">Restaurer</button>'
                . '</form> ';
        }
        $actions .= '<form method="post" action="/admin/buildings-save.php?action=remove" class="d-inline"'
            . ' onsubmit="return confirm(' . e(json_encode('Retirer définitivement ' . $b['name'] . ' #' . (int) $b['id'] . ' ?', JSON_UNESCAPED_UNICODE)) . ');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="id" value="' . (int) $b['id'] . '">'
            . '<button class="btn btn-sm btn-outline-danger" type="submit">Retirer</button>'
            . '</form>';

        $rows .= '<tr>'
            . '<td>' . (int) $b['id'] . '</td>'
            . '<td>' . e($b['name']) . '</td>'
            . '<td>' . e($b['type']) . '</td>'
            . '<td>' . building_state_badge($b['build_state'])
            . ($isEdifice && !$b['is_open'] ? ' <span class="badge badge-dark" title="Fermeture volontaire — le dialogue se tait">Fermé</span>' : '') . '</td>'
            . '<td>' . $pvCell . '</td>'
            . '<td>(' . (int) $b['x'] . ', ' . (int) $b['y'] . ') · ' . e($b['plan']) . '</td>'
            . '<td>' . ($b['owner_name'] !== null ? e($b['owner_name']) . ' <small class="text-muted">#' . (int) $b['owner_id'] . '</small>' : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . ($b['faction'] !== '' ? e($b['faction']) : '<span class="text-muted">—</span>') . '</td>'
            . '<td class="text-nowrap"><form method="post" action="/admin/buildings-save.php?action=dialog" class="d-inline">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="id" value="' . (int) $b['id'] . '">'
            . building_dialog_select('dialog', (string) $b['dialog'], $dialogNames)
            . ' <button class="btn btn-sm btn-outline-primary" type="submit"'
            . ' title="Dialogue porté par le bâtiment — muet en construction ou en ruine">OK</button>'
            . '</form></td>'
            . '<td class="text-nowrap">' . $actions . '</td>'
            . '</tr>';
    }

    return '<div class="table-responsive"><table class="table table-sm table-striped align-middle">'
        . '<thead><tr><th>#</th><th>Nom</th><th>Type</th><th>État</th><th>PV</th>'
        . '<th>Position</th><th>Propriétaire</th><th>Faction</th><th>Dialogue</th><th>Actions</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>';
}

/**
 * @param array<string,string> $types
 * @param array<int,string>    $plans
 * @param array<string,string> $factions code => name
 * @param array<int,string>    $dialogNames
 */
function building_render_place_form(array $types, array $plans, array $factions, array $dialogNames, string $csrfToken): string
{
    if ($types === []) {
        return '<div class="alert alert-warning">Aucun type de structure disponible : créez-en un'
            . ' dans <a href="/admin/races.php">Races</a> (sorte « Structure », renseignez ses PV).</div>';
    }

    $typeOptions = '';
    foreach ($types as $name => $label) {
        $typeOptions .= '<option value="' . e($name) . '">' . e($label) . ' (' . e($name) . ')</option>';
    }

    $planOptions = '';
    foreach ($plans as $plan) {
        $selected = $plan === 'gaia' ? ' selected' : '';
        $planOptions .= '<option value="' . e($plan) . '"' . $selected . '>' . e($plan) . '</option>';
    }

    $factionOptions = '<option value="">— neutre —</option>';
    foreach ($factions as $code => $label) {
        $factionOptions .= '<option value="' . e($code) . '">' . e($label) . '</option>';
    }

    $body = '<form method="post" action="/admin/buildings-save.php?action=place" class="row g-2">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<div class="col-md-3"><label class="form-label">Type</label>'
        . '<select name="type" class="form-control" required>' . $typeOptions . '</select>'
        . '<small class="text-muted">Entrée du catalogue races (sorte « Structure ») — ses PV = PV max du bâtiment.</small></div>'
        . '<div class="col-md-3"><label class="form-label">Nom (optionnel)</label>'
        . '<input type="text" name="name" class="form-control" maxlength="255" placeholder="Libellé de la race par défaut"></div>'
        . '<div class="col-md-1"><label class="form-label">X</label>'
        . '<input type="number" name="x" class="form-control" required></div>'
        . '<div class="col-md-1"><label class="form-label">Y</label>'
        . '<input type="number" name="y" class="form-control" required></div>'
        . '<div class="col-md-2"><label class="form-label">Plan</label>'
        . '<select name="plan" class="form-control">' . $planOptions . '</select></div>'
        . '<div class="col-md-2"><label class="form-label">Propriétaire (optionnel)</label>'
        . '<input type="text" name="owner" class="form-control" placeholder="matricule ou nom"></div>'
        . '<div class="col-md-2"><label class="form-label">Faction (optionnel)</label>'
        . '<select name="faction" class="form-control">' . $factionOptions . '</select></div>'
        . '<div class="col-md-2"><label class="form-label">Dialogue (optionnel)</label>'
        . building_dialog_select('dialog', '', $dialogNames)
        . '<small class="text-muted d-block">Porté par le bâtiment — muet en ruine.</small></div>'
        . '<div class="col-12"><button class="btn btn-primary" type="submit">Poser le bâtiment</button></div>'
        . '</form>';

    return '<div class="card mb-4"><div class="card-header">Poser un bâtiment</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$service = new BuildingService();

$factions = [];
foreach ((new FactionService())->getAllFactions() as $faction) {
    $factions[$faction->getCode()] = $faction->getName();
}

$dialogNames = array_keys((new DialogService())->listGameDialogs());

$content = building_render_place_form(
    building_type_options(),
    (new PnjAdminService())->listPlans(),
    $factions,
    $dialogNames,
    $csrfToken
) . building_render_list($service->listBuildings(), $dialogNames, $csrfToken);

echo admin_layout('Bâtiments', renderFlashMessage() . $content);
