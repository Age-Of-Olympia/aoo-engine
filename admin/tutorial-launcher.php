<?php
/**
 * Tutorial Launcher - Admin Tool
 *
 * Launches a specific tutorial version for testing purposes.
 * This bypasses normal tutorial start logic to allow testing any tutorial.
 */

require_once __DIR__ . '/../config.php';

use App\Service\AdminAuthorizationService;
use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialHelper;

// Launching arbitrary tutorial versions is an admin-only test tool;
// players use the regular start flow via api/tutorial/start.php.
AdminAuthorizationService::DoAdminCheck();

header('Content-Type: application/json; charset=utf-8');

$version = $_GET['version'] ?? $_POST['version'] ?? '1.0.0';
$db = new \Classes\Db();

// Check if tutorial version exists in catalog
$catalog = $db->exe("SELECT * FROM tutorial_catalog WHERE version = ?", [$version])->fetch_assoc();
if (!$catalog) {
    echo json_encode(['success' => false, 'error' => "Version '$version' non trouvée dans le catalogue"]);
    exit;
}

// Check if steps exist for this version
$stepCount = $db->exe("SELECT COUNT(*) as cnt FROM tutorial_steps WHERE version = ? AND is_active = 1", [$version])->fetch_assoc();
if ($stepCount['cnt'] == 0) {
    echo json_encode([
        'success' => false,
        'error' => "Aucun step actif pour la version '$version'",
        'hint' => 'Créez des steps dans l\'éditeur avec cette version'
    ]);
    exit;
}

try {
    // Cancel any existing tutorial first
    if (TutorialHelper::isInTutorial()) {
        TutorialHelper::exitTutorialMode();
    }

    // Clear any existing tutorial session for this player
    $db->exe("UPDATE tutorial_progress SET completed = 1 WHERE player_id = ? AND completed = 0", [$_SESSION['playerId']]);

    // Load player and create tutorial manager. Spawn coords come from the
    // catalog and are honored natively by TutorialManager::startTutorial()
    // (via TutorialMapInstance::createInstance) — no post-create reposition
    // needed here anymore.
    $player = new \Classes\Player($_SESSION['playerId']);
    $player->get_data();
    $manager = new TutorialManager($player);

    // Start the tutorial with specific version
    $result = $manager->startTutorial($version);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Tutoriel '$catalog[name]' (v$version) lancé",
            'tutorial_name' => $catalog['name'],
            'version' => $version,
            'step_count' => $stepCount['cnt'],
            'redirect' => '/index.php'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Erreur inconnue',
            'details' => $result
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
