<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionOutcomeEditService;
use App\Service\Action\ActionTypeInstructionEditService;
use App\Service\Action\ActionTypeLogEditService;
use App\Service\Action\ActionTypeRegistry;
use App\Service\Action\ActionTypeXpEditService;
use App\Service\CsrfProtectionService;
use App\View\Action\ActionTypeTreeView;
use App\View\Action\TypeDefaultsView;
use App\View\Action\TypeLogEditorView;
use App\View\Action\TypeXpEditorView;

$registry = new ActionTypeRegistry();
$assignableTypes = $registry->assignableTypes();
$selectedType = (string) ($_GET['type'] ?? '');
if (!isset($assignableTypes[$selectedType])) {
    $selectedType = (string) array_key_first($assignableTypes);
}

$editService = new ActionTypeInstructionEditService();
$instructions = $editService->instructionsForType($selectedType);
$instructionTypes = (new ActionOutcomeEditService())->availableInstructionTypes();
$csrf = new CsrfProtectionService();

$treeRail = (new ActionTypeTreeView())->render(
    $registry->tree(),
    '/admin/action-type-defaults.php',
    $selectedType,
    $editService->countsByType(),
);

$logTemplates = (new ActionTypeLogEditService())->templatesForType($selectedType);
$logSection = (new TypeLogEditorView())->render(
    $selectedType,
    $logTemplates['actor'],
    $logTemplates['target'],
    $csrf->renderTokenField(),
    $logTemplates['inheritedFrom'],
    $logTemplates['overriddenParent'],
);

$xpConfig = (new ActionTypeXpEditService())->configForType($selectedType);
$xpSection = (new TypeXpEditorView())->render(
    $selectedType,
    $xpConfig['mode'],
    $xpConfig['params'],
    $csrf->renderTokenField(),
    $xpConfig['inheritedFrom'],
    $xpConfig['overriddenParent'],
);

$body = (new TypeDefaultsView())->render(
    $selectedType,
    $treeRail,
    $instructions,
    $instructionTypes,
    $csrf->renderTokenField(),
    $xpSection . $logSection,
);

echo admin_layout('Défauts par type d\'action', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/action-workbench.css', '/admin/css/action-type-tree.css'],
    'scripts' => ['/admin/js/action-type-tree.js'],
]);
