<?php
/**
 * Catalogue des types de ressources — mutations (POST uniquement).
 * Compagnon de admin/resource-types.php, routé sur ?action : save | delete.
 *
 * La suppression est gardée côté service : refusée tant que des instances
 * map_resources portent le nom. CSRF validé ; même niveau d'accès que le
 * menu (un POST direct ne le contourne pas). Redirige en PRG avec un flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\ResourceTypeService;
use App\Service\TileCatalogService;

(new AdminMenuAccessService())->enforce('resource-types.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/resource-types.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/resource-types.php');
}

$action = $_GET['action'] ?? '';
$name = trim((string) ($_POST['name'] ?? ''));

if (!preg_match(TileCatalogService::ASSET_NAME_PATTERN, $name)) {
    setFlash('warning', 'Nom invalide (lettres, chiffres, _ . - — même règle que les noms de tuiles).');
    redirectTo('/admin/resource-types.php');
}

if ($action === 'save') {
    $pv = trim((string) ($_POST['pv'] ?? ''));
    if (!is_numeric($pv)) {
        setFlash('warning', 'PV invalide (entier attendu : -1 récoltable, -2 épuisé, positif destructible).');
        redirectTo('/admin/resource-types.php');
    }

    ResourceTypeService::save($name, (int) $pv);
    setFlash('success', "Type « {$name} » enregistré (pv " . (int) $pv . ').');
} elseif ($action === 'delete') {
    try {
        ResourceTypeService::delete($name);
        setFlash('success', "Type « {$name} » supprimé.");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
    }
}

redirectTo('/admin/resource-types.php');
