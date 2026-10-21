<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionConditionEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.edit');

$actionId = (int) ($_POST['action_id'] ?? 0);

handleAdminPost('/admin/action-workbench.php?id=' . $actionId . '&tab=config', function (): string {
    (new ActionConditionEditService())->removeCondition((int) ($_POST['condition_id'] ?? 0));

    return 'Condition retirée.';
});
