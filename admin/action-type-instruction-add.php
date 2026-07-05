<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypeInstructionEditService;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/action-type-defaults.php');
    exit;
}

$csrf = new CsrfProtectionService();
$typeKey = (string) ($_POST['type_key'] ?? '');

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionTypeInstructionEditService())->addInstruction($typeKey, (string) ($_POST['instruction_type'] ?? ''));
    setFlash('success', 'Instruction ajoutée au type.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'ajout de l'instruction.");
}

header('Location: /admin/action-type-defaults.php?type=' . urlencode($typeKey));
exit;
