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

AdminAuthorizationService::DoSuperAdminCheck();

requireAdminPost('/admin/access-control.php');

$levels = $_POST['level'] ?? [];
if (!is_array($levels)) {
    $levels = [];
}

$service = new AdminMenuAccessService();
foreach ($levels as $right => $level) {
    $service->setLevel((string) $right, (string) $level);
}

setFlash('success', 'Niveaux d’accès enregistrés.');
redirectTo('/admin/access-control.php');
