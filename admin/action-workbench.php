<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Action\Schema\Form\RawParamsEditor;
use App\Action\Schema\OptionCatalog;
use App\Action\Schema\ParameterSchema;
use App\Service\Action\ActionCatalogService;
use App\Service\Action\ActionConditionEditService;
use App\Service\Action\ActionCreateService;
use App\Service\Action\ActionOutcomeEditService;
use App\Service\Action\ActionTargeting;
use App\Service\Action\ActionTypeInstructionResolver;
use App\Service\Action\ActionTypeRegistry;
use App\Service\Action\RpgAwesomeIcons;
use App\Service\CsrfProtectionService;
use App\Service\OutcomeInstructionService;
use App\Service\RaceService;
use App\View\Action\AutomaticOutcomesView;
use App\View\Action\IconFieldView;
use App\View\Action\ConditionEditorView;
use App\View\Action\DeleteActionFormView;
use App\View\Action\ExportButtonView;
use App\View\Action\WorkbenchFooterView;
use App\View\Action\WorkbenchLayoutView;
use App\View\Action\NewActionFormView;
use App\View\Action\OutcomeEditorView;
use App\View\Action\SimulationPanelView;
use App\View\Action\WorkbenchListHeaderView;

$catalogService = new ActionCatalogService();
$actions = $catalogService->listActions();

// Order the list to match the "Types d'action" rail: by the type's depth-first
// position in the type tree, then by name. (Sorted here, not in listActions(),
// because the tree order is the PHP class hierarchy — not expressible in SQL —
// and other callers of listActions() keep the catalogue order.)
$typeRegistry = new ActionTypeRegistry();
$typeOrder = $typeRegistry->typeOrderIndex();
$ranked = [];
foreach ($actions as $item) {
    $typeKey = $typeRegistry->typeKeysForAction($item)[0] ?? '';
    $ranked[] = ['idx' => $typeOrder[$typeKey] ?? PHP_INT_MAX, 'name' => $item->getName(), 'action' => $item];
}
usort($ranked, static fn (array $a, array $b): int => [$a['idx'], $a['name']] <=> [$b['idx'], $b['name']]);
$actions = array_map(static fn (array $row) => $row['action'], $ranked);
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
    // Tint the name with the race colour when the action is race-gated, so the
    // list reads at a glance (coloured = restricted, default = all races).
    $itemRace = (string) $item->getRace();
    $nameStyle = $itemRace !== '' ? ' style="color: ' . e(RaceService::getRaceColor($itemRace)) . '"' : '';
    $raceMeta = $itemRace !== '' ? ' · ' . e(ucfirst($itemRace)) : '';
    echo '<a class="wb-item' . $active . '" href="/admin/action-workbench.php?id=' . (int) $item->getId() . '&tab=' . $activeTab . '"'
        . ' title="' . e($item->getDisplayName()) . '"'
        . ' data-search="' . e(strtolower($item->getName() . ' ' . $item->getDisplayName() . ' ' . action_type_label($item) . ' ' . $item->getCategory())) . '">'
        . (new \App\View\Action\ActionIconView())->forAction($item, 'i', ['wb-item-icon'])
        . '<span class="wb-item-text">'
        . '<span class="wb-item-name"' . $nameStyle . '>' . e($item->getDisplayName()) . '</span>'
        . '<span class="wb-item-meta">' . e(action_type_label($item)) . ' · niv.' . e($item->getLevel())
        . ' · ' . $item->getConditions()->count() . 'c/' . $item->getOutcomes()->count() . 'o' . $raceMeta . '</span>'
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
    echo '<form method="post" action="/admin/action-save.php" id="wb-action-form" class="wb-form">';
    echo $csrf->renderTokenField();
    echo '<input type="hidden" name="action_id" value="' . (int) $action->getId() . '">';
    echo '<input type="hidden" name="return_to" value="/admin/action-workbench.php?id=' . (int) $action->getId() . '">';

    echo '<div class="wb-meta">'
        . '<span class="badge badge-info">' . e(action_type_label($action)) . '</span>'
        . '<span class="badge badge-secondary" title="Cible déterminée par le type d\'action et les outcomes « sur soi »">'
            . e((new ActionTargeting())->label($action)) . '</span>'
        . ($action->getCategory() ? '<span class="wb-chip">' . e($action->getCategory()) . '</span>' : '')
        . '<code class="wb-chip">' . e($action->getName()) . '</code>'
        . '</div>';

    // Legacy rows can hold NULL in these NOT NULL columns, which makes the typed
    // getter throw before init — read defensively (same guard as the exporter).
    $readProp = static function (string $property) use ($action) {
        $reflection = new ReflectionProperty(App\Entity\Action::class, $property);

        return $reflection->isInitialized($action) ? $reflection->getValue($action) : null;
    };

    // Race restriction — the scalar `race` the runtime gates on (empty = all
    // races). "—" clears it; an unknown stored value survives a save via la
    // sentinelle ⚠ de renderSelectOptions. Mirrors the passive editor.
    $currentRace = (string) $action->getRace();

    // Icon picker, level and race share one row — all small fields, no need for a
    // line each.
    echo '<div class="wb-iconlevel">'
        . (new IconFieldView())->render($action->getIcon(), 'icon', $action->getIconColor())
        . '<label class="wb-field wb-field--level"><span>Niveau</span>'
        . '<input class="form-control" type="number" name="level" min="0" value="' . (int) $readProp('level') . '"'
        . ' title="Prérequis d\'achat (à venir)"></label>'
        . '<label class="wb-field"><span>Race</span>'
        . formSelect('race', (new OptionCatalog())->races(), $currentRace !== '' ? $currentRace : null, '—',
            'class="form-control" title="Restreint l\'action à une race ; — = toutes"') . '</label>';

    // Catégorie — le regroupement des listes du jeu ; libre, avec datalist
    // des catégories déjà en usage pour rester cohérent d'un clic.
    $knownCategories = [];
    foreach ($actions as $catalogAction) {
        $categoryName = (string) $catalogAction->getCategory();
        if ($categoryName !== '') {
            $knownCategories[$categoryName] = $categoryName;
        }
    }
    ksort($knownCategories);

    echo '<label class="wb-field"><span>Catégorie</span>'
        . formInput('category', (string) $action->getCategory(),
            'list="wb-categories" title="Regroupement dans les listes ; vide = sans catégorie"')
        . '</label>'
        . renderDatalist('wb-categories', $knownCategories)
        . '</div>';
    echo '<label class="wb-field wb-field--wide"><span>Description</span>'
        . '<textarea class="form-control" name="text" rows="3">' . e((string) $readProp('text')) . '</textarea></label>';

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
            . ($condition->isDisplayContext() ? ' <span class="badge badge-info">contextuelle</span>' : '')
            . $conditionEditor->removeButton((int) $condition->getId()) . '</div>';
        echo '<div class="wb-block-body">';
        echo $renderParams($schema, $params, 'cond[' . (int) $condition->getId() . ']', 'cond_raw[' . (int) $condition->getId() . ']');
        /* Contexte d'affichage : le bouton du panneau n'apparaît que si
         * cette condition passe (évaluée au rendu, en plus du refus à
         * l'exécution). */
        echo '<label class="wb-field"><span>Contextuelle</span>'
            . '<span><input type="checkbox" name="cond_ctx[' . (int) $condition->getId() . ']" value="1" '
            . ($condition->isDisplayContext() ? 'checked' : '') . '>'
            . ' le bouton ne s\'affiche que si la condition passe</span></label>';
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
            . ' ' . $outcomeEditor->targetToggle((int) $outcome->getId(), $outcome->getApplyTo())
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
    echo '</form>';

    // The delete form is a sibling form (no nested forms); its button lives in
    // the shared footer, wired back via the HTML form= attribute.
    echo (new DeleteActionFormView())->render((int) $action->getId(), $csrf->renderTokenField());
    echo (new WorkbenchFooterView())->render(
        'wb-action-form',
        DeleteActionFormView::FORM_ID,
        "Supprimer l'action",
        (new ExportButtonView())->single((int) $action->getId()),
    );
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
    /* The result is a modal overlay (auto-opened after a run) so the form never
       shifts position between runs; a floating button re-opens it once closed. */
    if ($simResultHtml !== '') {
        echo '<div class="wb-modal is-open" id="sim-result-modal">'
            . '<div class="wb-modal-backdrop" data-close></div>'
            . '<div class="wb-modal-panel">'
            . '<div class="wb-modal-head"><span>Résultat de la simulation</span>'
            . '<div class="wb-modal-actions">'
            . '<button type="button" class="btn btn-sm btn-primary" id="sim-reroll">↻ Relancer</button>'
            . '<button type="button" class="wb-modal-close" data-close aria-label="Fermer">&times;</button>'
            . '</div></div>'
            . '<div class="wb-modal-body wb-sim-result">' . $simResultHtml . '</div>'
            . '</div></div>';
    }
}
$simHtml = ob_get_clean();

