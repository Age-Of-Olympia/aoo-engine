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

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    (new ActionOutcomeEditService())->removeOutcome((int) ($_POST['outcome_id'] ?? 0));
    setFlash('success', 'Outcome retiré.');
    $csrf->regenerateToken();
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors du retrait de l'outcome.");
}

header('Location: /admin/action-workbench.php?id=' . $actionId . '&tab=config');
exit;
