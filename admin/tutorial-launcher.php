<?php
/**
 * Tutorial Launcher - Admin Tool
 *
 * Launches a specific tutorial version for testing purposes.
 * This bypasses normal tutorial start logic to allow testing any tutorial.
 */

require_once __DIR__ . '/../config.php';

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialContext;
use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialMapInstance;

header('Content-Type: application/json; charset=utf-8');

// Check admin access
if (empty($_SESSION['playerId'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$player = new \Classes\Player($_SESSION['playerId']);
$player->get_options();
if (empty($player->options->isAdmin)) {
    echo json_encode(['success' => false, 'error' => 'Accès admin requis']);
    exit;
}

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
    if (TutorialHelper::isTutorialActive()) {
        TutorialHelper::exitTutorialMode();
    }

    // Clear any existing tutorial session for this player
    $db->exe("UPDATE tutorial_progress SET completed = 1 WHERE player_id = ? AND completed = 0", [$_SESSION['playerId']]);

    // Get spawn coordinates from catalog
    $spawnX = $catalog['spawn_x'] ?? 0;
    $spawnY = $catalog['spawn_y'] ?? 0;
    $plan = $catalog['plan'] ?? 'tutorial';

    // Create tutorial context
    $context = new TutorialContext($_SESSION['playerId']);
    $manager = new TutorialManager($context);

    // Start the tutorial with specific version
    $result = $manager->startTutorial($version);

    if ($result['success']) {
        // Override spawn position if different from default
        if ($spawnX !== 0 || $spawnY !== 0) {
            $tutorialPlayerId = $_SESSION['tutorial_player_id'] ?? null;
            if ($tutorialPlayerId) {
                // Get or create coords for spawn position
                $coordsResult = $db->exe(
                    "SELECT id FROM coords WHERE x = ? AND y = ? AND z = 0 AND plan = ?",
                    [$spawnX, $spawnY, $plan]
                );
                $coords = $coordsResult->fetch_assoc();

                if (!$coords) {
                    $db->exe(
                        "INSERT INTO coords (x, y, z, plan) VALUES (?, ?, 0, ?)",
                        [$spawnX, $spawnY, $plan]
                    );
                    $coordsId = $db->exe("SELECT LAST_INSERT_ID() as id")->fetch_assoc()['id'];
                } else {
                    $coordsId = $coords['id'];
                }

                // Update tutorial player position
                $db->exe("UPDATE players SET coords_id = ? WHERE id = ?", [$coordsId, $tutorialPlayerId]);
            }
        }

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
