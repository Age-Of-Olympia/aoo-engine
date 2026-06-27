<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypePreconditionEditService;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/action-type-defaults.php');
    exit;
}

$csrf = new CsrfProtectionService();
$typeKey = (string) ($_POST['type_key'] ?? '');
$redirect = (string) ($_POST['selected_type'] ?? $typeKey);

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionTypePreconditionEditService())->saveParameters(
        $typeKey,
        is_array($_POST['type_precond'] ?? null) ? $_POST['type_precond'] : [],
        is_array($_POST['type_precond_raw'] ?? null) ? $_POST['type_precond_raw'] : [],
        is_array($_POST['type_precond_blocking'] ?? null) ? $_POST['type_precond_blocking'] : [],
    );
    setFlash('success', 'Préconditions enregistrées.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'enregistrement des préconditions.");
}

header('Location: /admin/action-type-defaults.php?type=' . urlencode($redirect));
exit;
