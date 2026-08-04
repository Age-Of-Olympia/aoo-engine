<?php
/**
 * Recettes d'artisanat — mutations (POST only). Compagnon de
 * admin/recipes.php. Routé sur ?action : create | update | delete.
 * CSRF, même niveau de menu, PRG. Sémantique remplace-tout pour les
 * lignes (ingrédients / résultats / races re-écrits à chaque save).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use Classes\Db;

(new AdminMenuAccessService())->enforce('recipes.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/recipes.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/recipes.php');
}

$action = $_GET['action'] ?? '';
$db = new Db();

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    foreach (['craft_recipes_ingredients', 'craft_recipes_results', 'race_recipes'] as $table) {
        $db->exe("DELETE FROM {$table} WHERE recipe_id = ?", $id);
    }
    $affected = $db->exe('DELETE FROM craft_recipes WHERE id = ?', $id, false, true);
    if ($affected > 0) {
        setFlash('success', 'Recette supprimée.');
    } else {
        setFlash('warning', "Aucune recette #{$id}.");
    }
    redirectTo('/admin/recipes.php');
}

if ($action !== 'create' && $action !== 'update') {
    setFlash('warning', 'Action inconnue.');
    redirectTo('/admin/recipes.php');
}

$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '') {
    setFlash('warning', 'Nom de recette requis.');
    redirectTo('/admin/recipes.php');
}

/** Lignes item/qté validées : item existant, qté >= 1. */
$collect = static function (string $itemKey, string $countKey) use ($db): array {
    $out = [];
    foreach ((array) ($_POST[$itemKey] ?? []) as $i => $itemId) {
        $itemId = (int) $itemId;
        $count = max(1, (int) (($_POST[$countKey] ?? [])[$i] ?? 1));
        if ($itemId <= 0) {
            continue;
        }
        $exists = $db->exe('SELECT id FROM items WHERE id = ?', $itemId);
        if (!$exists->num_rows) {
            continue;
        }
        $out[$itemId] = ($out[$itemId] ?? 0) + $count;
    }
    return $out;
};

$ingredients = $collect('ing_item', 'ing_count');
$results = $collect('res_item', 'res_count');

/* Required workshop: empty = basic recipe (NULL). A name is validated
 * against the building-type catalog — a typo would make the recipe
 * craftable nowhere. */
$workshop = trim((string) ($_POST['workshop'] ?? ''));
if ($workshop !== '') {
    $known = $db->exe("SELECT id FROM races WHERE name = ? AND type_kind = 'building'", $workshop);
    if (!$known->num_rows) {
        setFlash('warning', "Type de bâtiment inconnu : « {$workshop} ».");
        redirectTo('/admin/recipes.php');
    }
}

if ($ingredients === [] || $results === []) {
    setFlash('warning', 'Une recette exige au moins un ingrédient et un résultat.');
    redirectTo('/admin/recipes.php');
}

if ($action === 'create') {
    // Unicité par nom (clé naturelle des bundles export/import) : un
    // doublon rendrait l'export « ?name= » ambigu.
    $dup = $db->exe('SELECT id FROM craft_recipes WHERE name = ?', $name);
    if ($dup->num_rows) {
        setFlash('warning', "Une recette « {$name} » existe déjà.");
        redirectTo('/admin/recipes.php?action=new');
    }
    $db->exe('INSERT INTO craft_recipes (name, workshop) VALUES (?, ?)', [$name, $workshop !== '' ? $workshop : null]);
    $id = $db->insertId();
} else {
    $id = (int) ($_POST['id'] ?? 0);
    $exists = $db->exe('SELECT id FROM craft_recipes WHERE id = ?', $id);
    if (!$exists->num_rows) {
        setFlash('warning', 'Recette introuvable.');
        redirectTo('/admin/recipes.php');
    }
    $db->exe('UPDATE craft_recipes SET name = ?, workshop = ? WHERE id = ?', [$name, $workshop !== '' ? $workshop : null, $id]);
}

foreach (['craft_recipes_ingredients', 'craft_recipes_results', 'race_recipes'] as $table) {
    $db->exe("DELETE FROM {$table} WHERE recipe_id = ?", $id);
}
foreach ($ingredients as $itemId => $count) {
    $db->exe('INSERT INTO craft_recipes_ingredients (count, recipe_id, item_id) VALUES (?, ?, ?)', [$count, $id, $itemId]);
}
foreach ($results as $itemId => $count) {
    $db->exe('INSERT INTO craft_recipes_results (count, recipe_id, item_id) VALUES (?, ?, ?)', [$count, $id, $itemId]);
}
foreach ((array) ($_POST['races'] ?? []) as $raceId) {
    $raceId = (int) $raceId;
    if ($raceId > 0) {
        $db->exe('INSERT IGNORE INTO race_recipes (race_id, recipe_id) VALUES (?, ?)', [$raceId, $id]);
    }
}

setFlash('success', 'Recette « ' . $name . ' » enregistrée.');
redirectTo('/admin/recipes.php?action=edit&id=' . $id);
