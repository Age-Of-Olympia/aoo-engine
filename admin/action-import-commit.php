<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\ImportExport\ActionImporter;
use App\Service\ImportExport\BundleEnvelope;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/action-import.php');
    exit;
}

$csrf = new CsrfProtectionService();
$json = $_SESSION['action_import_bundle'] ?? null;

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

    if (!is_string($json) || $json === '') {
        throw new InvalidArgumentException('Aucun bundle à appliquer. Importez d\'abord un fichier.');
    }

    // Re-parse + re-validate from the stored JSON: import() classifies every
    // object again and applies the batch transactionally (all-or-nothing).
    $parsed = BundleEnvelope::parse($json);
    if ($parsed->objectType !== 'action') {
        throw new InvalidArgumentException("Type d'objet non supporté : « {$parsed->objectType} ».");
    }

    $report = (new ActionImporter())->import($parsed->objects);

    unset($_SESSION['action_import_bundle'], $_SESSION['action_import_filename']);
    $csrf->regenerateToken();

    if ($report->hasRejections()) {
        $first = $report->rejected()[0];
        setFlash('danger', 'Import annulé (' . count($report->rejected()) . ' rejet(s)) : ' . $first['name'] . ' — ' . $first['reason']);
    } else {
        setFlash('success', sprintf(
            'Import appliqué : %d créée(s), %d mise(s) à jour, %d avertissement(s).',
            count($report->created()),
            count($report->updated()),
            count($report->warnings())
        ));
    }
} catch (\Throwable $exception) {
    setFlash('danger', 'Échec de l\'import : ' . $exception->getMessage());
}

header('Location: /admin/action-import.php');
exit;
