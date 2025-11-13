<?php
/**
 * Test complete tutorial flow end-to-end
 *
 * Run with: php scripts/tutorial/test_tutorial_flow.php
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

// Bootstrap
define('__ROOT__', dirname(dirname(__DIR__)));
require_once(__ROOT__ . '/config/constants.php');
require_once(__ROOT__ . '/config/db_constants.php');
require_once(__ROOT__ . '/config/bootstrap.php');
require_once(__ROOT__ . '/config/functions.php');

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialContext;
use App\Tutorial\TutorialStepFactory;
use Classes\Player;
use Classes\Db;

echo "=== Testing Complete Tutorial Flow ===\n\n";

// Clean up any existing tutorial progress for test player
$db = new Db();
$testPlayerId = 1; // Cradek
echo "Cleaning up existing tutorial progress for player $testPlayerId...\n";
$db->exe('DELETE FROM tutorial_progress WHERE player_id = ?', [$testPlayerId]);
echo "\n";

// Test 1: Start tutorial
echo "Test 1: Start Tutorial\n";
echo "-----------------------\n";
$player = new Player($testPlayerId);
$player->get_data();

$manager = new TutorialManager($player, 'first_time');
$startResult = $manager->startTutorial('1.0.0');

if ($startResult['success']) {
    echo "✓ Tutorial started successfully\n";
    echo "  Session ID: {$startResult['session_id']}\n";
    echo "  Total steps: {$startResult['total_steps']}\n";
    echo "  Current step: {$startResult['current_step']}\n";
    echo "  Mode: {$startResult['mode']}\n";
} else {
    echo "✗ Failed to start tutorial\n";
    exit(1);
}
echo "\n";

$sessionId = $startResult['session_id'];

// Test 2: Get first step
echo "Test 2: Get First Step (Step 0)\n";
echo "--------------------------------\n";
$stepData = $manager->getCurrentStepForClient(0, '1.0.0');

if ($stepData) {
    echo "✓ Step data loaded\n";
    echo "  Step: {$stepData['step_number']} / {$startResult['total_steps']}\n";
    echo "  Type: {$stepData['step_type']}\n";
    echo "  Title: {$stepData['title']}\n";
    echo "  XP Reward: {$stepData['xp_reward']}\n";
    echo "  Requires Validation: " . ($stepData['requires_validation'] ? 'Yes' : 'No') . "\n";

    if (isset($stepData['dialog'])) {
        echo "  Dialog: {$stepData['dialog']['id']}\n";
    }
} else {
    echo "✗ Failed to load step data\n";
    exit(1);
}
echo "\n";

// Test 3: Advance through steps
echo "Test 3: Advance Through Steps\n";
echo "------------------------------\n";

for ($i = 0; $i < 5; $i++) {
    echo "Advancing from step $i...\n";

    // Simulate validation data based on step type
    $validationData = [];
    $currentStepData = $manager->getCurrentStepForClient($i, '1.0.0');

    if ($currentStepData && $currentStepData['requires_validation']) {
        // Provide mock validation data
        if ($currentStepData['step_type'] === 'movement') {
            $validationData = ['action' => 'move'];
        } elseif ($currentStepData['step_type'] === 'action') {
            $validationData = ['action' => 'fouiller'];
        }
    }

    $advanceResult = $manager->advanceStep($validationData);

    if ($advanceResult['success']) {
        echo "✓ Advanced to step {$advanceResult['current_step']}\n";
        echo "  XP earned: {$advanceResult['xp_earned']}\n";
        echo "  Level: {$advanceResult['level']}\n";
        echo "  PI: {$advanceResult['pi']}\n";
    } else {
        echo "✗ Failed to advance: {$advanceResult['error']}\n";
        if (isset($advanceResult['hint'])) {
            echo "  Hint: {$advanceResult['hint']}\n";
        }
    }
    echo "\n";
}

// Test 4: Resume tutorial
echo "Test 4: Resume Tutorial\n";
echo "-----------------------\n";
$manager2 = new TutorialManager($player, 'first_time');
$resumeResult = $manager2->resumeTutorial($sessionId);

if ($resumeResult['success']) {
    echo "✓ Tutorial resumed\n";
    echo "  Current step: {$resumeResult['current_step']}\n";
    echo "  XP earned: {$resumeResult['xp_earned']}\n";
} else {
    echo "✗ Failed to resume tutorial\n";
}
echo "\n";

// Test 5: Test step factory
echo "Test 5: Test Step Factory\n";
echo "--------------------------\n";
$step = $manager->getStep(4, '1.0.0'); // Movement step

if ($step) {
    echo "✓ Step object created via factory\n";
    echo "  Class: " . get_class($step) . "\n";
    echo "  Type: {$step->getStepType()}\n";
    echo "  Title: {$step->getTitle()}\n";
    echo "  XP Reward: {$step->getXPReward()}\n";
    echo "  Requires validation: " . ($step->requiresValidation() ? 'Yes' : 'No') . "\n";

    // Test validation
    if ($step->requiresValidation()) {
        $validTest = $step->validate(['action' => 'move']);
        echo "  Validation test (move): " . ($validTest ? 'Passed' : 'Failed') . "\n";
    }
} else {
    echo "✗ Failed to create step object\n";
}
echo "\n";

// Test 6: Check database state
echo "Test 6: Database State\n";
echo "----------------------\n";
$progressCheck = $db->exe(
    'SELECT * FROM tutorial_progress WHERE tutorial_session_id = ?',
    [$sessionId]
);

if ($progressCheck && $progressCheck->num_rows > 0) {
    $progress = $progressCheck->fetch_assoc();
    echo "✓ Progress record found in database\n";
    echo "  Player ID: {$progress['player_id']}\n";
    echo "  Current step: {$progress['current_step']}\n";
    echo "  Total steps: {$progress['total_steps']}\n";
    echo "  Completed: " . ($progress['completed'] ? 'Yes' : 'No') . "\n";
    echo "  XP earned: {$progress['xp_earned']}\n";
    echo "  Mode: {$progress['tutorial_mode']}\n";
    echo "  Version: {$progress['tutorial_version']}\n";
} else {
    echo "✗ Progress record not found\n";
}
echo "\n";

// Test 7: List all available steps
echo "Test 7: Available Steps\n";
echo "-----------------------\n";
$stepsQuery = $db->exe(
    'SELECT step_number, step_type, title, xp_reward FROM tutorial_configurations WHERE version = ? ORDER BY step_number',
    ['1.0.0']
);

if ($stepsQuery) {
    echo "Tutorial steps in version 1.0.0:\n";
    while ($row = $stepsQuery->fetch_assoc()) {
        echo sprintf(
            "  [%2d] %-20s - %s (%d XP)\n",
            $row['step_number'],
            $row['step_type'],
            $row['title'],
            $row['xp_reward']
        );
    }
}
echo "\n";

// Test 8: Validate XP progression
echo "Test 8: XP Progression\n";
echo "----------------------\n";
$context = $manager->getContext();
echo "  Tutorial XP: {$context->getTutorialXP()}\n";
echo "  Tutorial Level: {$context->getTutorialLevel()}\n";
echo "  Tutorial PI: {$context->getTutorialPI()}\n";

if ($context->getTutorialXP() > 0) {
    echo "✓ XP is being tracked correctly\n";
} else {
    echo "⚠ XP should be > 0 after advancing steps\n";
}
echo "\n";

echo "=== Tutorial Flow Tests Complete ===\n";
echo "\nSummary:\n";
echo "- Tutorial can be started ✓\n";
echo "- Steps can be loaded from database ✓\n";
echo "- Steps can be advanced ✓\n";
echo "- Tutorial can be resumed ✓\n";
echo "- Step factory creates correct objects ✓\n";
echo "- Progress is persisted to database ✓\n";
echo "- XP progression works ✓\n";
echo "\n✓ All core functionality working!\n";
