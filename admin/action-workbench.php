<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Action\Schema\Form\RawParamsEditor;
use App\Action\Schema\ParameterSchema;
use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionConditionEditService;
use App\Service\Action\ActionCreateService;
use App\Service\Action\ActionOutcomeEditService;
use App\Service\Action\ActionTypeInstructionResolver;
use App\Service\Action\RpgAwesomeIcons;
use App\Service\CsrfProtectionService;
use App\Service\OutcomeInstructionService;
use App\View\Action\AutomaticOutcomesView;
use App\View\Action\IconFieldView;
use App\View\Action\ConditionEditorView;
use App\View\Action\DeleteActionFormView;
use App\View\Action\ExportButtonView;
use App\View\Action\NewActionFormView;
use App\View\Action\OutcomeEditorView;
use App\View\Action\SimulationPanelView;

$catalogService = new ActionCatalogService();
$actions = $catalogService->listActions();
$actionTypes = (new ActionCreateService())->availableTypes();
$conditionTypes = (new ActionConditionEditService())->availableTypes();
$conditionEditor = new ConditionEditorView();
$outcomeEditService = new ActionOutcomeEditService();
$instructionTypes = $outcomeEditService->availableInstructionTypes();
$outcomeEditor = new OutcomeEditorView();
$automaticView = new AutomaticOutcomesView();
$typeInstructionResolver = new ActionTypeInstructionResolver();
$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$action = $id ? $catalogService->getActionById($id) : ($actions[0] ?? null);

// Active tab persists in the URL (?tab=) so a refresh stays put; a simulate POST opens on the results.
$activeTab = in_array($_GET['tab'] ?? null, ['config', 'sim'], true)
    ? $_GET['tab']
    : ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'sim' : 'config');

$renderer = new ParameterFieldRenderer();
$rawEditor = new RawParamsEditor();
$schemaCatalog = new ActionSchemaCatalog();
$instructionService = new OutcomeInstructionService();
$csrf = new CsrfProtectionService();

/**
 * Render the typed schema fields for a condition/instruction, followed by the
 * raw key→value editor for any parameters the schema doesn't model (the whole
 * map for schema-less handlers like RequiresTraitValue; the dynamic effect key
 * for ApplyStatus). $typedPrefix/$rawPrefix are the POST name prefixes.
 *
 * @param array<string, mixed> $params
 */
$renderParams = static function (ParameterSchema $schema, array $params, string $typedPrefix, string $rawPrefix) use ($renderer, $rawEditor): string {
    $out = '';
    $reserved = [];
    foreach ($schema->fields() as $field) {
        $reserved[] = $field->key;
        $out .= $renderer->render($field, $typedPrefix . '[' . $field->key . ']', $params[$field->key] ?? null);
    }
    $out .= $rawEditor->render($rawPrefix, $params, $reserved, $schema->isEmpty());

    return $out;
};

/* ---------- Panel 1: actions list ---------- */
ob_start();
foreach ($actions as $item) {
    $active = ($action && $item->getId() === $action->getId()) ? ' wb-item--active' : '';
    echo '<a class="wb-item' . $active . '" href="/admin/action-workbench.php?id=' . (int) $item->getId() . '&tab=' . $activeTab . '"'
        . ' title="' . e($item->getDisplayName()) . '"'
        . ' data-search="' . e(strtolower($item->getName() . ' ' . $item->getDisplayName() . ' ' . action_type_label($item) . ' ' . $item->getCategory())) . '">'
        . '<i class="ra ' . e($item->getIcon()) . ' wb-item-icon"></i>'
        . '<span class="wb-item-text">'
        . '<span class="wb-item-name">' . e($item->getDisplayName()) . '</span>'
        . '<span class="wb-item-meta">' . e(action_type_label($item)) . ' · niv.' . e($item->getLevel())
        . ' · ' . $item->getConditions()->count() . 'c/' . $item->getOutcomes()->count() . 'o</span>'
        . '</span>'
        . '</a>';
}
$listHtml = ob_get_clean();

$createFormHtml = (new NewActionFormView())->render($actionTypes, $csrf->renderTokenField());