/* ---------- Assemble the single-screen layout (shared workbench shell) ---------- */
$listBody = (new WorkbenchListHeaderView())->render($createFormHtml, 'action', 'Exporter tout')
    . '<input type="text" class="wb-search" id="wb-search" placeholder="Filtrer…" autocomplete="off">'
    . '<div class="wb-list" id="wb-list">' . $listHtml . '</div>';

$editorHead = '<div class="wb-tabbtns">'
    . '<button type="button" class="wb-tab-btn' . ($activeTab === 'config' ? ' active' : '') . '" data-tab="config">Configurer</button>'
    . '<button type="button" class="wb-tab-btn' . ($activeTab === 'sim' ? ' active' : '') . '" data-tab="sim">Simuler</button>'
    . '</div><span class="wb-editor-title"'
    . ($action && (string) $action->getRace() !== '' ? ' style="color: ' . e(RaceService::getRaceColor($action->getRace())) . '"' : '')
    . '>' . ($action ? e($action->getDisplayName()) : '') . '</span>';

$editorBody = '<div class="wb-tab wb-config" data-tab="config"' . ($activeTab === 'config' ? '' : ' hidden') . '>' . renderFlashMessage() . $configHtml . '</div>'
    . '<div class="wb-tab wb-sim" data-tab="sim"' . ($activeTab === 'sim' ? '' : ' hidden') . '>' . $simHtml . '</div>';

$content = (new WorkbenchLayoutView())->render('Actions', count($actions), $listBody, $editorHead, $editorBody, 'wb-tabs')
    . '<script>window.WB_ICONS = ' . json_encode((new RpgAwesomeIcons())->all(), JSON_UNESCAPED_SLASHES) . ';</script>';
echo admin_layout('Workbench', $content, [
    'styles' => ['/css/rpg-awesome.min.css', '/admin/css/action-simulate.css', '/admin/css/action-workbench.css'],
    'scripts' => ['/admin/js/action-simulate.js', '/admin/js/action-workbench.js'],
]);
