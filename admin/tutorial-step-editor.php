<?php
/**
 * Tutorial Step Editor
 *
 * Form-based editor for creating and editing tutorial steps
 */

require_once __DIR__ . '/layout.php';

use Classes\Db;

$database = new Db();

$stepId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = $stepId !== null;

// Load existing step if editing
$step = null;
$stepUi = null;
$stepValidation = null;
$stepPrerequisites = null;
$stepFeatures = null;
$interactions = [];
$highlights = [];
$contextChanges = [];
$nextPreparation = [];

if ($isEdit) {
    // Load step data
    $result = $database->exe("SELECT * FROM tutorial_steps WHERE id = ?", [$stepId]);
    $step = $result->fetch_assoc();

    if (!$step) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Step not found'];
        header('Location: tutorial.php');
        exit;
    }

    // Load related data
    $result = $database->exe("SELECT * FROM tutorial_step_ui WHERE step_id = ?", [$stepId]);
    $stepUi = $result->fetch_assoc();

    $result = $database->exe("SELECT * FROM tutorial_step_validation WHERE step_id = ?", [$stepId]);
    $stepValidation = $result->fetch_assoc();

    $result = $database->exe("SELECT * FROM tutorial_step_prerequisites WHERE step_id = ?", [$stepId]);
    $stepPrerequisites = $result->fetch_assoc();

    $result = $database->exe("SELECT * FROM tutorial_step_features WHERE step_id = ?", [$stepId]);
    $stepFeatures = $result->fetch_assoc();

    // Load interactions
    $result = $database->exe("SELECT * FROM tutorial_step_interactions WHERE step_id = ? ORDER BY id", [$stepId]);
    while ($row = $result->fetch_assoc()) {
        $interactions[] = $row;
    }

    // Load highlights
    $result = $database->exe("SELECT * FROM tutorial_step_highlights WHERE step_id = ? ORDER BY id", [$stepId]);
    while ($row = $result->fetch_assoc()) {
        $highlights[] = $row;
    }

    // Load context changes
    $result = $database->exe("SELECT * FROM tutorial_step_context_changes WHERE step_id = ? ORDER BY id", [$stepId]);
    while ($row = $result->fetch_assoc()) {
        $contextChanges[] = $row;
    }

    // Load next preparation
    $result = $database->exe("SELECT * FROM tutorial_step_next_preparation WHERE step_id = ? ORDER BY id", [$stepId]);
    while ($row = $result->fetch_assoc()) {
        $nextPreparation[] = $row;
    }
}

