<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionPassiveCatalogService;
use App\Service\CsrfProtectionService;
use App\Service\SkillOwnershipService;
use App\View\Action\PassiveWorkbenchView;

$catalog = new ActionPassiveCatalogService();
$passives = $catalog->listPassives();
$id = (int) ($_GET['id'] ?? 0);
$selected = $id ? $catalog->getById($id) : ($passives[0] ?? null);
$csrf = new CsrfProtectionService();

$ownerCount = $selected !== null
    ? ((new SkillOwnershipService())->passiveOwnerCounts()[$selected->getId()] ?? 0)
    : 0;

$body = (new PassiveWorkbenchView())->render($passives, $selected, $csrf->renderTokenField(), $ownerCount);

echo admin_layout('Passifs', renderFlashMessage() . $body, [
    'styles' => ['/css/rpg-awesome.min.css', '/admin/css/action-workbench.css'],
    'scripts' => ['/admin/js/action-workbench.js'],
]);
