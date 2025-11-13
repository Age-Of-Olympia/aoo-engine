<?php
/**
 * Test Tutorial Classes - TutorialManager, TutorialContext, TutorialFeatureFlag
 * CLI script - run with: php scripts/tutorial/test_tutorial_classes.php
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

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialContext;
use App\Tutorial\TutorialFeatureFlag;
use Classes\Player;

echo "=== Testing Tutorial Classes ===\n\n";

// Test 1: TutorialContext
echo "Test 1: TutorialContext\n";
echo "-----------------------\n";
$player = new Player(1);
$player->get_data();
$context = new TutorialContext($player, 'first_time');

echo "✓ Context created\n";
echo "  Player: " . $context->getPlayer()->data->name . "\n";
echo "  Mode: " . $context->getMode() . "\n";
echo "  Initial XP: " . $context->getTutorialXP() . "\n";
echo "  Initial Level: " . $context->getTutorialLevel() . "\n";
echo "  Initial PI: " . $context->getTutorialPI() . "\n";
echo "\n";

// Test 2: State management
echo "Test 2: State management\n";
echo "------------------------\n";
$context->setState('test_key', 'test_value');
$value = $context->getState('test_key');
echo "✓ State set and retrieved: $value\n";
echo "\n";

// Test 3: XP award and level up
echo "Test 3: XP award and level up\n";
echo "------------------------------\n";
$context->awardXP(50);
echo "  Awarded 50 XP. Total: " . $context->getTutorialXP() . "\n";
$context->awardXP(60);
echo "  Awarded 60 XP. Total: " . $context->getTutorialXP() . " (should trigger level up at 100)\n";
echo "  Current Level: " . $context->getTutorialLevel() . "\n";
echo "  Current PI: " . $context->getTutorialPI() . "\n";
echo "  Pending level up? " . ($context->hasPendingLevelUp() ? 'Yes' : 'No') . "\n";
echo "\n";

// Test 4: PI investment
echo "Test 4: PI investment\n";
echo "---------------------\n";
$oldMvt = $context->getPlayer()->data->mvt ?? 4;
$success = $context->investPI('mvt', 1);
$newMvt = $context->getPlayer()->data->mvt ?? 4;

if ($success) {
    echo "✓ PI investment successful\n";
    echo "  Mvt before: $oldMvt\n";
    echo "  Mvt after: $newMvt\n";
    echo "  PI remaining: " . $context->getTutorialPI() . "\n";
} else {
    echo "✗ PI investment failed\n";
}
echo "\n";

// Test 5: State serialization
echo "Test 5: State serialization\n";
echo "---------------------------\n";
$serialized = $context->serializeState();
echo "✓ State serialized: " . strlen($serialized) . " bytes\n";

// Create new context and restore
$context2 = new TutorialContext($player, 'replay');
$context2->restoreState($serialized);
echo "✓ State restored to new context\n";
echo "  Restored XP: " . $context2->getTutorialXP() . "\n";
echo "  Restored Level: " . $context2->getTutorialLevel() . "\n";
echo "  Restored PI: " . $context2->getTutorialPI() . "\n";
echo "\n";

// Test 6: TutorialManager
echo "Test 6: TutorialManager\n";
echo "-----------------------\n";
$manager = new TutorialManager($player, 'first_time');
echo "✓ Manager created\n";
echo "  Session ID: " . $manager->getSessionId() . "\n";
echo "  Context player: " . $manager->getContext()->getPlayer()->data->name . "\n";
echo "\n";

// Test 7: Check completion status
echo "Test 7: Check completion status\n";
echo "--------------------------------\n";
$hasCompleted = TutorialManager::hasCompletedTutorial(1);
echo "  Has player 1 completed tutorial? " . ($hasCompleted ? 'Yes' : 'No') . "\n";
echo "\n";

// Test 8: TutorialFeatureFlag
echo "Test 8: TutorialFeatureFlag\n";
echo "---------------------------\n";
echo "  Globally enabled? " . (TutorialFeatureFlag::isEnabled() ? 'Yes' : 'No') . "\n";
echo "  Enabled for player 1? " . (TutorialFeatureFlag::isEnabledForPlayer(1) ? 'Yes' : 'No') . "\n";
echo "  Enabled for player 999? " . (TutorialFeatureFlag::isEnabledForPlayer(999) ? 'Yes' : 'No') . "\n";
echo "  Should show tutorial for player 1? " . (TutorialFeatureFlag::shouldShowTutorial(1, false) ? 'Yes' : 'No') . "\n";
echo "  Tutorial mode for player 1 (new): " . (TutorialFeatureFlag::getTutorialMode(1, false) ?? 'null') . "\n";
echo "  Tutorial mode for player 1 (completed): " . (TutorialFeatureFlag::getTutorialMode(1, true) ?? 'null') . "\n";
echo "\n";

// Test 9: Public state for API
echo "Test 9: Public state for API\n";
echo "----------------------------\n";
$publicState = $context->getPublicState();
echo "✓ Public state generated:\n";
echo "  " . json_encode($publicState, JSON_PRETTY_PRINT) . "\n";
echo "\n";

echo "=== Tutorial Class Tests Complete ===\n";
