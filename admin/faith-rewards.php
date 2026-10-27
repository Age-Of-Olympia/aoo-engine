<?php
/** Catalogue of objects available as faith rewards. */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\FaithRewardService;

const FAITH_REWARD_TYPE_LABELS = [
    'consommable' => 'Consommables',
    'equipement' => 'Équipement',
    'constructible' => 'Constructibles',
    'structure' => 'Structures (ancien type)',
    'matiere' => 'Matières',
    'quete' => 'Objets de quête',
    '' => 'Sans type',
];

function faith_reward_type_label(string $type): string
{
    return FAITH_REWARD_TYPE_LABELS[$type] ?? ($type === '' ? 'Sans type' : ucfirst(str_replace('_', ' ', $type)));
}

$service = new FaithRewardService();
$csrfToken = (new CsrfProtectionService())->generateToken();
$additionalTypes = $service->additionalTypes();
$monthlyRewardCount = $service->monthlyRewardCount();
$rawTypes = $_GET['types'] ?? [];
$includeTypes = is_array($rawTypes)
    ? array_values(array_intersect(array_map('strval', $rawTypes), $additionalTypes))
    : [];
$query = trim((string) ($_GET['q'] ?? ''));
$availableItems = $service->availableItems($includeTypes, $query);

$typeOptions = '';
foreach ($additionalTypes as $type) {
    $checked = in_array($type, $includeTypes, true) ? ' checked' : '';
    $typeOptions .= '<label class="form-check form-check-inline mr-3">'
        . '<input class="form-check-input" type="checkbox" name="types[]" value="' . e($type) . '"'
        . ' data-autosubmit' . $checked . '> <span class="form-check-label">Inclure ' . e(faith_reward_type_label($type)) . '</span></label>';
}

$filters = '<form method="get" action="/admin/faith-rewards.php" class="mb-3">'
    . '<div class="mb-2"><strong>Types d’objets à proposer</strong> '
    . '<small class="text-muted">Les consommables sont toujours inclus.</small></div>'
    . ($typeOptions !== '' ? $typeOptions : '<p class="text-muted">Aucun autre type d’objet dans le catalogue.</p>')
    . '<div class="form-inline mt-2"><input type="search" name="q" class="form-control form-control-sm mr-2"'
    . ' placeholder="Rechercher un objet" value="' . e($query) . '">'
    . '<button class="btn btn-sm btn-outline-secondary" type="submit">Rechercher</button></div></form>';

$selectOptions = [];
foreach ($availableItems as $item) {
    $selectOptions[$item['id']] = $item['label'] . ' — ' . faith_reward_type_label($item['type']) . ' (#' . $item['id'] . ')';
}
$addForm = '<form method="post" action="/admin/faith-rewards-save.php" class="form-inline">'
    . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
    . '<input type="hidden" name="action" value="add">'
    . implode('', array_map(static fn (string $type): string => '<input type="hidden" name="include_types[]" value="' . e($type) . '">', $includeTypes))
    . formSelect('item_id', $selectOptions, null, '— choisir un objet —', 'class="form-control form-control-sm mr-2" required')
    . '<label class="mr-2">Quantité <input type="number" name="quantity" class="form-control form-control-sm d-inline-block"'
    . ' style="width:7rem" min="1" max="2147483647" value="1" required></label> '
    . '<button class="btn btn-sm btn-primary" type="submit"' . ($selectOptions === [] ? ' disabled' : '')
    . '>Ajouter aux récompenses</button></form>';

$rows = '';
$rewards = $service->rewards();
foreach ($rewards as $reward) {
    $rows .= '<tr><td>' . e($reward['label']) . ' <small class="text-muted">#' . $reward['id'] . '</small></td>'
        . '<td>' . e(faith_reward_type_label($reward['type'])) . '</td><td>'
        . '<form method="post" action="/admin/faith-rewards-save.php" class="form-inline">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="action" value="quantity"><input type="hidden" name="item_id" value="' . $reward['id'] . '">'
        . '<input type="number" name="quantity" class="form-control form-control-sm mr-2" min="1" max="2147483647" required value="' . $reward['quantity'] . '">'
        . '<button class="btn btn-sm btn-outline-primary" type="submit">Enregistrer</button></form></td><td class="text-right">'
        . '<form method="post" action="/admin/faith-rewards-save.php" data-confirm="'
        . e('Retirer « ' . $reward['label'] . ' » de la liste des récompenses ?') . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="' . $reward['id'] . '">'
        . '<button class="btn btn-sm btn-outline-danger" type="submit">Retirer</button></form></td></tr>';
}
$rewardList = $rows !== ''
    ? '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Objet</th><th>Type</th><th>Quantité</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
    : '<p class="text-muted mb-0">Aucun objet dans la liste des récompenses.</p>';

$monthlyCountForm = '<form method="post" action="/admin/faith-rewards-save.php" class="form-inline">'
    . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
    . '<input type="hidden" name="action" value="monthly-count">'
    . '<label class="mr-2">Nombre de récompenses à distribuer chaque mois'
    . '<input type="number" name="monthly_reward_count" class="form-control form-control-sm ml-2" style="width:7rem" min="0" max="1000" value="' . $monthlyRewardCount . '" required></label>'
    . '<button class="btn btn-sm btn-primary" type="submit">Enregistrer</button></form>';

$content = '<div class="card mb-4"><div class="card-header">Distribution mensuelle</div>'
    . '<div class="card-body">' . $monthlyCountForm . '<small class="form-text text-muted mt-2">0 désactive la distribution mensuelle.</small></div></div>'
    . '<div class="card mb-4"><div class="card-header">Ajouter un objet à la liste des récompenses</div>'
    . '<div class="card-body">' . $filters . $addForm . '</div></div>'
    . '<div class="card"><div class="card-header">Objets pouvant être obtenus en récompense (' . count($rewards) . ')</div>'
    . '<div class="card-body">' . $rewardList . '</div></div>';

echo admin_layout('Récompenses', $content, [
    'intro' => 'Catalogue des objets qui pourront être obtenus en récompense de foi.',
]);
