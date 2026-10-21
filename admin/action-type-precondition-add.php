<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypePreconditionEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.type-defaults');

$typeKey = (string) ($_POST['type_key'] ?? '');
$redirect = (string) ($_POST['selected_type'] ?? $typeKey);

handleAdminPost('/admin/action-type-defaults.php?type=' . urlencode($redirect), function () use ($typeKey): string {
    (new ActionTypePreconditionEditService())->addPrecondition($typeKey, (string) ($_POST['condition_type'] ?? ''));

    return 'Précondition ajoutée.';
});
