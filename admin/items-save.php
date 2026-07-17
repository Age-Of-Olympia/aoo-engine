<?php
/**
 * Items catalog — mutations (POST only). Companion to admin/items.php.
 *
 * Routed on ?action: update. CSRF-validated, same menu level as
 * items.php (a direct POST can't bypass the dashboard gate), PRG with
 * a flash. Only the DB-backed columns are writable — flags and wear
 * config; the JSON stats stay read-only until their migration.
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

if (($_GET['action'] ?? '') !== 'update') {
    setFlash('warning', 'Action inconnue.');
    redirectTo('/admin/items.php');
}

$id = (int) ($_POST['id'] ?? 0);
$db = new Db();

$res = $db->exe('SELECT id, name FROM items WHERE id = ?', $id);
if (!$res->num_rows) {
    setFlash('warning', 'Objet introuvable.');
    redirectTo('/admin/items.php');
}
$name = $res->fetch_object()->name;

// Déclencheurs d'usure : whitelist stricte.
$allowedTriggers = ['attack', 'defense', 'move', 'usage'];
$triggers = array_values(array_intersect(
    $allowedTriggers,
    array_map('strval', (array) ($_POST['wear_triggers'] ?? []))
));

$db->exe(
    'UPDATE items SET
        cursed = ?, enchanted = ?, vorpal = ?, is_bankable = ?, is_deprecated = ?,
        element = ?, spell = ?, exotique = ?,
        wear_triggers = ?, wear_rate = ?
     WHERE id = ?',
    [
        (int) !empty($_POST['cursed']),
        (int) !empty($_POST['enchanted']),
        (int) !empty($_POST['vorpal']),
        (int) !empty($_POST['is_bankable']),
        (int) !empty($_POST['is_deprecated']),
        trim((string) ($_POST['element'] ?? '')),
        trim((string) ($_POST['spell'] ?? '')),
        trim((string) ($_POST['exotique'] ?? '')),
        implode(',', $triggers),
        max(0, (int) ($_POST['wear_rate'] ?? 0)),
        $id,
    ]
);

setFlash('success', 'Objet « ' . $name . ' » enregistré.');
redirectTo('/admin/items.php?action=edit&id=' . $id);
