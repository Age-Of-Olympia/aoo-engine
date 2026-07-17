<?php
/**
 * Items catalog — mutations (POST only). Companion to admin/items.php.
 *
 * Routed on ?action: create | update. CSRF-validated, same menu level
 * as items.php (a direct POST can't bypass the dashboard gate), PRG
 * with a flash. `create` insère la ligne par son nom (clé naturelle,
 * unique) puis partage le chemin d'update complet — l'objet créé est
 * d'emblée stats_in_db=1, la base est sa source.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use Classes\Db;

(new AdminMenuAccessService())->enforce('items.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/items.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/items.php');
}

$action = $_GET['action'] ?? '';
if ($action !== 'update' && $action !== 'create') {
    setFlash('warning', 'Action inconnue.');
    redirectTo('/admin/items.php');
}

$db = new Db();

if ($action === 'create') {
    $name = strtolower(trim((string) ($_POST['new_name'] ?? '')));
    if ($name === '' || !preg_match('/^[a-z0-9_\/-]+$/', $name)) {
        setFlash('warning', 'Nom d\'objet requis (minuscules, chiffres, _ / -).');
        redirectTo('/admin/items.php?action=new');
    }
    $exists = $db->exe('SELECT id FROM items WHERE name = ?', $name);
    if ($exists->num_rows) {
        setFlash('warning', "Un objet « {$name} » existe déjà.");
        redirectTo('/admin/items.php?action=new');
    }
    $db->exe('INSERT INTO items (name) VALUES (?)', $name);
    $id = (int) $db->exe('SELECT id FROM items WHERE name = ?', $name)->fetch_object()->id;
} else {
    $id = (int) ($_POST['id'] ?? 0);
    $res = $db->exe('SELECT id, name FROM items WHERE id = ?', $id);
    if (!$res->num_rows) {
        setFlash('warning', 'Objet introuvable.');
        redirectTo('/admin/items.php');
    }
    $name = $res->fetch_object()->name;
}

// Déclencheurs d'usure : whitelist stricte (source unique).
$triggers = array_values(array_intersect(
    \Classes\Item::WEAR_TRIGGERS,
    array_map('strval', (array) ($_POST['wear_triggers'] ?? []))
));

// Colonnes JSON : validées ou refusées — jamais de JSON cassé en base.
$jsonColumns = [];
foreach (\Classes\Item::JSON_COLUMNS as $col) {
    $raw = trim((string) ($_POST[$col] ?? ''));
    if ($raw === '') {
        $jsonColumns[$col] = null;
        continue;
    }
    if (json_decode($raw) === null && strtolower($raw) !== 'null') {
        setFlash('warning', "Champ {$col} : JSON invalide, rien n'a été enregistré.");
        redirectTo('/admin/items.php?action=edit&id=' . $id);
    }
    $jsonColumns[$col] = $raw;
}

// Munitions : liste de noms d'objets, stockée en JSON — chaque nom doit
// exister au catalogue (une faute de frappe rendrait l'arme muette).
$munitions = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['munitions'] ?? '')))));
foreach ($munitions as $munition) {
    if (!$db->exe('SELECT id FROM items WHERE name = ?', $munition)->num_rows) {
        setFlash('warning', "Munition inconnue au catalogue : « {$munition} » — rien n'a été enregistré.");
        redirectTo('/admin/items.php?action=edit&id=' . $id);
    }
}

$set = [];
$params = [];

// Flags booléens — mêmes clés que les cases du formulaire (Item::FLAG_KEYS).
foreach (\Classes\Item::FLAG_KEYS as $flag) {
    $set[] = "`{$flag}` = ?";
    $params[] = (int) !empty($_POST[$flag]);
}

$set = array_merge($set, [
    'element = ?', 'spell = ?', 'exotique = ?',
    'wear_triggers = ?', 'wear_rate = ?', 'durability_max = ?',
    'text = ?', 'price = ?', 'emplacement = ?', 'type = ?', 'subtype = ?', 'race = ?',
    'munitions = ?', 'add_effects = ?', 'forbid = ?', 'extra = ?',
    'stats_in_db = 1',
]);
$params = array_merge($params, [
    trim((string) ($_POST['element'] ?? '')),
    trim((string) ($_POST['spell'] ?? '')),
    trim((string) ($_POST['exotique'] ?? '')),
    implode(',', $triggers),
    max(0, (int) ($_POST['wear_rate'] ?? 0)),
    max(1, (int) ($_POST['durability_max'] ?? 100)),
    trim((string) ($_POST['text'] ?? '')),
    max(0, (int) ($_POST['price'] ?? 1)),
    trim((string) ($_POST['emplacement'] ?? '')),
    trim((string) ($_POST['type'] ?? '')),
    trim((string) ($_POST['subtype'] ?? '')),
    trim((string) ($_POST['race'] ?? '')),
    $munitions === [] ? null : json_encode($munitions, JSON_UNESCAPED_UNICODE),
    $jsonColumns['add_effects'],
    $jsonColumns['forbid'],
    $jsonColumns['extra'],
]);

foreach (array_merge(\App\Enum\Caracs::KEYS, \Classes\Item::SPECIAL_KEYS) as $key) {
    $set[] = "`{$key}` = ?";
    $params[] = (int) ($_POST[$key] ?? 0);
}

$params[] = $id;
$db->exe('UPDATE items SET ' . implode(', ', $set) . ' WHERE id = ?', $params);

setFlash('success', 'Objet « ' . $name . ' » enregistré — la base est sa source de vérité.');
redirectTo('/admin/items.php?action=edit&id=' . $id);