ob_start();
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= $isEdit ? 'Edit Step: ' . htmlspecialchars($step['title']) : 'Create New Tutorial Step' ?></h1>
        <a href="tutorial.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Tutorial Admin
        </a>
    </div>

    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?=$_SESSION['flash']['type']?>" role="alert">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <form method="post" action="tutorial-step-save.php" id="stepForm">
        <?php if ($isEdit): ?>
            <input type="hidden" name="db_step_id" value="<?=$stepId?>">
        <?php endif; ?>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">
                    Basic Info
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="ui-tab" data-toggle="tab" href="#ui" role="tab">
                    UI Config
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="validation-tab" data-toggle="tab" href="#validation" role="tab">
                    Validation
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="prerequisites-tab" data-toggle="tab" href="#prerequisites" role="tab">
                    Prerequisites
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="interactions-tab" data-toggle="tab" href="#interactions" role="tab">
                    Interactions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="advanced-tab" data-toggle="tab" href="#advanced" role="tab">
                    Advanced
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Basic Info Tab -->
            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Basic Information</h3>

                        <div class="form-group">
                            <label for="version">Version *</label>
                            <input type="text" class="form-control" id="version" name="version"
                                   value="<?= $isEdit ? htmlspecialchars($step['version']) : '1.0.0' ?>" required>
                            <small class="form-text text-muted">Tutorial version (e.g., 1.0.0, 1.1.0)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="step_number">Step Number *</label>
                                    <input type="number" step="0.1" class="form-control" id="step_number" name="step_number"
                                           value="<?= $isEdit ? $step['step_number'] : '' ?>" required>
                                    <small class="form-text text-muted">Decimal allowed (e.g., 0.5, 1.0, 1.5)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="step_id">Step ID</label>
                                    <input type="text" class="form-control" id="step_id" name="step_id"
                                           value="<?= $isEdit ? htmlspecialchars($step['step_id'] ?? '') : '' ?>">
                                    <small class="form-text text-muted">Human-readable identifier (e.g., "first_movement")</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="step_type">Step Type *</label>
                                    <select class="form-control" id="step_type" name="step_type" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="info" <?= $isEdit && $step['step_type'] === 'info' ? 'selected' : '' ?>>Info</option>
                                        <option value="welcome" <?= $isEdit && $step['step_type'] === 'welcome' ? 'selected' : '' ?>>Welcome</option>
                                        <option value="dialog" <?= $isEdit && $step['step_type'] === 'dialog' ? 'selected' : '' ?>>Dialog</option>
                                        <option value="movement" <?= $isEdit && $step['step_type'] === 'movement' ? 'selected' : '' ?>>Movement</option>
                                        <option value="movement_limit" <?= $isEdit && $step['step_type'] === 'movement_limit' ? 'selected' : '' ?>>Movement Limit</option>
                                        <option value="action" <?= $isEdit && $step['step_type'] === 'action' ? 'selected' : '' ?>>Action</option>
                                        <option value="action_intro" <?= $isEdit && $step['step_type'] === 'action_intro' ? 'selected' : '' ?>>Action Intro</option>
                                        <option value="ui_interaction" <?= $isEdit && $step['step_type'] === 'ui_interaction' ? 'selected' : '' ?>>UI Interaction</option>
                                        <option value="combat" <?= $isEdit && $step['step_type'] === 'combat' ? 'selected' : '' ?>>Combat</option>
                                        <option value="combat_intro" <?= $isEdit && $step['step_type'] === 'combat_intro' ? 'selected' : '' ?>>Combat Intro</option>
                                        <option value="exploration" <?= $isEdit && $step['step_type'] === 'exploration' ? 'selected' : '' ?>>Exploration</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="xp_reward">XP Reward</label>
                                    <input type="number" class="form-control" id="xp_reward" name="xp_reward"
                                           value="<?= $isEdit ? $step['xp_reward'] : 0 ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input type="text" class="form-control" id="title" name="title"
                                   value="<?= $isEdit ? htmlspecialchars($step['title']) : '' ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="text">Step Text *</label>
                            <textarea class="form-control" id="text" name="text" rows="5" required><?= $isEdit ? htmlspecialchars($step['text']) : '' ?></textarea>
                            <small class="form-text text-muted">Supports HTML. Use <strong>&lt;strong&gt;</strong> for emphasis.</small>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                                   <?= $isEdit && $step['is_active'] ? 'checked' : (!$isEdit ? 'checked' : '') ?>>
                            <label class="form-check-label" for="is_active">
                                Active (step will be shown in tutorial)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- UI Config Tab -->
            <div class="tab-pane fade" id="ui" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">UI Configuration</h3>

                        <div class="form-group">
                            <label for="target_selector">Target Selector</label>
                            <input type="text" class="form-control font-monospace" id="target_selector" name="target_selector"
                                   value="<?= $isEdit && $stepUi ? htmlspecialchars($stepUi['target_selector'] ?? '') : '' ?>">
                            <small class="form-text text-muted">CSS selector for element to highlight (e.g., "#show-caracs", ".case[data-coords='0,0']")</small>
                        </div>

                        <div class="form-group">
                            <label for="tooltip_position">Tooltip Position</label>
                            <select class="form-control" id="tooltip_position" name="tooltip_position">
                                <option value="top" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'top' ? 'selected' : '' ?>>Top</option>
                                <option value="bottom" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'bottom' ? 'selected' : 'selected' ?>>Bottom</option>
                                <option value="left" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'left' ? 'selected' : '' ?>>Left</option>
                                <option value="right" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'right' ? 'selected' : '' ?>>Right</option>
                                <option value="center" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'center' ? 'selected' : '' ?>>Center</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="interaction_mode">Interaction Mode</label>
                            <select class="form-control" id="interaction_mode" name="interaction_mode">
                                <option value="blocking" <?= $isEdit && $stepUi && $stepUi['interaction_mode'] === 'blocking' ? 'selected' : 'selected' ?>>Blocking (full overlay)</option>
                                <option value="semi-blocking" <?= $isEdit && $stepUi && $stepUi['interaction_mode'] === 'semi-blocking' ? 'selected' : '' ?>>Semi-blocking (allow specific elements)</option>
                                <option value="open" <?= $isEdit && $stepUi && $stepUi['interaction_mode'] === 'open' ? 'selected' : '' ?>>Open (no overlay)</option>
                            </select>
                            <small class="form-text text-muted">Blocking = only "Next" button works. Semi-blocking = some elements clickable. Open = everything clickable.</small>
                        </div>

                        <div class="form-group">
                            <label for="blocked_click_message">Blocked Click Message</label>
                            <textarea class="form-control" id="blocked_click_message" name="blocked_click_message" rows="2"><?= $isEdit && $stepUi ? htmlspecialchars($stepUi['blocked_click_message'] ?? '') : '' ?></textarea>
                            <small class="form-text text-muted">Message shown when user clicks blocked element</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="show_delay">Show Delay (ms)</label>
                                    <input type="number" class="form-control" id="show_delay" name="show_delay"
                                           value="<?= $isEdit && $stepUi ? $stepUi['show_delay'] : 0 ?>">
                                    <small class="form-text text-muted">Delay before showing tooltip (useful if UI needs time to render)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="auto_advance_delay">Auto-Advance Delay (ms)</label>
                                    <input type="number" class="form-control" id="auto_advance_delay" name="auto_advance_delay"
                                           value="<?= $isEdit && $stepUi && $stepUi['auto_advance_delay'] !== null ? $stepUi['auto_advance_delay'] : '' ?>">
                                    <small class="form-text text-muted">Auto-advance to next step after this delay (leave empty for manual only)</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="auto_close_card" name="auto_close_card" value="1"
                                   <?= $isEdit && $stepUi && $stepUi['auto_close_card'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_close_card">
                                Auto-close action card on step complete
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation Tab -->
            <div class="tab-pane fade" id="validation" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Validation Rules</h3>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="requires_validation" name="requires_validation" value="1"
                                   <?= $isEdit && $stepValidation && $stepValidation['requires_validation'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="requires_validation">
                                Requires validation (step must be completed before advancing)
                            </label>
                        </div>

                        <div id="validationFields" style="display: <?= $isEdit && $stepValidation && $stepValidation['requires_validation'] ? 'block' : 'none' ?>">
                            <div class="form-group">
                                <label for="validation_type">Validation Type</label>
                                <select class="form-control" id="validation_type" name="validation_type">
                                    <option value="">-- Select Type --</option>
                                    <option value="any_movement" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'any_movement' ? 'selected' : '' ?>>Any Movement</option>
                                    <option value="movements_depleted" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'movements_depleted' ? 'selected' : '' ?>>Movements Depleted</option>
                                    <option value="position" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'position' ? 'selected' : '' ?>>Position (exact X, Y)</option>
                                    <option value="adjacent_to_position" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'adjacent_to_position' ? 'selected' : '' ?>>Adjacent to Position</option>
                                    <option value="action_used" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'action_used' ? 'selected' : '' ?>>Action Used</option>
                                    <option value="ui_panel_opened" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'ui_panel_opened' ? 'selected' : '' ?>>UI Panel Opened</option>
                                    <option value="ui_element_hidden" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'ui_element_hidden' ? 'selected' : '' ?>>UI Element Hidden</option>
                                    <option value="ui_interaction" <?= $isEdit && $stepValidation && $stepValidation['validation_type'] === 'ui_interaction' ? 'selected' : '' ?>>UI Interaction (element clicked)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="validation_hint">Validation Hint</label>
                                <textarea class="form-control" id="validation_hint" name="validation_hint" rows="2"><?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['validation_hint'] ?? '') : '' ?></textarea>
                                <small class="form-text text-muted">Hint shown when validation fails</small>
                            </div>

                            <hr>
                            <h5>Validation Parameters</h5>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="target_x">Target X</label>
                                        <input type="number" class="form-control" id="target_x" name="target_x"
                                               value="<?= $isEdit && $stepValidation && $stepValidation['target_x'] !== null ? $stepValidation['target_x'] : '' ?>">
                                        <small class="form-text text-muted">For position/adjacent_to_position validation</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="target_y">Target Y</label>
                                        <input type="number" class="form-control" id="target_y" name="target_y"
                                               value="<?= $isEdit && $stepValidation && $stepValidation['target_y'] !== null ? $stepValidation['target_y'] : '' ?>">
                                        <small class="form-text text-muted">For position/adjacent_to_position validation</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="action_name">Action Name</label>
                                <input type="text" class="form-control" id="action_name" name="action_name"
                                       value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['action_name'] ?? '') : '' ?>">
                                <small class="form-text text-muted">For action_used validation (e.g., "fouiller", "attaquer")</small>
                            </div>

                            <div class="form-group">
                                <label for="panel_id">Panel ID</label>
                                <input type="text" class="form-control" id="panel_id" name="panel_id"
                                       value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['panel_id'] ?? '') : '' ?>">
                                <small class="form-text text-muted">For ui_panel_opened validation (e.g., "actions", "characteristics")</small>
                            </div>

                            <div class="form-group">
                                <label for="element_clicked">Element Clicked</label>
                                <input type="text" class="form-control font-monospace" id="element_clicked" name="element_clicked"
                                       value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['element_clicked'] ?? '') : '' ?>">
                                <small class="form-text text-muted">For ui_interaction validation (CSS selector)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prerequisites Tab -->
            <div class="tab-pane fade" id="prerequisites" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Prerequisites</h3>
                        <p class="text-muted">Resources required before step can start</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mvt_required">Movement Points Required</label>
                                    <input type="number" class="form-control" id="mvt_required" name="mvt_required"
                                           value="<?= $isEdit && $stepPrerequisites && $stepPrerequisites['mvt_required'] !== null ? $stepPrerequisites['mvt_required'] : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pa_required">Action Points Required</label>
                                    <input type="number" class="form-control" id="pa_required" name="pa_required"
                                           value="<?= $isEdit && $stepPrerequisites && $stepPrerequisites['pa_required'] !== null ? $stepPrerequisites['pa_required'] : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="auto_restore" name="auto_restore" value="1"
                                   <?= $isEdit && $stepPrerequisites && $stepPrerequisites['auto_restore'] ? 'checked' : 'checked' ?>>
                            <label class="form-check-label" for="auto_restore">
                                Auto-restore resources on step start
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="consume_movements" name="consume_movements" value="1"
                                   <?= $isEdit && $stepPrerequisites && $stepPrerequisites['consume_movements'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="consume_movements">
                                Consume movement points when moving
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="unlimited_mvt" name="unlimited_mvt" value="1"
                                   <?= $isEdit && $stepPrerequisites && $stepPrerequisites['unlimited_mvt'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="unlimited_mvt">
                                Unlimited movement points
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="unlimited_pa" name="unlimited_pa" value="1"
                                   <?= $isEdit && $stepPrerequisites && $stepPrerequisites['unlimited_pa'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="unlimited_pa">
                                Unlimited action points
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interactions Tab -->
            <div class="tab-pane fade" id="interactions" role="tabpanel">
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title">Allowed Interactions</h3>
                        <p class="text-muted">For semi-blocking mode: elements that user can click</p>

                        <div id="interactionsList">
                            <?php foreach ($interactions as $index => $interaction): ?>
                                <div class="input-group mb-2 interaction-row">
                                    <input type="text" class="form-control font-monospace" name="interactions[]"
                                           value="<?= htmlspecialchars($interaction['selector']) ?>"
                                           placeholder=".selector or #id">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-danger remove-row">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-success" id="addInteraction">
                            <i class="fas fa-plus"></i> Add Interaction
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Additional Highlights</h3>
                        <p class="text-muted">Extra elements to highlight (beyond target_selector)</p>

                        <div id="highlightsList">
                            <?php foreach ($highlights as $index => $highlight): ?>
                                <div class="input-group mb-2 highlight-row">
                                    <input type="text" class="form-control font-monospace" name="highlights[]"
                                           value="<?= htmlspecialchars($highlight['selector']) ?>"
                                           placeholder=".selector or #id">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-danger remove-row">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-success" id="addHighlight">
                            <i class="fas fa-plus"></i> Add Highlight
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Tab -->
            <div class="tab-pane fade" id="advanced" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Advanced Features</h3>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="celebration" name="celebration" value="1"
                                   <?= $isEdit && $stepFeatures && $stepFeatures['celebration'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="celebration">
                                Show celebration animation
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="show_rewards" name="show_rewards" value="1"
                                   <?= $isEdit && $stepFeatures && $stepFeatures['show_rewards'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_rewards">
                                Show rewards summary
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="redirect_delay">Redirect Delay (ms)</label>
                            <input type="number" class="form-control" id="redirect_delay" name="redirect_delay"
                                   value="<?= $isEdit && $stepFeatures && $stepFeatures['redirect_delay'] !== null ? $stepFeatures['redirect_delay'] : '' ?>">
                            <small class="form-text text-muted">Auto-redirect to main game after this delay (for final step)</small>
                        </div>

                        <hr>

                        <h5>Context Changes</h5>
                        <p class="text-muted">State modifications during this step</p>

                        <div id="contextChangesList">
                            <?php foreach ($contextChanges as $index => $change): ?>
                                <div class="row mb-2 context-row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="context_keys[]"
                                               value="<?= htmlspecialchars($change['context_key']) ?>"
                                               placeholder="Key (e.g., set_mvt_limit)">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="context_values[]"
                                               value="<?= htmlspecialchars($change['context_value']) ?>"
                                               placeholder="Value">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger remove-row w-100">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-success" id="addContextChange">
                            <i class="fas fa-plus"></i> Add Context Change
                        </button>

                        <hr>

                        <h5>Next Step Preparation</h5>
                        <p class="text-muted">Actions to run after step completion (for next step)</p>

                        <div id="nextPrepList">
                            <?php foreach ($nextPreparation as $index => $prep): ?>
                                <div class="row mb-2 prep-row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="prep_keys[]"
                                               value="<?= htmlspecialchars($prep['preparation_key']) ?>"
                                               placeholder="Key (e.g., restore_mvt)">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="prep_values[]"
                                               value="<?= htmlspecialchars($prep['preparation_value']) ?>"
                                               placeholder="Value">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger remove-row w-100">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-success" id="addNextPrep">
                            <i class="fas fa-plus"></i> Add Preparation
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card mt-4">
            <div class="card-body">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> <?= $isEdit ? 'Update Step' : 'Create Step' ?>
                </button>
                <a href="tutorial.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
</div>

<script>
// Tab switching with Bootstrap
document.querySelectorAll('[data-toggle="tab"]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');

        // Hide all tab panes
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });

        // Remove active from all tabs
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });

        // Show target pane
        document.querySelector(targetId).classList.add('show', 'active');

        // Set active tab
        this.classList.add('active');
    });
});

