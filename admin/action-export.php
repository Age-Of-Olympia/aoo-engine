<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\EntityManagerFactory;
use App\Entity\Faction;
use App\Entity\Race;
use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionPassiveCatalogService;
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

if ($id > 0) {
    // Single-object export. The generic exporter has no by-id lookup, so we resolve
    // the one entity per family here, then hand it to the matching exporter.
    $object = match ($type) {
        'action' => (new ActionCatalogService())->getActionById($id),
        'passive' => (new ActionPassiveCatalogService())->getById($id),
        'race' => EntityManagerFactory::getEntityManager()->find(Race::class, $id),
        'faction' => EntityManagerFactory::getEntityManager()->find(Faction::class, $id),
        default => null,
    };
    if ($object === null) {
        setFlash('warning', 'Objet introuvable.');
        header('Location: /admin/actions.php');
        exit;
    }
    $objects = [$exporter->toArray($object)];
    // Factions: filename from the stable code, not the display name (accents, &…).
    $filename = BundleDownload::filename(
        $type,
        $object instanceof Faction ? $object->getCode() : $object->getName()
    );
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
