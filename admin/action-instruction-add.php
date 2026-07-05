<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionOutcomeEditService;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/action-workbench.php');
    exit;
}

$csrf = new CsrfProtectionService();
$actionId = (int) ($_POST['action_id'] ?? 0);
$outcomeId = (int) ($_POST['outcome_id'] ?? 0);
// The select is named per outcome so multiple add controls don't collide.
$type = (string) ($_POST['instruction_type_' . $outcomeId] ?? '');

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionOutcomeEditService())->addInstruction($outcomeId, $type);
    setFlash('success', 'Instruction ajoutée.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'ajout de l'instruction.");
}

header('Location: /admin/action-workbench.php?id=' . $actionId . '&tab=config');
exit;
