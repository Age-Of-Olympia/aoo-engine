<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypePreconditionEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.type-defaults');

$typeKey = (string) ($_POST['type_key'] ?? '');
$redirect = (string) ($_POST['selected_type'] ?? $typeKey);

handleAdminPost('/admin/action-type-defaults.php?type=' . urlencode($redirect), function () use ($typeKey): string {
    (new ActionTypePreconditionEditService())->saveParameters(
        $typeKey,
        is_array($_POST['type_precond'] ?? null) ? $_POST['type_precond'] : [],
        is_array($_POST['type_precond_raw'] ?? null) ? $_POST['type_precond_raw'] : [],
        is_array($_POST['type_precond_blocking'] ?? null) ? $_POST['type_precond_blocking'] : [],
    );

    return 'Préconditions enregistrées.';
});
