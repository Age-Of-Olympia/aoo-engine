<?php
/**
 * Test script for crafting tutorial
 */

// Minimal bootstrap without login check
define('NO_LOGIN', true);
require_once __DIR__ . '/../../config.php';

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialContext;
use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialStepRepository;
use Classes\Player;
use Classes\Db;

header('Content-Type: text/plain; charset=utf-8');

$db = new Db();

echo "=== Testing Crafting Tutorial (2.0.0-craft) ===\n\n";

// 1. Check catalog
echo "1. Checking catalog...\n";
$catalog = $db->exe("SELECT * FROM tutorial_catalog WHERE version = '2.0.0-craft'")->fetch_assoc();
if ($catalog) {
    echo "   Found: {$catalog['name']} (v{$catalog['version']})\n";
} else {
    echo "   ERROR: Catalog entry not found!\n";
    exit(1);
}

// 2. Check steps
echo "\n2. Checking steps...\n";
$steps = $db->exe("SELECT step_id, title FROM tutorial_steps WHERE version = '2.0.0-craft' ORDER BY step_number");
$stepCount = 0;
while ($step = $steps->fetch_assoc()) {
    echo "   - {$step['step_id']}: {$step['title']}\n";
    $stepCount++;
}
echo "   Total: {$stepCount} steps\n";

// 3. Test step repository
echo "\n3. Testing TutorialStepRepository...\n";
$repo = new TutorialStepRepository();

$firstStep = $repo->getFirstStepId('2.0.0-craft');
echo "   First step ID: {$firstStep}\n";

$totalSteps = $repo->getTotalSteps('2.0.0-craft');
echo "   Total steps: {$totalSteps}\n";

// 4. Get first step data
echo "\n4. Getting first step data...\n";
$stepData = $repo->getStepById($firstStep, '2.0.0-craft');
if ($stepData) {
    echo "   Step ID: {$stepData['step_id']}\n";
    echo "   Title: {$stepData['title']}\n";
    echo "   Type: {$stepData['step_type']}\n";
    echo "   Config keys: " . implode(', ', array_keys($stepData['config'])) . "\n";
} else {
    echo "   ERROR: Could not get step data!\n";
    exit(1);
}

// 5. Test JSON encoding
echo "\n5. Testing JSON encoding...\n";
$json = json_encode($stepData, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    echo "   ERROR: JSON encoding failed: " . json_last_error_msg() . "\n";
    exit(1);
} else {
    echo "   JSON encoding OK (" . strlen($json) . " bytes)\n";
}

// 6. Test all steps JSON encoding
echo "\n6. Testing all steps JSON encoding...\n";
$allSteps = $repo->getStepsForAdmin('2.0.0-craft');
foreach ($allSteps as $s) {
    $fullStep = $repo->getStepById($s['step_id'], '2.0.0-craft');
    if ($fullStep) {
        $stepJson = json_encode($fullStep, JSON_UNESCAPED_UNICODE);
        if ($stepJson === false) {
            echo "   ERROR on {$s['step_id']}: " . json_last_error_msg() . "\n";
            var_dump($fullStep);
            exit(1);
        }
        echo "   {$s['step_id']}: OK (" . strlen($stepJson) . " bytes)\n";
    } else {
        echo "   ERROR: Could not get step {$s['step_id']}\n";
    }
}

echo "\n=== All JSON tests passed! ===\n";
