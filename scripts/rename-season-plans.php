<?php

/**
 * CLI twin of admin/plan-season-rename.php: gives the current season's
 * plans their base slug, suffixes displaced archives with their own
 * season. Replayable at every season opening.
 *
 * Usage (from the docroot):
 *   php scripts/rename-season-plans.php [--dry-run]
 */

require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/db_constants.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../config/functions.php';

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
}

$service = new \App\Service\PlanSeasonRenameService();

if (in_array('--dry-run', $argv, true)) {
    $preview = $service->preview();

    foreach ($preview['operations'] as $i => $op) {
        printf(
            "%2d. %-30s -> %-30s %s%s\n",
            $i + 1,
            $op['from'],
            $op['to'],
            $op['kind'] === 'archive' ? 'archive déplacée' : 'suffixe retiré',
            $op['pinBg'] !== null ? '  [fond épinglé : ' . $op['pinBg'] . ']' : ''
        );
    }
    foreach ($preview['skipped'] as $slug => $why) {
        fwrite(STDERR, "ignoré {$slug} : {$why}\n");
    }
    if ($preview['operations'] === []) {
        echo "rien à renommer\n";
    }

    exit(0);
}

$report = $service->apply();

foreach ($report['renamed'] as $done) {
    printf(
        "%s -> %s%s\n",
        $done['from'],
        $done['to'],
        $done['pinnedBg'] !== null ? '  [fond épinglé : ' . $done['pinnedBg'] . ']' : ''
    );
}
foreach ($report['skipped'] as $slug => $why) {
    fwrite(STDERR, "ignoré {$slug} : {$why}\n");
}
if ($report['failed'] !== null) {
    fwrite(STDERR, "ÉCHEC sur {$report['failed']['from']} -> {$report['failed']['to']} : {$report['failed']['error']}\n");
    exit(1);
}

printf("%d plan(s) renommé(s)\n", count($report['renamed']));
