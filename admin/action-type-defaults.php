<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Action\Schema\ActionSchemaCatalog;
use App\Service\Action\ActionOutcomeEditService;
use App\Service\Action\ActionTypeInstructionEditService;
use App\Service\Action\ActionTypeLogEditService;
use App\Service\Action\ActionTypePreconditionEditService;
use App\Service\Action\ActionTypeRegistry;
use App\Service\Action\ActionTypeXpEditService;
use App\Service\CsrfProtectionService;
use App\View\Action\ActionTypeTreeView;
use App\View\Action\TypeDefaultsView;
use App\View\Action\TypeLogEditorView;
use App\View\Action\TypePreconditionsView;
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

// Préconditions: the global scope (applies to every action, e.g. "Plan: enfers")
// plus the selected type's own preconditions.
$preconditionService = new ActionTypePreconditionEditService();
$conditionTypes = (new ActionSchemaCatalog())->allConditionTypes();
$preconditionsView = new TypePreconditionsView();
$precondSection = $preconditionsView->render(
    ActionTypePreconditionEditService::GLOBAL_SCOPE,
    'Global — toutes les actions',
    $preconditionService->preconditionsForType(ActionTypePreconditionEditService::GLOBAL_SCOPE),
    $conditionTypes,
    $selectedType,
    $csrf->renderTokenField(),
) . $preconditionsView->render(
    $selectedType,
    'Type « ' . $selectedType . ' »',
    $preconditionService->preconditionsForType($selectedType),
    $conditionTypes,
    $selectedType,
    $csrf->renderTokenField(),
);

$body = (new TypeDefaultsView())->render(
    $selectedType,
    $treeRail,
    $instructions,
    $instructionTypes,
    $csrf->renderTokenField(),
    [
        ['label' => 'Préconditions', 'html' => $precondSection],
        ['label' => 'Expérience', 'html' => $xpSection],
        ['label' => 'Journal', 'html' => $logSection],
    ],
);

// The export of all per-type config (XP + log templates) is rendered inside the
// layout's left column by TypeDefaultsView (shared wb-list-header). Import reuses
// the generic /admin/action-import.php — it routes by the bundle's objectType.
echo admin_layout('Défauts par type d\'action', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/action-workbench.css', '/admin/css/action-type-tree.css'],
    'scripts' => ['/admin/js/action-type-tree.js', '/admin/js/action-type-defaults.js'],
]);
