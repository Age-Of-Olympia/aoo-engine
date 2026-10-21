<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionOutcomeEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.edit');

$actionId = (int) ($_POST['action_id'] ?? 0);
$outcomeId = (int) ($_POST['outcome_id'] ?? 0);
$type = (string) ($_POST['instruction_type_' . $outcomeId] ?? '');

handleAdminPost('/admin/action-workbench.php?id=' . $actionId . '&tab=config', function () use ($outcomeId, $type): string {
    (new ActionOutcomeEditService())->addInstruction($outcomeId, $type);

    return 'Instruction ajoutée.';
});
