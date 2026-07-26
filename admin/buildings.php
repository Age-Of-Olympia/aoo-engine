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
use App\Service\NpcAdminService;
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
    return formSelect(
        $fieldName,
        array_combine($dialogNames, $dialogNames),
        $selected !== '' ? $selected : null,
        '— aucun —',
        'class="form-control form-control-sm d-inline-block" style="width:auto"'
    );
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
            . '<td>(' . (int) $b['x'] . ', ' . (int) $b['y'] . ', ' . (int) $b['z'] . ') · ' . e($b['plan']) . '</td>'
            . '<td>' . ($b['owner_name'] !== null ? e($b['owner_name']) . ' <small class="text-muted">#' . (int) $b['owner_id'] . '</small>' : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . ($b['faction'] !== '' ? e($b['faction']) : '<span class="text-muted">—</span>') . '</td>'
            . '<td>' . ($b['dialog'] !== '' ? '<code>' . e($b['dialog']) . '</code>' : '<span class="text-muted">—</span>') . '</td>'
            . '<td class="text-nowrap">'
            . '<a class="btn btn-sm btn-outline-primary" href="/admin/buildings.php?action=edit&id=' . (int) $b['id'] . '">Éditer</a> '
            . $actions . '</td>'
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

    $typeLabels = [];
    foreach ($types as $name => $label) {
        $typeLabels[$name] = $label . ' (' . $name . ')';
    }
    $typeOptions = renderSelectOptions($typeLabels);
    $planOptions = renderSelectOptions(array_combine($plans, $plans), 'gaia');
    $factionOptions = renderSelectOptions($factions, null, '— neutre —');

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
        . '<div class="col-md-1"><label class="form-label">Z</label>'
        . '<input type="number" name="z" class="form-control" required value="0"'
        . ' title="Niveau : 0 = surface, négatif = souterrains, positif = étages"></div>'
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

/**
 * Formulaire d'édition d'un bâtiment posé : nom, description (le
 * « message du jour » du bâtiment), dialogue, porte, propriétaire,
 * faction. L'état (PV, build_state) reste géré par les boutons de la
 * liste (Restaurer) — ici on édite l'IDENTITÉ.
 *
 * @param array<string, mixed>  $b           ligne de listBuildings()
 * @param array<string, string> $factions    code => nom
 * @param array<int, string>    $dialogNames
 */
function building_render_edit(array $b, string $description, array $factions, array $dialogNames, bool $isEdifice, string $csrfToken): string
{
    $factionOptions = renderSelectOptions($factions, $b['faction'] !== '' ? (string) $b['faction'] : null, '— neutre —');

    $body = '<form method="post" action="/admin/buildings-save.php?action=edit">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="id" value="' . (int) $b['id'] . '">'
        . '<div class="row">'
        . '<div class="form-group col-md-4"><label>Nom affiché</label>'
        . '<input type="text" class="form-control" name="name" maxlength="255" value="' . e($b['name']) . '"'
        . ' placeholder="Libellé du type par défaut"></div>'
        . '<div class="form-group col-md-4"><label>Propriétaire</label>'
        . '<input type="text" class="form-control" name="owner" placeholder="matricule ou nom — vide : aucun"'
        . ' value="' . ($b['owner_id'] !== null ? (int) $b['owner_id'] : '') . '"></div>'
        . '<div class="form-group col-md-4"><label>Faction</label>'
        . '<select name="faction" class="form-control">' . $factionOptions . '</select></div>'
        . '</div>'
        . '<div class="form-group"><label>Description</label>'
        . '<textarea class="form-control" name="text" rows="4" placeholder="Visible sur la carte et la fiche — l\'équivalent du message du jour.">'
        . e($description) . '</textarea></div>'
        . '<div class="row">'
        . '<div class="form-group col-md-4"><label>Dialogue</label><div>'
        . building_dialog_select('dialog', (string) $b['dialog'], $dialogNames)
        . '</div><small class="text-muted">Porté par le bâtiment — muet quand il est fermé.</small></div>'
        . '<div class="form-group col-md-4"><label>Inscription lisible de loin</label>'
        . '<select name="readable_from_afar" class="form-control">'
        . '<option value="">Comme son type</option>'
        . '<option value="1"' . (($b['readable_from_afar'] ?? null) === true ? ' selected' : '') . '>Oui</option>'
        . '<option value="0"' . (($b['readable_from_afar'] ?? null) === false ? ' selected' : '') . '>Non, il faut s\'approcher</option>'
        . '</select><small class="text-muted">Le défaut vient du type (console des Races) ;'
        . ' ce réglage n\'est qu\'une exception pour CET exemplaire.</small></div>'
        . ($isEdifice
            ? '<div class="form-group col-md-4"><label>Porte</label>'
                . '<input type="hidden" name="has_door" value="1">'
                . '<div><label><input type="checkbox" name="is_open" ' . checked((bool) $b['is_open']) . '> Ouvert</label></div>'
                . '<small class="text-muted">Fermé volontairement, le bâtiment tait son dialogue (il ferme aussi'
                . ' d\'office endommagé, en construction ou en ruine).</small></div>'
            : '')
        . '</div>'
        . '<button class="btn btn-primary" type="submit">Enregistrer</button> '
        . '<a class="btn btn-secondary" href="/admin/buildings.php">Retour</a>'
        . '</form>';

    return '<div class="card"><div class="card-header">' . e($b['name']) . ' <code>#' . (int) $b['id'] . '</code>'
        . ' — ' . e($b['type']) . ' en (' . (int) $b['x'] . ', ' . (int) $b['y'] . ', ' . (int) $b['z'] . ') · ' . e($b['plan'])
        . '</div><div class="card-body">' . $body . '</div></div>';
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

if (($_GET['action'] ?? '') === 'edit') {
    $editId = (int) ($_GET['id'] ?? 0);
    $row = null;
    foreach ($service->listBuildings() as $b) {
        if ($b['id'] === $editId) {
            $row = $b;
            break;
        }
    }
    if ($row === null) {
        setFlash('warning', "Aucun bâtiment #{$editId}.");
        redirectTo('/admin/buildings.php');
    }
    $description = (string) (new \Classes\Db())
        ->exe('SELECT text FROM players WHERE id = ?', $editId)->fetch_object()->text;
    $isEdifice = (bool) (new RaceService())->getRaceByName((string) $row['type'])?->isEdifice();

    echo admin_layout('Bâtiments posés', renderFlashMessage()
        . building_render_edit($row, $description, $factions, $dialogNames, $isEdifice, $csrfToken));
    exit();
}

$content = building_render_place_form(
    building_type_options(),
    (new NpcAdminService())->listPlans(),
    $factions,
    $dialogNames,
    $csrfToken
) . building_render_list($service->listBuildings(), $dialogNames, $csrfToken);

echo admin_layout('Bâtiments posés', renderFlashMessage() . $content);
