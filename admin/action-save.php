<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\Action\ActionSaveService;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/actions.php');
    exit;
}

$csrf = new CsrfProtectionService();
$actionId = (int) ($_POST['action_id'] ?? 0);
// Return to the caller (e.g. the workbench) when it asks; only same-site admin paths.
$returnTo = (string) ($_POST['return_to'] ?? '');
$editorUrl = (str_starts_with($returnTo, '/admin/') && !str_contains($returnTo, "\n"))
    ? $returnTo
    : '/admin/action-workbench.php?id=' . $actionId;

try {
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

    $conditionParams = is_array($_POST['cond'] ?? null) ? $_POST['cond'] : [];
    $instructionParams = is_array($_POST['inst'] ?? null) ? $_POST['inst'] : [];
    $conditionRaw = is_array($_POST['cond_raw'] ?? null) ? $_POST['cond_raw'] : [];
    $instructionRaw = is_array($_POST['inst_raw'] ?? null) ? $_POST['inst_raw'] : [];

    (new ActionSaveService())->saveParameters($actionId, $conditionParams, $instructionParams, $conditionRaw, $instructionRaw);

    setFlash('success', 'Paramètres enregistrés.');
    $csrf->regenerateToken();
    header('Location: ' . $editorUrl);
    exit;
} catch (\InvalidArgumentException $exception) {
    setFlash('warning', $exception->getMessage());
    header('Location: ' . $editorUrl);
    exit;
} catch (\RuntimeException $exception) {
    setFlash('danger', $exception->getMessage());
    header('Location: /admin/actions.php');
    exit;
} catch (\Throwable $exception) {
    setFlash('danger', "Erreur lors de l'enregistrement.");
    header('Location: ' . $editorUrl);
    exit;
}