// Toggle validation fields visibility
document.getElementById('requires_validation').addEventListener('change', function() {
    document.getElementById('validationFields').style.display = this.checked ? 'block' : 'none';
});

// Add/Remove Interaction
document.getElementById('addInteraction').addEventListener('click', function() {
    const list = document.getElementById('interactionsList');
    const row = document.createElement('div');
    row.className = 'input-group mb-2 interaction-row';
    row.innerHTML = `
        <input type="text" class="form-control font-monospace" name="interactions[]" placeholder=".selector or #id">
        <div class="input-group-append">
            <button type="button" class="btn btn-danger remove-row">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    list.appendChild(row);
});

// Add/Remove Highlight
document.getElementById('addHighlight').addEventListener('click', function() {
    const list = document.getElementById('highlightsList');
    const row = document.createElement('div');
    row.className = 'input-group mb-2 highlight-row';
    row.innerHTML = `
        <input type="text" class="form-control font-monospace" name="highlights[]" placeholder=".selector or #id">
        <div class="input-group-append">
            <button type="button" class="btn btn-danger remove-row">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    list.appendChild(row);
});

// Add/Remove Context Change
document.getElementById('addContextChange').addEventListener('click', function() {
    const list = document.getElementById('contextChangesList');
    const row = document.createElement('div');
    row.className = 'row mb-2 context-row';
    row.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" name="context_keys[]" placeholder="Key (e.g., set_mvt_limit)">
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control" name="context_values[]" placeholder="Value">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger remove-row w-100">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    list.appendChild(row);
});

// Add/Remove Next Prep
document.getElementById('addNextPrep').addEventListener('click', function() {
    const list = document.getElementById('nextPrepList');
    const row = document.createElement('div');
    row.className = 'row mb-2 prep-row';
    row.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" name="prep_keys[]" placeholder="Key (e.g., restore_mvt)">
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control" name="prep_values[]" placeholder="Value">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger remove-row w-100">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    list.appendChild(row);
});

// Remove row (using event delegation)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row') || e.target.closest('.remove-row')) {
        const btn = e.target.classList.contains('remove-row') ? e.target : e.target.closest('.remove-row');
        const row = btn.closest('.interaction-row, .highlight-row, .context-row, .prep-row');
        if (row) {
            row.remove();
        }
    }
});
</script>

<style>
.font-monospace {
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}

.nav-tabs .nav-link {
    border-radius: 0.25rem 0.25rem 0 0;
}

.nav-tabs .nav-link.active {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 1.5rem;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1.1rem;
}
</style>

<?php
$content = ob_get_clean();
echo admin_layout('Tutorial Step Editor', $content);
?>
