<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\ImportExport\BundleEnvelope;
use App\Service\ImportExport\ImporterRegistry;
use App\View\Action\ImportPreviewView;

$json = $_SESSION['action_import_bundle'] ?? null;
if (!is_string($json) || $json === '') {
    setFlash('warning', 'Aucun bundle à prévisualiser. Importez d\'abord un fichier.');
    redirectTo('/admin/action-import.php');
}

try {
    $parsed = BundleEnvelope::parse($json);
    $importer = (new ImporterRegistry())->importerFor($parsed->objectType);
    if ($importer === null) {
        throw new InvalidArgumentException("Type d'objet non supporté : « {$parsed->objectType} ».");
    }
    $report = $importer->preview($parsed->objects);
} catch (\Throwable $exception) {
    unset($_SESSION['action_import_bundle'], $_SESSION['action_import_filename']);
    setFlash('danger', 'Bundle invalide : ' . $exception->getMessage());
    redirectTo('/admin/action-import.php');
}

$csrf = new CsrfProtectionService();
$filename = (string) ($_SESSION['action_import_filename'] ?? 'bundle.json');
$bundleHash = hash('sha256', $json);
$body = (new ImportPreviewView())->render($report, $filename, $csrf->renderTokenField(), $bundleHash);

echo admin_layout('Prévisualisation de l\'import', $body, ['back' => ['/admin/action-import.php', 'Importer']]);
