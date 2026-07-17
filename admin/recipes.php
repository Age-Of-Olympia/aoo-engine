<?php
/**
 * Recettes d'artisanat (admin dashboard → Objets → Recettes).
 *
 * CRUD complet sur les tables craft_recipes / _ingredients / _results
 * / race_recipes — remplace le dump brut view_recipes.php. Une recette
 * sans race est disponible pour toutes (contrat de
 * RecipeService::getRecipes : ra.id IS NULL).
 *
 * Mutations dans recipes-save.php (CSRF, PRG). Accès via layout.php
 * (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use Classes\Db;

const RECIPE_INGREDIENT_SLOTS = 5;
const RECIPE_RESULT_SLOTS = 2;

/** @return array<int, string> item id => name, trié */
function recipe_item_options(): array
{
    $out = [];
    $res = (new Db())->exe('SELECT id, name FROM items ORDER BY name');
    while ($row = $res->fetch_object()) {
        $out[(int) $row->id] = $row->name;
    }
    return $out;
}

/** @return array<int, object> */
function recipe_rows(string $table, int $recipeId): array
{
    $out = [];
    $res = (new Db())->exe("SELECT item_id, count FROM {$table} WHERE recipe_id = ?", $recipeId);
    while ($row = $res->fetch_object()) {
        $out[] = $row;
    }
    return $out;
}

function recipe_render_list(string $csrfToken): string
{
    $db = new Db();
    $res = $db->exe(
        'SELECT r.id, r.name,
            (SELECT GROUP_CONCAT(CONCAT(i.name, " x", ri.count) SEPARATOR ", ")
             FROM craft_recipes_ingredients ri JOIN items i ON i.id = ri.item_id WHERE ri.recipe_id = r.id) AS ingredients,
            (SELECT GROUP_CONCAT(CONCAT(i.name, " x", rr.count) SEPARATOR ", ")
             FROM craft_recipes_results rr JOIN items i ON i.id = rr.item_id WHERE rr.recipe_id = r.id) AS results,
            (SELECT GROUP_CONCAT(ra.name SEPARATOR ", ")
             FROM race_recipes rr2 JOIN races ra ON ra.id = rr2.race_id WHERE rr2.recipe_id = r.id) AS races
         FROM craft_recipes r ORDER BY r.name'
    );

    $rows = '';
    while ($r = $res->fetch_object()) {
        $rows .= '<tr>'
            . '<td><code>' . e($r->name) . '</code></td>'
            . '<td>' . e((string) ($r->ingredients ?? '—')) . '</td>'
            . '<td>' . e((string) ($r->results ?? '—')) . '</td>'
            . '<td>' . ($r->races ? e($r->races) : '<span class="text-muted">toutes</span>') . '</td>'
            . '<td class="text-nowrap">'
            . '<a class="btn btn-sm btn-outline-primary" href="/admin/recipes.php?action=edit&id=' . (int) $r->id . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter le bundle JSON"'
            . ' href="/admin/action-export.php?type=recipe&name=' . e(urlencode($r->name)) . '">JSON</a> '
            . '<form method="post" action="/admin/recipes-save.php?action=delete" class="d-inline"'
            . ' onsubmit="return confirm(' . e(json_encode('Supprimer la recette ' . $r->name . ' ?', JSON_UNESCAPED_UNICODE)) . ');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="id" value="' . (int) $r->id . '">'
            . '<button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button></form>'
            . '</td></tr>';
    }

    return '<p><a class="btn btn-primary" href="/admin/recipes.php?action=new">Nouvelle recette</a> '
        . '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=recipe">Exporter tout (JSON)</a> '
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php">Importer</a></p>'
        . '<div class="table-responsive"><table class="table table-sm table-striped align-middle">'
        . '<thead><tr><th>Recette</th><th>Ingrédients</th><th>Résultats</th><th>Races</th><th></th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>';
}

