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
    (new ActionTypePreconditionEditService())->addPrecondition($typeKey, (string) ($_POST['condition_type'] ?? ''));
    setFlash('success', 'Précondition ajoutée.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'ajout de la précondition.");
}

header('Location: /admin/action-type-defaults.php?type=' . urlencode($redirect));
exit;
