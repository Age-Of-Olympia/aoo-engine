#!/usr/bin/env php
<?php
/**
 * Migration Script: Remove Legacy Tutorial Schema
 *
 * This script removes the deprecated tutorial_configurations table
 * after the migration to normalized tutorial_steps schema.
 *
 * IMPORTANT: This script should only be run AFTER verifying:
 * 1. api/tutorial/jump-to-step.php has been fixed to use tutorial_steps
 * 2. All tutorial steps exist in new schema
 * 3. Backup has been created
 *
 * Usage:
 *   php scripts/tutorial/migrate_remove_legacy_schema.php [--dry-run] [--backup]
 *
 * Options:
 *   --dry-run    Show what would be done without making changes
 *   --backup     Create backup SQL dump before dropping table
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use Classes\Db;

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$createBackup = in_array('--backup', $argv);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Tutorial Legacy Schema Removal Migration                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No changes will be made\n\n";
}

$db = new Db();

// Step 1: Verify new schema exists and is populated
echo "📋 Step 1: Verifying new schema...\n";

$newSchemaQuery = "SELECT COUNT(*) as count FROM tutorial_steps";
$result = $db->exe($newSchemaQuery);
$newCount = $result->fetch_assoc()['count'];

echo "   ✓ tutorial_steps table: {$newCount} rows\n";

if ($newCount === 0) {
    echo "\n❌ ERROR: tutorial_steps table is empty!\n";
    echo "   Cannot remove legacy schema until new schema is populated.\n";
    echo "   Run: php scripts/tutorial/populate_tutorial_steps.php\n\n";
    exit(1);
}

// Step 2: Verify old schema exists
echo "\n📋 Step 2: Checking legacy schema...\n";

$oldSchemaQuery = "SELECT COUNT(*) as count FROM tutorial_configurations";
$result = $db->exe($oldSchemaQuery);
$oldCount = $result->fetch_assoc()['count'];

echo "   ✓ tutorial_configurations table: {$oldCount} rows\n";

// Step 3: Compare data integrity
echo "\n📋 Step 3: Comparing data integrity...\n";

if ($newCount !== $oldCount) {
    echo "   ⚠️  WARNING: Row count mismatch!\n";
    echo "   Old schema: {$oldCount} steps\n";
    echo "   New schema: {$newCount} steps\n";
    echo "   Proceeding anyway (some steps may have been added to new schema)\n";
} else {
    echo "   ✓ Row counts match ({$newCount} steps in both schemas)\n";
}

// Step 4: Check for references to old table in code
echo "\n📋 Step 4: Checking code references...\n";

$codeReferences = [
    'api/tutorial/jump-to-step.php' => false,
    'src/Tutorial/TutorialStepRepository.php' => false,
];

foreach ($codeReferences as $file => $expectedFixed) {
    $fullPath = __DIR__ . '/../../' . $file;
    if (!file_exists($fullPath)) {
        echo "   ⚠️  File not found: {$file}\n";
        continue;
    }

    $content = file_get_contents($fullPath);
    $hasReference = strpos($content, 'tutorial_configurations') !== false;

    if ($hasReference) {
        echo "   ❌ FOUND: {$file} still references tutorial_configurations\n";
        if ($file === 'api/tutorial/jump-to-step.php') {
            echo "      This file MUST be fixed before running migration!\n";
            echo "      Change 'tutorial_configurations' to 'tutorial_steps'\n\n";
            exit(1);
        }
    } else {
        echo "   ✓ Clean: {$file}\n";
    }
}

// Step 5: Create backup if requested
if ($createBackup && !$dryRun) {
    echo "\n📋 Step 5: Creating backup...\n";

    $backupDir = __DIR__ . '/../../tmp/backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $backupFile = $backupDir . '/tutorial_configurations_' . date('Y-m-d_His') . '.sql';

    $dbConstants = DB_CONSTANTS;
    $host = explode(':', $dbConstants['host'])[0];
    $password = $dbConstants['password'];
    $database = $dbConstants['dbname'];
    $user = $dbConstants['user'];

    $mysqldumpCmd = sprintf(
        'mysqldump -h %s -u %s -p%s %s tutorial_configurations > %s 2>&1',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($password),
        escapeshellarg($database),
        escapeshellarg($backupFile)
    );

    exec($mysqldumpCmd, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile)) {
        $size = filesize($backupFile);
        echo "   ✓ Backup created: {$backupFile} (" . number_format($size) . " bytes)\n";
    } else {
        echo "   ❌ Backup failed!\n";
        echo "   Output: " . implode("\n", $output) . "\n";
        exit(1);
    }
} elseif ($createBackup && $dryRun) {
    echo "\n📋 Step 5: Backup (skipped in dry-run)\n";
    echo "   Would create: tmp/backups/tutorial_configurations_" . date('Y-m-d_His') . ".sql\n";
} else {
    echo "\n📋 Step 5: Backup (skipped - use --backup to enable)\n";
}

// Step 6: Drop legacy table
echo "\n📋 Step 6: Removing legacy schema...\n";

if ($dryRun) {
    echo "   Would execute: DROP TABLE tutorial_configurations;\n";
    echo "   Would remove index: idx_tutorial_config_step_id\n";
    echo "   Would remove index: idx_tutorial_config_number\n";
} else {
    try {
        // Drop table (indexes will be dropped automatically)
        $db->exe("DROP TABLE IF EXISTS tutorial_configurations");
        echo "   ✓ Dropped table: tutorial_configurations\n";
    } catch (Exception $e) {
        echo "   ❌ Failed to drop table: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Step 7: Verify deletion
if (!$dryRun) {
    echo "\n📋 Step 7: Verifying deletion...\n";

    try {
        $db->exe("SELECT 1 FROM tutorial_configurations LIMIT 1");
        echo "   ❌ ERROR: Table still exists!\n";
        exit(1);
    } catch (Exception $e) {
        echo "   ✓ Table successfully removed\n";
    }
}

// Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Migration Complete                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "✓ Dry run completed successfully\n";
    echo "\nTo execute migration:\n";
    echo "  php scripts/tutorial/migrate_remove_legacy_schema.php --backup\n\n";
} else {
    echo "✓ Legacy schema removed successfully\n";
    echo "✓ Tutorial system now uses normalized schema only\n\n";

    if ($createBackup) {
        echo "💾 Backup available at: tmp/backups/tutorial_configurations_*.sql\n\n";
    }
}

echo "Next steps:\n";
echo "1. Test tutorial functionality thoroughly\n";
echo "2. Update CLAUDE.md to reflect correct schema\n";
echo "3. Run: make test (ensure all tests pass)\n\n";
