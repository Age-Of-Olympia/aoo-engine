<?php

/**
 * CLI twin of admin/plan-seed.php: seeds `plans` / `plan_z_levels` from the
 * environment's datas/{public,private}/plans/*.json. Create-only — a plan
 * already in the DB is never touched, so the run is replayable.
 *
 * Usage (from the docroot, where datas/ exists):
 *   php scripts/seed-plans.php [--dry-run]
 */

require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/db_constants.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/functions.php';

use App\Service\PlanSeedService;

$service = new PlanSeedService();

if (in_array('--dry-run', $argv, true)) {
    $preview = $service->preview();

    foreach ($preview['entries'] as $entry) {
        printf(
            "%-30s %-30s %d niveau(x)  %s%s\n",
            $entry['slug'],
            $entry['name'],
            $entry['zLevels'],
            $entry['inDb'] ? 'déjà en base' : 'à créer',
            $entry['warnings'] === [] ? '' : '  [' . implode(' · ', $entry['warnings']) . ']'
        );
    }
    foreach ($preview['unreadable'] as $slug) {
        fwrite(STDERR, "illisible : {$slug}\n");
    }

    exit(0);
}

$report = $service->seed();

printf(
    "créés : %d, préservés : %d, illisibles : %d\n",
    count($report['created']),
    count($report['skipped']),
    count($report['unreadable'])
);
foreach ($report['created'] as $slug) {
    echo "  + {$slug}\n";
}
foreach ($report['warnings'] as $slug => $warnings) {
    fwrite(STDERR, "{$slug} : " . implode(' · ', $warnings) . "\n");
}
foreach ($report['unreadable'] as $slug) {
    fwrite(STDERR, "illisible : {$slug}\n");
}

exit($report['unreadable'] === [] ? 0 : 1);
