<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypeInstructionEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.type-defaults');

$typeKey = (string) ($_POST['type_key'] ?? '');

handleAdminPost('/admin/action-type-defaults.php?type=' . urlencode($typeKey), function () use ($typeKey): string {
    (new ActionTypeInstructionEditService())->saveParameters(
        $typeKey,
        is_array($_POST['type_inst'] ?? null) ? $_POST['type_inst'] : [],
        is_array($_POST['type_inst_raw'] ?? null) ? $_POST['type_inst_raw'] : [],
    );

    return 'Défauts du type enregistrés.';
});
