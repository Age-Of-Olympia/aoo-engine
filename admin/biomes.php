<?php
/** Biomes: the types ("forêt") and the zones of each plan ("Forêt du Nord"). */
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Module\Module;
use App\Module\Modules;
use App\Service\CsrfProtectionService;
use App\Service\Map\BiomeAdminService;
use App\Service\Map\BiomeCellService;
use App\Service\Map\BiomeGrowthService;

$service = new BiomeAdminService();
$token = '<input type="hidden" name="csrf_token" value="' . e((new CsrfProtectionService())->generateToken()) . '">';
$plan = (string) ($_GET['plan'] ?? '');
$types = $service->types();
$typeChoices = array_column($types, 'label', 'id');

/**
 * A POST form to biomes-save.php with its action and hidden fields.
 *
 * @param array<string, int|string> $hidden
 */
function biome_form(string $token, string $action, string $body, array $hidden = [], string $attrs = ''): string
{
    $fields = '';
    foreach ($hidden + ['action' => $action] as $name => $value) {
        $fields .= '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">';
    }

    return '<form method="post" action="/admin/biomes-save.php"' . ($attrs !== '' ? ' ' . $attrs : '') . '>'
        . $token . $fields . $body . '</form>';
}

$content = '';
if (!Modules::on(Module::Biomes)) {
    $content .= '<div class="alert alert-warning">Le module Biomes est désactivé : les biomes n\'ont aucun effet en jeu.'
        . ' On l\'active dans <a href="/admin/modules.php">Administration › Modules</a>.</div>';
}

// Types
$terrains = $service->groundTerrains();
$paletteLink = static fn (int $typeId): string => '?' . e(http_build_query(array_filter(['plan' => $plan, 'palette' => $typeId]))) . '#palette';
$paletteSummary = static fn (array $type): string => '<div class="small text-muted mt-1">Sols : '
    . ($type['grounds'] === []
        ? 'tout sol'
        : e(implode(' · ', array_map(static fn (string $terrain, int $share): string => $terrain . ' ' . $share . ' %', array_keys($type['grounds']), $type['grounds']))))
    . ' — <a href="' . $paletteLink($type['id']) . '">Palette</a></div>';
$rows = [];
foreach ($types as $type) {
    $rows[] = '<tr><td>'
        . biome_form($token, 'save_type', '<div class="d-flex gap-2">'
            . formInput('name', $type['name'], 'class="form-control form-control-sm" pattern="' . BiomeAdminService::TYPE_NAME_PATTERN . '" required')
            . formInput('label', $type['label'], 'class="form-control form-control-sm" required')
            . formInput('color', $type['color'], 'type="color" class="form-control form-control-sm" style="width:4em"')
            . formInput('patch_scale', (string) $type['patch_scale'], 'type="number" min="1" max="' . BiomeAdminService::MAX_PATCH_SCALE
                . '" class="form-control form-control-sm" style="width:5em" title="Taille des plaques du pinceau, en cases"')
            . '<button class="btn btn-sm btn-primary" type="submit">Enregistrer</button></div>'
            . $paletteSummary($type),
            ['id' => $type['id']])
        . '</td><td>' . $type['biomes'] . '</td><td>'
        . ($type['biomes'] === 0
            ? biome_form($token, 'delete_type', '<button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button>',
                ['id' => $type['id']], 'data-confirm="' . e('Supprimer le type ' . $type['label'] . ' ?') . '"')
            : '')
        . '</td></tr>';
}
$addType = biome_form($token, 'save_type', '<div class="d-flex flex-wrap gap-2 align-items-end">'
    . formField('Nom technique', formInput('name', '', 'placeholder="foret" required'), 'mb-0')
    . formField('Libellé', formInput('label', '', 'placeholder="Forêt" required'), 'mb-0')
    . formField('Couleur', formInput('color', '#3f6b4f', 'type="color" style="width:4em"'), 'mb-0')
    . '<button class="btn btn-primary" type="submit">Ajouter le type</button></div>', ['id' => 0]);
$content .= formCard('Types de biome', ($types === []
        ? '<p class="text-muted">Aucun type pour l\'instant.</p>'
        : renderTable(['Nom technique, libellé, couleur, taille des plaques et sols', 'Biomes', ''], $rows))
    . '<p class="text-muted small">La case à côté de la couleur règle la taille des plaques du pinceau de biome, en cases.</p>'
    . '<hr>' . $addType);

