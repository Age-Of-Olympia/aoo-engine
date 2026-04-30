<?php
/**
 * Tutorial Launcher - Admin Tool
 *
 * Launches a specific tutorial version for testing purposes.
 * This bypasses normal tutorial start logic to allow testing any tutorial.
 */

require_once __DIR__ . '/../config.php';

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialHelper;

// Launching arbitrary tutorial versions is an admin-only test tool;
// players use the regular start flow via api/tutorial/start.php.
AdminAuthorizationService::DoAdminCheck();

header('Content-Type: application/json; charset=utf-8');

// State-changing endpoint: clears the admin's active tutorial and
// starts a new one. Reject non-POST so an `<img src="…?version=…">`
// embedded in any page the admin loads cannot silently fire this.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF gate: pair with the token rendered by tutorial-catalog.php on
// the "Lancer" button. Validation fires BEFORE the UPDATE that wipes
// the admin's prior tutorial_progress row.
$csrf = new CsrfProtectionService();
try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (RuntimeException $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$version = $_POST['version'] ?? '1.0.0';
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

    // Race override for admin testing.
    //
    // In prod, admins are NPC characters (id<0) often with race='animal'
    // (or another non-playable race). TutorialPlayerFactory defaults the
    // tutorial-character race to the launching player's race and then
    // validates it against RACES_EXT — so an admin testing a tutorial
    // would otherwise get an InvalidArgumentException ("Invalid race
    // 'animal'..."), surfaced as the generic "failed to create tutorial".
    //
    // Override to a sane playable race when the launcher's own race
    // isn't tutorial-compatible. Real players with playable races keep
    // their own race (admin testing as a real test character still
    // works as before).
    $launcherRace = strtolower($player->data->race ?? '');
    $raceOverride = in_array($launcherRace, RACES_EXT, true) ? null : 'nain';

    // Start the tutorial with specific version (and race override if any)
    $result = $manager->startTutorial($version, $raceOverride);

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
    error_log(sprintf(
        '[tutorial-launcher] start failed for player %d (race=%s) on version %s: %s',
        $_SESSION['playerId'] ?? 0,
        $player->data->race ?? '?',
        $version,
        $e->getMessage()
    ));

    // Surface a clearer message when the underlying issue is the
    // invalid-race path; everything else is reported verbatim. The
    // raw message + trace stay in the JSON for debugging in the
    // admin console.
    $rawMessage = $e->getMessage();
    if (stripos($rawMessage, 'Invalid race') !== false) {
        $friendly = "Race '" . ($player->data->race ?? '?') . "' incompatible avec le tutoriel. "
                  . "Le launcher tente normalement un override automatique vers 'nain' — "
                  . "vérifiez que la chaîne d'override n'a pas été contournée.";
    } else {
        $friendly = $rawMessage;
    }

    echo json_encode([
        'success' => false,
        'error' => $friendly,
        'raw_error' => $rawMessage,
        'trace' => $e->getTraceAsString()
    ]);
}