function recipe_item_select(string $name, int $selected, array $items): string
{
    $options = '<option value="0">—</option>';
    foreach ($items as $id => $label) {
        $options .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return '<select name="' . $name . '" class="form-control form-control-sm d-inline-block" style="width:auto">' . $options . '</select>';
}

function recipe_render_form(?object $recipe, string $csrfToken): string
{
    $items = recipe_item_options();
    $isEdit = $recipe !== null;

    $ingredients = $isEdit ? recipe_rows('craft_recipes_ingredients', (int) $recipe->id) : [];
    $results = $isEdit ? recipe_rows('craft_recipes_results', (int) $recipe->id) : [];

    $recipeRaces = [];
    if ($isEdit) {
        $res = (new Db())->exe('SELECT race_id FROM race_recipes WHERE recipe_id = ?', (int) $recipe->id);
        while ($row = $res->fetch_object()) {
            $recipeRaces[] = (int) $row->race_id;
        }
    }

    /* Toujours au moins un slot vide de plus que l'existant : la
     * sauvegarde est remplace-tout, un formulaire plus court que la
     * recette perdrait silencieusement les lignes excédentaires. */
    $ingredientSlots = max(RECIPE_INGREDIENT_SLOTS, count($ingredients) + 1);
    $resultSlots = max(RECIPE_RESULT_SLOTS, count($results) + 1);

    $ingredientRows = '';
    for ($i = 0; $i < $ingredientSlots; $i++) {
        $sel = isset($ingredients[$i]) ? (int) $ingredients[$i]->item_id : 0;
        $count = isset($ingredients[$i]) ? (int) $ingredients[$i]->count : 1;
        $ingredientRows .= '<div class="mb-1">' . recipe_item_select("ing_item[{$i}]", $sel, $items)
            . ' × <input type="number" min="1" name="ing_count[' . $i . ']" value="' . $count . '"'
            . ' class="form-control form-control-sm d-inline-block" style="width:80px"></div>';
    }

    $resultRows = '';
    for ($i = 0; $i < $resultSlots; $i++) {
        $sel = isset($results[$i]) ? (int) $results[$i]->item_id : 0;
        $count = isset($results[$i]) ? (int) $results[$i]->count : 1;
        $resultRows .= '<div class="mb-1">' . recipe_item_select("res_item[{$i}]", $sel, $items)
            . ' × <input type="number" min="1" name="res_count[' . $i . ']" value="' . $count . '"'
            . ' class="form-control form-control-sm d-inline-block" style="width:80px"></div>';
    }

    $raceBoxes = '';
    $racesRes = (new Db())->exe("SELECT id, name, label FROM races WHERE kind = 'character' ORDER BY name");
    while ($race = $racesRes->fetch_object()) {
        $raceBoxes .= '<label class="mr-3"><input type="checkbox" name="races[]" value="' . (int) $race->id . '" '
            . checked(in_array((int) $race->id, $recipeRaces, true)) . '> ' . e($race->label) . '</label> ';
    }

    $body = '<form method="post" action="/admin/recipes-save.php?action=' . ($isEdit ? 'update' : 'create') . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . ($isEdit ? '<input type="hidden" name="id" value="' . (int) $recipe->id . '">' : '')
        . '<div class="form-group"><label>Nom</label>'
        . '<input type="text" class="form-control" name="name" required maxlength="255" value="' . e($isEdit ? $recipe->name : '') . '"></div>'
        . '<div class="row"><div class="col-md-4"><h5>Ingrédients</h5>' . $ingredientRows . '</div>'
        . '<div class="col-md-4"><h5>Résultats</h5>' . $resultRows . '</div>'
        . '<div class="col-md-4"><h5>Races <small class="text-muted">(aucune = toutes)</small></h5>' . $raceBoxes . '</div></div>'
        . '<button class="btn btn-primary" type="submit">Enregistrer</button> '
        . '<a class="btn btn-secondary" href="/admin/recipes.php">Retour</a>'
        . '</form>';

    return '<div class="card"><div class="card-header">' . ($isEdit ? 'Recette « ' . e($recipe->name) . ' »' : 'Nouvelle recette') . '</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$action = $_GET['action'] ?? 'list';

if ($action === 'new') {
    $content = recipe_render_form(null, $csrfToken);
} elseif ($action === 'edit') {
    $res = (new Db())->exe('SELECT * FROM craft_recipes WHERE id = ?', (int) ($_GET['id'] ?? 0));
    $recipe = $res->num_rows ? $res->fetch_object() : null;
    if ($recipe === null) {
        setFlash('warning', 'Recette introuvable.');
        redirectTo('/admin/recipes.php');
    }
    $content = recipe_render_form($recipe, $csrfToken);
} else {
    $content = recipe_render_list($csrfToken);
}

echo admin_layout('Recettes', renderFlashMessage() . $content);
