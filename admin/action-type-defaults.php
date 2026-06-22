<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionOutcomeEditService;
use App\Service\Action\ActionTypeInstructionEditService;
use App\Service\Action\ActionTypeRegistry;
use App\Service\CsrfProtectionService;
use App\View\Action\TypeDefaultsView;

$assignableTypes = (new ActionTypeRegistry())->assignableTypes();
$selectedType = (string) ($_GET['type'] ?? '');
if (!isset($assignableTypes[$selectedType])) {
    $selectedType = (string) array_key_first($assignableTypes);
}

$editService = new ActionTypeInstructionEditService();
$instructions = $editService->instructionsForType($selectedType);
$instructionTypes = (new ActionOutcomeEditService())->availableInstructionTypes();
$csrf = new CsrfProtectionService();

$body = (new TypeDefaultsView())->render(
    $selectedType,
    $assignableTypes,
    $instructions,
    $instructionTypes,
    $csrf->renderTokenField(),
);

echo admin_layout('Défauts par type d\'action', renderFlashMessage() . $body, [
    'styles' => ['/admin/css/action-workbench.css'],
]);
