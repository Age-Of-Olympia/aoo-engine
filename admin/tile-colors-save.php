<?php
/**
 * Palette carte — mutations (POST uniquement). Compagnon de
 * admin/tile-colors.php, routé sur ?action : save | delete.
 *
 * « default » est protégée côté service (repli de colorFor). CSRF validé ;
 * même niveau d'accès que le menu. Redirige en PRG avec un flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\ColorService;
use App\Service\CsrfProtectionService;
use App\Service\TileCatalogService;

(new AdminMenuAccessService())->enforce('tile-colors.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/tile-colors.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/tile-colors.php');
}

$action = $_GET['action'] ?? '';
$name = trim((string) ($_POST['name'] ?? ''));

if (!preg_match(TileCatalogService::ASSET_NAME_PATTERN, $name)) {
    setFlash('warning', 'Nom invalide (lettres, chiffres, _ . - — même règle que les noms de tuiles).');
    redirectTo('/admin/tile-colors.php');
}

if ($action === 'save') {
    $hex = strtolower(trim((string) ($_POST['color'] ?? '')));
    if (!preg_match('/^#[0-9a-f]{6}$/', $hex)) {
        setFlash('warning', 'Couleur invalide (format #rrggbb attendu).');
        redirectTo('/admin/tile-colors.php');
    }

    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
    ColorService::saveColor($name, (int) $r, (int) $g, (int) $b);
    setFlash('success', "Couleur de « {$name} » enregistrée ({$hex}).");
} elseif ($action === 'delete') {
    try {
        ColorService::deleteColor($name);
        setFlash('success', "Couleur de « {$name} » supprimée (repli sur default).");
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
    }
}

redirectTo('/admin/tile-colors.php');
