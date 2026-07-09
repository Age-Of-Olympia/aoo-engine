<?php
/**
 * Access control — save per-menu required levels (POST only).
 *
 * Superadmin-only (managing who can reach what is itself a superadmin power).
 * CSRF-validated. Applies every submitted level via AdminMenuAccessService,
 * which ignores unknown pages / invalid levels and clears an override when the
 * chosen level equals the registry default.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminAuthorizationService;
use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoSuperAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/access-control.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/access-control.php');
}

$levels = $_POST['level'] ?? [];
if (!is_array($levels)) {
    $levels = [];
}

$service = new AdminMenuAccessService();
foreach ($levels as $page => $level) {
    $service->setLevel((string) $page, (string) $level);
}

setFlash('success', 'Niveaux d’accès enregistrés.');
redirectTo('/admin/access-control.php');
