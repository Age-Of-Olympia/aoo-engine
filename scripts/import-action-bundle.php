<?php

/**
 * One-off local helper: replace ALL actions in the app DB (aoo4) with a
 * config-bundle exported from action-export.php. True replace — every existing
 * action is deleted first (children cascade), then the bundle is imported fresh,
 * so type changes / removed actions are handled and final state == bundle.
 *
 * Usage (inside the devcontainer):
 *   php scripts/import-action-bundle.php /path/to/bundle.json
 *
 * Safety: back up first (mysqldump actions action_conditions action_outcomes
 * outcome_instructions race_actions). If it reports rejections, restore.
 */

require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/db_constants.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/functions.php';

use App\Action\Condition\ConditionRegistry;
use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\Action;
use App\Entity\EntityManagerFactory;
use App\Service\Action\ActionTypeRegistry;
use App\Service\ImportExport\ActionImporter;

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fail('Usage: php scripts/import-action-bundle.php /path/to/bundle.json');
}

$raw = file_get_contents($path);
$bundle = json_decode((string) $raw, true);
if (!is_array($bundle)) {
    fail('Bundle is not valid JSON.');
}
if (($bundle['objectType'] ?? null) !== 'action') {
    fail("Bundle objectType is not 'action' (got: " . var_export($bundle['objectType'] ?? null, true) . ').');
}
$objects = $bundle['objects'] ?? null;
if (!is_array($objects) || $objects === []) {
    fail('Bundle has no objects.');
}

echo 'Bundle: ' . count($objects) . " actions, exportedAt=" . ($bundle['exportedAt'] ?? '?') . "\n";

/* Pre-flight: reject unknown types / conditions / instructions BEFORE deleting
 * anything, so the likely failure modes never leave the DB empty. */
$typeRegistry = new ActionTypeRegistry();
$conditionRegistry = new ConditionRegistry();
$instructionTypes = OutcomeInstructionFactory::typeMap();
$names = [];
$problems = [];
foreach ($objects as $i => $object) {
    $name = is_string($object['name'] ?? null) ? $object['name'] : "#{$i}";
    $names[] = $name;
    $class = $typeRegistry->classForTypeKey((string) ($object['type'] ?? ''));
    if ($class === null || !(new ReflectionClass($class))->isInstantiable()) {
        $problems[] = "{$name}: unknown/abstract type '" . ($object['type'] ?? '') . "'";
    }
    foreach (($object['conditions'] ?? []) as $c) {
        if ($conditionRegistry->getCondition((string) ($c['type'] ?? '')) === null) {
            $problems[] = "{$name}: unknown condition '" . ($c['type'] ?? '') . "'";
        }
    }
    foreach (($object['outcomes'] ?? []) as $o) {
        foreach (($o['instructions'] ?? []) as $ins) {
            if (!isset($instructionTypes[(string) ($ins['type'] ?? '')])) {
                $problems[] = "{$name}: unknown instruction '" . ($ins['type'] ?? '') . "'";
            }
        }
    }
}
$dupes = array_keys(array_filter(array_count_values($names), static fn ($n) => $n > 1));
if ($dupes !== []) {
    $problems[] = 'Duplicate names in bundle: ' . implode(', ', $dupes);
}
if ($problems !== []) {
    fail("Pre-flight failed (nothing deleted):\n  - " . implode("\n  - ", $problems));
}
echo "Pre-flight OK: all types, conditions and instructions are known.\n";

$em = EntityManagerFactory::getEntityManager();

/* True replace: delete every existing action (owned children cascade). */
$existing = $em->getRepository(Action::class)->findAll();
echo 'Deleting ' . count($existing) . " existing actions...\n";
foreach ($existing as $action) {
    $em->remove($action);
}
$em->flush();
$em->clear();

/* Import fresh — all-or-nothing inside its own transaction. */
$report = (new ActionImporter($em))->import($objects);

$created = $report->created();
$updated = $report->updated();
$rejected = $report->rejected();
$warnings = $report->warnings();

echo "\n=== Import report ===\n";
echo 'Created: ' . count($created) . "\n";
echo 'Updated: ' . count($updated) . "\n";
echo 'Rejected: ' . count($rejected) . "\n";
foreach ($rejected as $name => $reason) {
    echo "  REJECT {$name}: {$reason}\n";
}
echo 'Warnings: ' . count($warnings) . "\n";
foreach ($warnings as $name => $reason) {
    echo "  WARN {$name}: {$reason}\n";
}

if ($rejected !== []) {
    fail('Import REJECTED and rolled back. The actions table is now EMPTY (delete already ran) — restore from your backup.');
}

$final = (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM actions');
echo "\nFinal actions in DB: {$final}\n";
echo "Done.\n";
