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
$redirect = (string) ($_POST['selected_type'] ?? ($_POST['type_key'] ?? ''));

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionTypePreconditionEditService())->removePrecondition((int) ($_POST['precondition_id'] ?? 0));
    setFlash('success', 'Précondition retirée.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', 'Erreur lors du retrait de la précondition.');
}

header('Location: /admin/action-type-defaults.php?type=' . urlencode($redirect));
exit;
