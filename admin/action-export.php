<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;
use App\Service\AdminAuthorizationService;
use App\Service\ImportExport\ActionExporter;
use App\Service\ImportExport\BundleDownload;
use App\Service\ImportExport\BundleEnvelope;

AdminAuthorizationService::DoAdminCheck();

/*
 * Read-only JSON download. Deliberately does NOT include admin/layout.php: the
 * response is a file attachment, not an HTML page. One action when ?id is given,
 * otherwise the whole catalogue.
 */
$catalog = new ActionCatalogService();
$exporter = new ActionExporter($catalog);

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $action = $catalog->getActionById($id);
    if ($action === null) {
        setFlash('warning', 'Action introuvable.');
        header('Location: /admin/actions.php');
        exit;
    }
    $objects = [$exporter->toArray($action)];
    $filename = BundleDownload::filename($exporter->objectType(), $action->getName());
} else {
    $objects = $exporter->exportAll();
    $filename = BundleDownload::filename($exporter->objectType());
}

$json = BundleEnvelope::encode(BundleEnvelope::build($exporter->objectType(), $objects));

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
echo $json;
exit;