// The palette of one type: up to MAX_GROUNDS terrains, shares adding up to 100 %
$paletteType = null;
foreach ($types as $type) {
    if ($type['id'] === (int) ($_GET['palette'] ?? 0)) {
        $paletteType = $type;
    }
}
if ($paletteType !== null) {
    $choices = array_combine($terrains, $terrains);
    $lines = array_slice(array_pad(array_map(null, array_keys($paletteType['grounds']), $paletteType['grounds']), BiomeAdminService::MAX_GROUNDS, [null, null]), 0, BiomeAdminService::MAX_GROUNDS);
    $paletteRows = '';
    foreach ($lines as [$terrain, $share]) {
        $paletteRows .= '<div class="form-row align-items-end mb-2">'
            . formField('Terrain', formSelect('terrain[]', $choices, $terrain, '— aucun —'), 'form-group col-md-5 mb-0')
            . formField('Part', formInput('share[]', $share === null ? '' : (string) $share, 'type="number" min="1" max="' . BiomeAdminService::FULL_PALETTE . '" placeholder="%"'), 'form-group col-md-2 mb-0')
            . '</div>';
    }
    $content .= '<div id="palette">' . formCard('Palette de ' . $paletteType['label'], $terrains === []
        ? '<p class="text-muted">Aucun terrain classé sur ce serveur. Classez les sols d\'un plan dans'
            . ' <a href="/admin/terrain-transitions.php">Transitions de terrain</a> : chaque terrain apparaît ensuite dans cette liste.</p>'
        : '<p class="text-muted small">Dans les biomes de ce type, les plantes ne poussent que sur ces terrains, leurs tuiles et les transitions'
            . ' entre eux ; le pinceau de biome les peint selon leurs parts. Au plus ' . BiomeAdminService::MAX_GROUNDS . ' terrains,'
            . ' dont toutes les transitions existent, et des parts qui font ' . BiomeAdminService::FULL_PALETTE . ' % tout juste.'
            . ' Sans aucun terrain, les plantes poussent sur tout sol.</p>'
            . biome_form($token, 'save_palette', $paletteRows
                . '<p id="palette-total" class="small"></p>'
                . '<button class="btn btn-primary" type="submit">Enregistrer la palette</button>'
                . ' <a class="btn btn-outline-secondary" href="?' . e(http_build_query(array_filter(['plan' => $plan]))) . '">Fermer</a>',
                ['id' => $paletteType['id'], 'plan_filter' => $plan, 'palette' => $paletteType['id']], 'id="palette-form"')
            . '<script>
                /* Live total of the shares whose terrain is chosen */
                (function () {
                    var form = document.getElementById("palette-form");
                    var total = document.getElementById("palette-total");
                    function refresh() {
                        var sum = 0;
                        form.querySelectorAll(".form-row").forEach(function (row) {
                            if (row.querySelector("select").value !== "") {
                                sum += parseInt(row.querySelector("input").value, 10) || 0;
                            }
                        });
                        total.textContent = "Total : " + sum + " %";
                        total.style.color = sum === ' . BiomeAdminService::FULL_PALETTE . ' || sum === 0 ? "" : "var(--danger, #c0392b)";
                    }
                    form.addEventListener("input", refresh);
                    form.addEventListener("change", refresh);
                    refresh();
                })();
            </script>'
    ) . '</div>';
}

// Biomes
$editId = (int) ($_GET['edit'] ?? 0);
$biomes = $service->biomes($plan);
$edited = null;
$rows = [];
foreach ($biomes as $biome) {
    if ($biome['id'] === $editId) {
        $edited = $biome;
    }
    $rows[] = '<tr><td><span class="badge" style="background:' . e($biome['color']) . '">&nbsp;</span> '
        . e($biome['name']) . '</td><td>' . e($biome['type']) . '</td><td>' . e($biome['plan']) . '</td>'
        . '<td>' . $biome['cells'] . '</td><td>' . $biome['stack_order'] . '</td><td class="text-nowrap">'
        . '<a class="btn btn-sm btn-outline-primary" href="?' . e(http_build_query(['plan' => $plan, 'edit' => $biome['id']])) . '#biome-form">Modifier</a> '
        . biome_form($token, 'delete_biome', '<button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button>',
            ['id' => $biome['id'], 'plan_filter' => $plan],
            'class="d-inline" data-confirm="' . e('Supprimer le biome ' . $biome['name'] . ' ?') . '"')
        . '</td></tr>';
}

