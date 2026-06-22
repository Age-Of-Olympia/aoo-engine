<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveCatalogService;
use App\Service\CsrfProtectionService;
use App\View\Action\PassiveWorkbenchView;

$catalog = new ActionPassiveCatalogService();
$passives = $catalog->listPassives();
$id = (int) ($_GET['id'] ?? 0);
$selected = $id ? $catalog->getById($id) : ($passives[0] ?? null);
$csrf = new CsrfProtectionService();

$body = (new PassiveWorkbenchView())->render($passives, $selected, $csrf->renderTokenField());

echo admin_layout('Passifs', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/action-workbench.css'],
    'scripts' => ['/admin/js/action-workbench.js'],
]);
