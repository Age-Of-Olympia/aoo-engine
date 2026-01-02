<?php
/**
 * Test starting crafting tutorial
 */

define('NO_LOGIN', true);
require_once __DIR__ . '/../../config.php';

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialHelper;
use Classes\Player;
use Classes\Db;

header('Content-Type: text/plain; charset=utf-8');

$db = new Db();

// Use TestAdmin
$_SESSION['playerId'] = 100;

echo "=== Testing Start Crafting Tutorial ===\n\n";

// 1. Cancel any existing tutorial
echo "1. Cleaning up...\n";
if (TutorialHelper::isInTutorial()) {
    TutorialHelper::exitTutorialMode();
    echo "   Exited existing tutorial mode\n";
} else {
    echo "   No existing tutorial\n";
}

$db->exe("UPDATE tutorial_progress SET completed = 1 WHERE player_id = 100 AND completed = 0");
echo "   Marked sessions complete\n";

// 2. Load player
echo "\n2. Loading player...\n";
$player = new Player(100);
$player->get_data();
echo "   Player: {$player->data->name} (ID: {$player->id})\n";

// 3. Create manager (it creates its own context internally)
echo "\n3. Creating tutorial manager...\n";
$manager = new TutorialManager($player);
echo "   Done\n";

// 4. Start tutorial
echo "\n4. Starting tutorial...\n";
try {
    $result = $manager->startTutorial('2.0.0-craft');
    echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";

    if ($result['success']) {
        echo "   Session ID: {$result['session_id']}\n";
        echo "   Current step: {$result['current_step']}\n";
        echo "   Tutorial player ID: " . ($_SESSION['tutorial_player_id'] ?? 'N/A') . "\n";
    } else {
        echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
        if (isset($result['debug'])) {
            echo "   Debug: {$result['debug']}\n";
        }
    }

    // 5. Test JSON output for API
    echo "\n5. Testing API JSON output...\n";
    $apiOutput = json_encode($result, JSON_UNESCAPED_UNICODE);
    if ($apiOutput === false) {
        echo "   ERROR: JSON failed: " . json_last_error_msg() . "\n";
    } else {
        echo "   JSON OK (" . strlen($apiOutput) . " bytes)\n";
    }

    // 6. Get step data for client
    if ($result['success']) {
        echo "\n6. Getting step for client...\n";
        // getCurrentStepForClient expects step_number (int), not step_id (string)
        $clientData = $manager->getCurrentStepForClient(1, '2.0.0-craft');
        if ($clientData) {
            $clientJson = json_encode($clientData, JSON_UNESCAPED_UNICODE);
            if ($clientJson === false) {
                echo "   ERROR: Client JSON failed: " . json_last_error_msg() . "\n";
                var_dump($clientData);
            } else {
                echo "   Client JSON OK (" . strlen($clientJson) . " bytes)\n";
                echo "   Title: {$clientData['title']}\n";
            }
        } else {
            echo "   ERROR: Could not get client data\n";
        }
    }

} catch (Exception $e) {
    echo "   EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test complete ===\n";
