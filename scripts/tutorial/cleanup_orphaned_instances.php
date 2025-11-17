<?php
/**
 * Cleanup Orphaned Tutorial Map Instances
 *
 * This script removes tutorial map instances that are no longer needed.
 * Run this periodically to clean up instances from:
 * - Completed tutorials
 * - Cancelled tutorials
 * - Abandoned/crashed tutorial sessions
 *
 * Usage: php scripts/tutorial/cleanup_orphaned_instances.php
 */

define('NO_LOGIN', true);
require_once __DIR__ . '/../../config.php';

use App\Tutorial\TutorialMapInstance;
use App\Entity\EntityManagerFactory;
use Classes\Db;

$db = new Db();
$em = EntityManagerFactory::getEntityManager();
$conn = $em->getConnection();
$mapInstance = new TutorialMapInstance($conn);

echo "==== Tutorial Map Instance Cleanup ====\n\n";

// Find all tut_* plans in coords table
$sql = "SELECT DISTINCT plan FROM coords WHERE plan LIKE 'tut_%'";
$result = $db->exe($sql);

$instancePlans = [];
while ($row = $result->fetch_assoc()) {
    $instancePlans[] = $row['plan'];
}

echo "Found " . count($instancePlans) . " tutorial map instances\n\n";

if (empty($instancePlans)) {
    echo "No instances to clean up. Exiting.\n";
    exit(0);
}

// Check each instance against tutorial_progress
$toDelete = [];
$toKeep = [];

foreach ($instancePlans as $planName) {
    // Extract session ID from plan name (tut_abc123... -> abc123...)
    $sessionIdFragment = str_replace('tut_', '', $planName);

    // Find matching tutorial session
    // Session IDs are UUIDs, fragment is first 10 chars
    $sql = "SELECT tutorial_session_id, completed, is_active
            FROM tutorial_progress tp
            LEFT JOIN tutorial_players tpl ON tpl.tutorial_session_id = tp.tutorial_session_id
            WHERE tp.tutorial_session_id LIKE ?
            LIMIT 1";

    $sessionsResult = $db->exe($sql, [$sessionIdFragment . '%']);
    $session = $sessionsResult->fetch_assoc();

    if (!$session) {
        // No matching session - orphaned instance
        echo "❌ ORPHAN: {$planName} (no matching session found)\n";
        $toDelete[] = $planName;
    } elseif ($session['completed'] == 1) {
        // Session completed - should be cleaned up
        echo "✅ COMPLETED: {$planName} (session completed)\n";
        $toDelete[] = $planName;
    } elseif ($session['is_active'] == 0) {
        // Tutorial player deactivated - should be cleaned up
        echo "⚠️  INACTIVE: {$planName} (tutorial player deactivated)\n";
        $toDelete[] = $planName;
    } else {
        // Active session - keep
        echo "🔵 ACTIVE: {$planName} (session active)\n";
        $toKeep[] = $planName;
    }
}

echo "\n";
echo "Summary:\n";
echo "  Active instances: " . count($toKeep) . "\n";
echo "  Instances to delete: " . count($toDelete) . "\n";
echo "\n";

if (empty($toDelete)) {
    echo "Nothing to clean up. Exiting.\n";
    exit(0);
}

// Ask for confirmation
echo "Delete " . count($toDelete) . " orphaned/completed instances? [y/N]: ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "Aborted.\n";
    exit(0);
}

// Delete instances
echo "\nDeleting instances...\n";

$deletedCount = 0;
foreach ($toDelete as $planName) {
    try {
        $mapInstance->deleteInstanceByPlan($planName);
        echo "✅ Deleted: {$planName}\n";
        $deletedCount++;
    } catch (\Exception $e) {
        echo "❌ Error deleting {$planName}: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "==== Cleanup Complete ====\n";
echo "Deleted: {$deletedCount} instances\n";
echo "Remaining: " . count($toKeep) . " active instances\n";
