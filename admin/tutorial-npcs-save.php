<?php
/**
 * Tutorial NPCs save handler — create / update / delete a row in
 * tutorial_npcs. POST-only, CSRF-gated, admin-only.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('tutorial.npcs');

requireAdminPost('/admin/tutorial-npcs.php');

$db = new Db();
$action = $_GET['action'] ?? 'save';

/* Delete branch */
if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id > 0) {
        $db->exe("DELETE FROM tutorial_npcs WHERE id = ?", [$id]);
        setFlash('success', "NPC #{$id} supprimé.");
    }
    redirectTo('/admin/tutorial-npcs.php');
}

/* Save (create or update) */
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$version = trim($_POST['version'] ?? '1.0.0');
$role = trim($_POST['role'] ?? '');
$spawnMode = $_POST['spawn_mode'] ?? '';
$x = (int) ($_POST['x'] ?? 0);
$y = (int) ($_POST['y'] ?? 0);
$name = trim($_POST['name'] ?? '');
$race = strtolower(trim($_POST['race'] ?? ''));
$avatar = trim($_POST['avatar'] ?? '');
$portrait = trim($_POST['portrait'] ?? '');
$faction = trim($_POST['faction'] ?? '');
$text = $_POST['text'] ?? '';
$energie = max(1, (int) ($_POST['energie'] ?? 100));
$spawnAtStepId = ($_POST['spawn_at_step_id'] ?? '') !== '' ? (int) $_POST['spawn_at_step_id'] : null;
$isActive = isset($_POST['is_active']) ? 1 : 0;

// Validation
$errors = [];
if ($role === '') $errors[] = 'Rôle requis.';
if (!in_array($spawnMode, ['template', 'dynamic'], true)) $errors[] = 'Mode de spawn invalide.';
if ($name === '') $errors[] = 'Nom requis.';
if ($race === '' || (new \App\Service\RaceService())->getRaceByName($race) === null) {
    $errors[] = 'Race invalide.';
}
if ($avatar === '') $errors[] = 'Avatar requis.';
if ($portrait === '') $errors[] = 'Portrait requis.';
// spawn_at_step_id only meaningful for dynamic — silently null it for template
if ($spawnMode === 'template') {
    $spawnAtStepId = null;
}

if (!empty($errors)) {
    setFlash('danger', 'Erreurs : ' . implode(' / ', $errors));
    redirectTo($id ? "/admin/tutorial-npcs.php?action=edit&id={$id}" : '/admin/tutorial-npcs.php?action=new');
}

if ($id) {
    $db->exe(
        "UPDATE tutorial_npcs SET
            version = ?, role = ?, spawn_mode = ?, x = ?, y = ?,
            name = ?, race = ?, avatar = ?, portrait = ?, faction = ?,
            text = ?, energie = ?, spawn_at_step_id = ?, is_active = ?
         WHERE id = ?",
        [
            $version, $role, $spawnMode, $x, $y,
            $name, $race, $avatar, $portrait, $faction,
            $text, $energie, $spawnAtStepId, $isActive,
            $id,
        ]
    );
    setFlash('success', "NPC #{$id} mis à jour.");
} else {
    $db->exe(
        "INSERT INTO tutorial_npcs
            (version, role, spawn_mode, x, y, name, race, avatar, portrait, faction, text, energie, spawn_at_step_id, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $version, $role, $spawnMode, $x, $y,
            $name, $race, $avatar, $portrait, $faction,
            $text, $energie, $spawnAtStepId, $isActive,
        ]
    );
    setFlash('success', "NPC créé.");
}

redirectTo('/admin/tutorial-npcs.php');
