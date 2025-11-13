<?php
/**
 * Test DialogService - Database-backed dialog system
 * CLI script - run with: php scripts/tutorial/test_dialog_service.php
 */

// Bypass web authentication for CLI testing
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

// Minimal bootstrap for CLI
define('__ROOT__', dirname(dirname(__DIR__)));
require_once(__ROOT__ . '/config/constants.php');
require_once(__ROOT__ . '/config/db_constants.php');
require_once(__ROOT__ . '/config/bootstrap.php');
require_once(__ROOT__ . '/config/functions.php');

use App\Service\DialogService;
use Classes\Player;

echo "=== Testing DialogService (Database-backed) ===\n\n";

// Test 1: Tutorial mode - load from database
echo "Test 1: Load dialog from database (tutorial mode)\n";
echo "------------------------------------------------\n";
$tutorialService = new DialogService(true);
$dialogData = $tutorialService->getDialogData('gaia_welcome');

if ($dialogData['success']) {
    echo "✓ SUCCESS: Loaded dialog from database\n";
    echo "  Dialog ID: " . $dialogData['id'] . "\n";
    echo "  NPC Name: " . $dialogData['name'] . "\n";
    echo "  Nodes: " . count($dialogData['nodes']) . "\n";
    echo "  Mode: " . $dialogData['mode'] . "\n";
} else {
    echo "✗ FAILED: " . $dialogData['error'] . "\n";
}
echo "\n";

// Test 2: Game mode - load from JSON files
echo "Test 2: Load dialog from files (game mode)\n";
echo "-------------------------------------------\n";
$gameService = new DialogService(false);
$gameDialogData = $gameService->getDialogData('marchand');

if ($gameDialogData['success']) {
    echo "✓ SUCCESS: Loaded dialog from JSON file\n";
    echo "  Dialog ID: " . $gameDialogData['id'] . "\n";
    echo "  Mode: " . $gameDialogData['mode'] . "\n";
} else {
    echo "✗ FAILED: " . $gameDialogData['error'] . "\n";
}
echo "\n";

// Test 3: Player placeholder replacement
echo "Test 3: Player placeholder replacement\n";
echo "--------------------------------------\n";
$player = new Player(1);
$player->get_data();
$dialogWithPlayer = $tutorialService->getDialogData('gaia_welcome', $player);

if ($dialogWithPlayer['success']) {
    echo "✓ SUCCESS: Loaded dialog with player context\n";
    echo "  Player: " . $player->data->name . "\n";
    // Check if placeholders would be replaced (if they existed in dialog)
    echo "  Dialog nodes: " . count($dialogWithPlayer['nodes']) . "\n";
} else {
    echo "✗ FAILED\n";
}
echo "\n";

// Test 4: List all tutorial dialogs in database
echo "Test 4: List all tutorial dialogs\n";
echo "----------------------------------\n";
$db = new \Classes\Db();
$result = $db->exe('SELECT dialog_id, npc_name, version FROM tutorial_dialogs WHERE is_active = 1');

if ($result) {
    echo "Tutorial dialogs in database:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['dialog_id']} ({$row['npc_name']}) v{$row['version']}\n";
    }
} else {
    echo "✗ FAILED to query database\n";
}
echo "\n";

// Test 5: Test combat dialog
echo "Test 5: Load combat dialog\n";
echo "--------------------------\n";
$combatDialog = $tutorialService->getDialogData('gaia_combat');

if ($combatDialog['success']) {
    echo "✓ SUCCESS: Loaded combat dialog\n";
    echo "  Nodes: " . count($combatDialog['nodes']) . "\n";
    echo "  First node: " . substr($combatDialog['nodes'][0]['text'], 0, 50) . "...\n";
} else {
    echo "✗ FAILED: " . $combatDialog['error'] . "\n";
}
echo "\n";

// Test 6: Test completion dialog
echo "Test 6: Load completion dialog\n";
echo "------------------------------\n";
$completionDialog = $tutorialService->getDialogData('gaia_completion');

if ($completionDialog['success']) {
    echo "✓ SUCCESS: Loaded completion dialog\n";
    echo "  Nodes: " . count($completionDialog['nodes']) . "\n";
} else {
    echo "✗ FAILED: " . $completionDialog['error'] . "\n";
}
echo "\n";

echo "=== DialogService Tests Complete ===\n";