// Transition tiles a single biome covers, per plan on the list
const LONE_TRANSITIONS_SHOWN = 5;
$loneWarnings = '';
$biomeCells = new BiomeCellService();
foreach (array_unique(array_column($biomes, 'plan')) as $biomePlan) {
    $lone = $biomeCells->loneTransitions($biomePlan);
    if ($lone === []) {
        continue;
    }
    $examples = implode(', ', array_map(
        static fn (array $cell): string => '(' . $cell[0] . ', ' . $cell[1] . ')',
        array_slice($lone, 0, LONE_TRANSITIONS_SHOWN)
    ));
    $loneWarnings .= '<div class="alert alert-warning">Plan ' . e($biomePlan) . ' : ' . count($lone)
        . ' tuile(s) de transition dans un seul biome, par exemple ' . $examples . (count($lone) > LONE_TRANSITIONS_SHOWN ? '…' : '') . '.'
        . ' Le sol y marque la rencontre de deux biomes, mais un seul contour couvre la case :'
        . ' en général, il manque une rangée au contour du biome voisin.'
        . ' Le calque Biomes de Tiled les affiche sans contour pointillé.</div>';
}

$filter = '<form method="get" class="d-flex gap-2 align-items-center mb-2">'
    . formSelect('plan', plan_choices(), $plan !== '' ? $plan : null, 'Plan : tous', 'class="form-control form-control-sm w-auto" data-autosubmit')
    . '<small class="text-muted">' . count($biomes) . ' biome(s)</small></form>';

$form = $types === []
    ? '<p class="text-muted">Créez d\'abord un type de biome.</p>'
    : biome_form($token, 'save_biome',
        '<div class="form-row">'
        . formField('Nom', formInput('name', $edited['name'] ?? '', 'placeholder="Forêt du Nord" required'), 'form-group col-md-4')
        . formField('Type', formSelect('type_id', $typeChoices, isset($edited) ? (string) array_search($edited['type'], $typeChoices, true) : null), 'form-group col-md-3')
        . formField('Plan', formSelect('plan', plan_choices(), $edited['plan'] ?? ($plan !== '' ? $plan : null)), 'form-group col-md-3')
        . formField('Empilement', formInput('stack_order', (string) ($edited['stack_order'] ?? 0), 'type="number"'), 'form-group col-md-2',
            'Là où des biomes se chevauchent, celui qui a l\'empilement le plus grand l\'emporte.'
            . ' Sur une tuile de transition, la case appartient à chacun d\'eux.')
        . '</div>'
        . formField('Graine du pinceau', formInput('seed', isset($edited) ? (string) $edited['seed'] : '', 'type="number" min="1" placeholder="tirée au hasard"'), 'form-group',
            'Le pinceau de biome tire ses plaques de sol à partir de cette graine : une autre graine donne un autre tirage.')
        . formField('Coins de la zone', formTextarea('zone', $edited['zone'] ?? '', 2, 'placeholder="0,0  10,0  10,8  0,8" required'), 'form-group',
            'Les coins du contour au format x,y, séparés par des espaces, dans l\'ordre où on en fait le tour.'
            . ' Les coordonnées désignent les angles des cases : la case (x, y) va de l\'angle x,y à l\'angle x+1,y+1.'
            . ' Ainsi, <code>0,0 10,0 10,8 0,8</code> couvre les cases de (0, 0) à (9, 7).'
            . '<br>Une case fait partie du biome quand son centre est dans le contour ou sur un de ses côtés.'
            . ' La page accepte toutes les formes (en L, en couloir, en spirale, avec des côtés en diagonale), tant que le contour ne se croise pas.'
            . '<br>Pour une clairière dans une forêt, entrez dans la clairière par un côté, faites-en le tour, puis ressortez par le même chemin :'
            . ' <code>0,0 10,0 10,10 0,10 0,4 4,4 4,6 6,6 6,4 4,4 0,4</code> laisse un trou de (4, 4) à (5, 5).'
            . ' Pour deux morceaux séparés, créez deux biomes du même type.')
        . '<button class="btn btn-primary" type="submit">' . ($edited ? 'Enregistrer' : 'Ajouter le biome') . '</button>'
        . ($edited ? ' <a class="btn btn-outline-secondary" href="?' . e(http_build_query(['plan' => $plan])) . '">Annuler</a>' : ''),
        ['id' => $edited['id'] ?? 0, 'plan_filter' => $plan], 'id="biome-form"');

