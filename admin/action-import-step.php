<?php
/**
 * One slice of a plan import (JSON), called in a loop by
 * action-import-run.php.
 *
 * A plan is loaded step by step, each step committed with the cursor naming
 * the next one: the request hands back when its budget runs out, and the next
 * call resumes where this one stopped.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\ImportExport\BundleEnvelope;
use App\Service\ImportExport\ImportReport;
use App\Service\ImportExport\PlanImporter;

AdminAuthorizationService::DoAdminCheck();
header('Content-Type: application/json');

/** Seconds of work per call: under PHP's and the proxy's time limits. */
const STEP_BUDGET = 5;

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);

    $json = $_SESSION['action_import_bundle'] ?? null;
    if (!is_string($json) || $json === '') {
        throw new InvalidArgumentException('Aucun bundle en cours.');
    }

    $parsed = BundleEnvelope::parse($json);
    if ($parsed->objectType !== 'plan') {
        throw new InvalidArgumentException('Cet écran ne charge que des plans.');
    }

    $importer = new PlanImporter();
    $report = new ImportReport();
    $objects = $parsed->objects;
    $index = (int) ($_SESSION['action_import_plan_index'] ?? 0);
    $deadline = microtime(true) + STEP_BUDGET;

    $payload = null;
    $run = null;

    while ($index < count($objects)) {
        $payload = $importer->payloadFor($objects[$index]);
        $run = $importer->runFor($payload, $report);
        if (!$run->isDone()) {
            break;
        }
        $index++;
    }

    if ($run === null || $index >= count($objects)) {
        unset($_SESSION['action_import_bundle'], $_SESSION['action_import_filename'], $_SESSION['action_import_plan_index']);
        echo json_encode(['done' => true, 'plan' => '', 'step' => 0, 'total' => 0, 'label' => 'terminé', 'warnings' => []]);
        exit;
    }

    while (!$run->isDone() && microtime(true) < $deadline) {
        $run->next();
    }

    if ($run->isDone()) {
        $index++;
    }
    $_SESSION['action_import_plan_index'] = $index;

    $warnings = array_map(
        static fn(array $warning): string => $warning['name'] . ' — ' . $warning['message'],
        $report->warnings()
    );
    $_SESSION['action_import_warnings'] = array_merge(
        (array) ($_SESSION['action_import_warnings'] ?? []),
        $warnings
    );

    $done = $index >= count($objects);
    if ($done) {
        unset($_SESSION['action_import_bundle'], $_SESSION['action_import_filename'], $_SESSION['action_import_plan_index']);
    }

    echo json_encode([
        'done'     => $done,
        'plan'     => $run->plan(),
        'step'     => $run->step(),
        'total'    => $run->total(),
        'label'    => $run->label(),
        'warnings' => $warnings,
    ]);
} catch (\Throwable $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()]);
}