/* ---------- Panel 2: configure (editor) ---------- */
ob_start();
if ($action === null) {
    echo '<p class="wb-empty">Sélectionnez une action.</p>';
} else {
    echo '<form method="post" action="/admin/action-save.php" class="wb-form">';
    echo $csrf->renderTokenField();
    echo '<input type="hidden" name="action_id" value="' . (int) $action->getId() . '">';
    echo '<input type="hidden" name="return_to" value="/admin/action-workbench.php?id=' . (int) $action->getId() . '">';

    echo '<div class="wb-meta">'
        . '<span class="badge badge-info">' . e(action_type_label($action)) . '</span>'
        . '<span class="wb-chip">niv. ' . e($action->getLevel()) . '</span>'
        . ($action->getCategory() ? '<span class="wb-chip">' . e($action->getCategory()) . '</span>' : '')
        . '<code class="wb-chip">' . e($action->getName()) . '</code>'
        . '</div>';

    echo (new IconFieldView())->render($action->getIcon());

    echo '<div class="wb-section-title wb-section-title--row">Conditions' . $conditionEditor->addControls($conditionTypes) . '</div>';
    if ($action->getConditions()->count() === 0) {
        echo '<p class="wb-muted">Aucune condition.</p>';
    }
    echo '<div class="wb-grid">';
    foreach ($action->getConditions() as $condition) {
        $schema = $schemaCatalog->schemaForCondition($condition->getConditionType());
        $params = $condition->getParameters() ?? [];
        echo '<div class="wb-block">';
        echo '<div class="wb-block-head">' . e($condition->getConditionType())
            . ($condition->isBlocking() ? ' <span class="badge badge-warning">bloquante</span>' : '')
            . $conditionEditor->removeButton((int) $condition->getId()) . '</div>';
        echo '<div class="wb-block-body">';
        echo $renderParams($schema, $params, 'cond[' . (int) $condition->getId() . ']', 'cond_raw[' . (int) $condition->getId() . ']');
        echo '</div></div>';
    }
    echo '</div>';

    echo '<div class="wb-section-title wb-section-title--row">Outcomes' . $outcomeEditor->addOutcomeControls() . '</div>';
    if ($action->getOutcomes()->count() === 0) {
        echo '<p class="wb-muted">Aucun outcome.</p>';
    }
    echo '<div class="wb-grid">';
    foreach ($action->getOutcomes() as $outcome) {
        echo '<div class="wb-block">';
        echo '<div class="wb-block-head">Outcome '
            . ($outcome->isOnSuccess() ? '<span class="badge badge-success">succès</span>' : '<span class="badge badge-danger">échec</span>')
            . ($outcome->getApplyToSelf() ? ' <span class="badge badge-info">sur soi</span>' : '')
            . ($outcome->getName() ? ' <code class="wb-chip">' . e($outcome->getName()) . '</code>' : '')
            . $outcomeEditor->removeOutcomeButton((int) $outcome->getId()) . '</div>';
        echo '<div class="wb-block-body">';
        $instructions = $instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId());
        if (count($instructions) === 0) {
            echo '<p class="wb-muted">Aucune instruction.</p>';
        }
        foreach ($instructions as $instruction) {
            $instructionType = OutcomeInstructionFactory::typeOf($instruction);
            $schema = $schemaCatalog->schemaForOutcomeInstruction($instructionType);
            $params = $instruction->getParameters() ?? [];
            echo '<div class="wb-inst-name">' . e($instructionType)
                . $outcomeEditor->removeInstructionButton((int) $instruction->getId()) . '</div>';
            echo $renderParams($schema, $params, 'inst[' . (int) $instruction->getId() . ']', 'inst_raw[' . (int) $instruction->getId() . ']');
        }
        echo $outcomeEditor->addInstructionControls((int) $outcome->getId(), $instructionTypes);
        echo '</div></div>';
    }
    echo '</div>';

    // Instructions inherited from the action's type (e.g. an attack's adrenaline),
    // shown read-only — they're configured on the type, not this action.
    echo $automaticView->render($typeInstructionResolver->resolve($action));
    echo '<p class="wb-muted"><a href="/admin/action-type-defaults.php">Gérer les défauts par type d\'action →</a></p>';

    echo $renderer->traitDatalist();
    echo '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer</button>'
        . (new ExportButtonView())->single((int) $action->getId()) . '</div>';
    echo '</form>';

    echo (new DeleteActionFormView())->render((int) $action->getId(), $csrf->renderTokenField());
}
$configHtml = ob_get_clean();

/* ---------- Panel 3: simulate (form + results side by side) ---------- */
ob_start();
if ($action === null) {
    echo '<p class="wb-empty">—</p>';
} else {
    $panel = new SimulationPanelView();
    $simResultHtml = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $panel->result($action, $_POST) : '';
    echo '<div class="wb-sim-form">' . $panel->form($action, $_POST) . '</div>';
    echo '<div class="wb-sim-result">' . $simResultHtml . '</div>';
}
$simHtml = ob_get_clean();

/* ---------- Assemble the single-screen layout ---------- */
ob_start();
?>

<div class="wb">
    <div class="wb-col wb-col--list">
        <div class="wb-col-head">
            <span class="wb-col-head-title">Actions <small><?= count($actions) ?></small></span>
            <button type="button" class="wb-fold-toggle" id="wb-fold" title="Replier / déplier la liste">⟨⟩</button>
        </div>
        <div class="wb-col-body">
            <?= $createFormHtml ?>
            <input type="text" class="wb-search" id="wb-search" placeholder="Filtrer…" autocomplete="off">
            <div class="wb-list" id="wb-list"><?= $listHtml ?></div>
        </div>
    </div>

    <div class="wb-col">
        <div class="wb-col-head wb-tabs">
            <div class="wb-tabbtns">
                <button type="button" class="wb-tab-btn<?= $activeTab === 'config' ? ' active' : '' ?>" data-tab="config">Configurer</button>
                <button type="button" class="wb-tab-btn<?= $activeTab === 'sim' ? ' active' : '' ?>" data-tab="sim">Simuler</button>
            </div>
            <small><?= $action ? e($action->getDisplayName()) : '' ?></small>
        </div>
        <div class="wb-col-body">
            <div class="wb-tab wb-config" data-tab="config"<?= $activeTab === 'config' ? '' : ' hidden' ?>><?= renderFlashMessage() . $configHtml ?></div>
            <div class="wb-tab wb-sim" data-tab="sim"<?= $activeTab === 'sim' ? '' : ' hidden' ?>><?= $simHtml ?></div>
        </div>
    </div>
</div>
<script>window.WB_ICONS = <?= json_encode((new RpgAwesomeIcons())->all(), JSON_UNESCAPED_SLASHES) ?>;</script>

<?php
$content = ob_get_clean();
echo admin_layout('Workbench', $content, [
    'styles' => ['/css/rpg-awesome.min.css', '/admin/css/action-simulate.css', '/admin/css/action-workbench.css'],
    'scripts' => ['/admin/js/action-simulate.js', '/admin/js/action-workbench.js'],
]);
