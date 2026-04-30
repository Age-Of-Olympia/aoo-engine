<?php
/**
 * Tutorial NPCs save handler — create / update / delete a row in
 * tutorial_npcs. POST-only, CSRF-gated, admin-only.
 */

require_once __DIR__ . '/../config.php';

use Classes\Db;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$csrf = new CsrfProtectionService();
try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (RuntimeException $e) {
    http_response_code(403);
    exit($e->getMessage());
}

$db = new Db();
$action = $_GET['action'] ?? 'save';

/* Delete branch */
if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id > 0) {
        $db->exe("DELETE FROM tutorial_npcs WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "NPC #{$id} supprimé."];
    }
    header('Location: tutorial-npcs.php');
    exit;
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
if ($race === '' || (defined('RACES_EXT') && !in_array($race, RACES_EXT, true))) {
    $errors[] = 'Race invalide.';
}
if ($avatar === '') $errors[] = 'Avatar requis.';
if ($portrait === '') $errors[] = 'Portrait requis.';
// spawn_at_step_id only meaningful for dynamic — silently null it for template
if ($spawnMode === 'template') {
    $spawnAtStepId = null;
}

if (!empty($errors)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Erreurs : ' . implode(' / ', $errors)];
    $back = $id ? "tutorial-npcs.php?action=edit&id={$id}" : 'tutorial-npcs.php?action=new';
    header("Location: {$back}");
    exit;
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
    $_SESSION['flash'] = ['type' => 'success', 'message' => "NPC #{$id} mis à jour."];
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
    $_SESSION['flash'] = ['type' => 'success', 'message' => "NPC créé."];
}

header('Location: tutorial-npcs.php');
exit;
