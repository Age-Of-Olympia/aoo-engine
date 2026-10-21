<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionTypePreconditionEditService;
use App\Service\AdminMenuAccessService;

(new AdminMenuAccessService())->enforce('actions.type-defaults');

$redirect = (string) ($_POST['selected_type'] ?? ($_POST['type_key'] ?? ''));

handleAdminPost('/admin/action-type-defaults.php?type=' . urlencode($redirect), function (): string {
    (new ActionTypePreconditionEditService())->removePrecondition((int) ($_POST['precondition_id'] ?? 0));

    return 'Précondition retirée.';
});
