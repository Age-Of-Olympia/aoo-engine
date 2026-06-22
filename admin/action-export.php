<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;
use App\Service\AdminAuthorizationService;
use App\Service\ImportExport\BundleDownload;
use App\Service\ImportExport\BundleEnvelope;
use App\Service\ImportExport\ExporterRegistry;

AdminAuthorizationService::DoAdminCheck();

/*
 * Read-only JSON download. Deliberately does NOT include admin/layout.php: the
 * response is a file attachment, not an HTML page. ?type picks the object family
 * (default action); ?id exports a single action; otherwise the whole catalogue.
 */
$type = (string) ($_GET['type'] ?? 'action');
$exporter = (new ExporterRegistry())->exporterFor($type);
if ($exporter === null) {
    setFlash('warning', "Type d'export inconnu : « {$type} ».");
    header('Location: /admin/actions.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

if ($type === 'action' && $id > 0) {
    // Single-action export keeps its by-id lookup (the generic exporter has none).
    $catalog = new ActionCatalogService();
    $action = $catalog->getActionById($id);
    if ($action === null) {
        setFlash('warning', 'Action introuvable.');
        header('Location: /admin/actions.php');
        exit;
    }
    $objects = [$exporter->toArray($action)];
    $filename = BundleDownload::filename($type, $action->getName());
} else {
    $objects = $exporter->exportAll();
    $filename = BundleDownload::filename($type);
}

$json = BundleEnvelope::encode(BundleEnvelope::build($type, $objects));

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
echo $json;
exit;