// What grows in the biome being edited
$populationsCard = '';
if ($edited) {
    $hidden = ['id' => $edited['id'], 'edit' => $edited['id'], 'plan_filter' => $plan];
    $numbers = static fn (array $p): string =>
        formField('Chance : 1 sur', formInput('chance', (string) ($p['chance'] ?? 20), 'type="number" min="1" required'), 'form-group col-md-2 mb-0')
        . formField('Pour 100 cases', formInput('density', (string) ($p['density'] ?? 5), 'type="number" min="0.01" max="100" step="0.01" required'), 'form-group col-md-2 mb-0')
        . formField('z min', formInput('z_min', (string) ($p['z_min'] ?? 0), 'type="number" required'), 'form-group col-md-1 mb-0')
        . formField('z max', formInput('z_max', (string) ($p['z_max'] ?? 0), 'type="number" required'), 'form-group col-md-1 mb-0');
    $populationRows = [];
    $growth = new BiomeGrowthService();
    foreach ($service->populations($edited['id']) as $population) {
        $cap = $growth->cap(['biome_id' => $edited['id']] + $population);
        $populationRows[] = '<tr><td>' . e($population['label'])
            . '<br><small class="text-muted">≈ ' . $cap . ' aujourd\'hui</small></td><td>'
            . biome_form($token, 'save_population', '<div class="form-row align-items-end">' . $numbers($population)
                . '<div class="col-md-2"><button class="btn btn-sm btn-primary" type="submit">Enregistrer</button></div></div>',
                $hidden + ['race_id' => $population['race_id']])
            . '</td><td>'
            . biome_form($token, 'delete_population', '<button class="btn btn-sm btn-outline-danger" type="submit">Retirer</button>',
                $hidden + ['population_id' => $population['id']])
            . '</td></tr>';
    }
    $populationsCard = '<div id="populations">' . formCard('Ce qui pousse dans ' . $edited['name'],
        '<p class="text-muted">Chaque nuit, chaque case libre du biome (sol posé, rien dessus) a une chance sur N'
        . ' de voir pousser ce type, tant que le biome en compte moins que sa densité :'
        . ' 5 pour 100 cases, sur un biome de 300 cases avec du sol, arrête la pousse à 15, plantations des joueurs comprises.</p>'
        . ($populationRows === [] ? '<p class="text-muted">Rien ne pousse encore ici.</p>' : renderTable(['Type', 'Réglages', ''], $populationRows))
        . '<hr>' . biome_form($token, 'save_population', '<div class="form-row align-items-end">'
            . formField('Type', formSelect('race_id', $service->growableTypes(), null, '— Choisir —', 'required'), 'form-group col-md-4 mb-0')
            . $numbers([])
            . '<div class="col-md-2"><button class="btn btn-primary" type="submit">Ajouter</button></div></div>', $hidden)
    ) . '</div>';
}

$content .= formCard('Biomes', $loneWarnings . $filter
    . ($biomes === [] ? '<p class="text-muted">Aucun biome.</p>' : renderTable(['Biome', 'Type', 'Plan', 'Cases', 'Empilement', ''], $rows))
    . '<hr><h5>' . ($edited ? 'Modifier ' . e($edited['name']) : 'Nouveau biome') . '</h5>' . $form
    . ($edited ? biome_form($token, 'reseed_biome', '<button class="btn btn-sm btn-outline-secondary mt-2" type="submit" title="Tire une nouvelle graine : le prochain coup de pinceau de biome dessine d\'autres plaques">Nouveau tirage du pinceau</button>', ['id' => $edited['id'], 'plan_filter' => $plan, 'edit' => $edited['id']]) : ''))
    . $populationsCard;

echo admin_layout('Biomes', $content, [
    'intro' => 'Un biome est une zone d\'un plan avec ses propres règles, valable à tous les niveaux z.'
        . ' À chaque enregistrement, la page recalcule les cases sous l\'ancien et le nouveau contour du biome.',
]);
