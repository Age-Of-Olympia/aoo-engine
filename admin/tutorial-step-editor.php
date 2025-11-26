<?php
/**
 * Tutorial Step Editor
 *
 * Form-based editor for creating and editing tutorial steps
 */

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\CsrfProtectionService;

$database = new Db();
$csrf = new CsrfProtectionService();

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

    <?= renderFlashMessage() ?>

    <!-- Quick Actions Panel -->
    <div class="card mb-3 quick-actions-card">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-muted"><i class="fas fa-bolt"></i> Quick Actions</h6>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="btn-preview" title="Preview step">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <?php if ($isEdit): ?>
                    <button type="button" class="btn btn-outline-secondary" id="btn-duplicate" title="Duplicate this step">
                        <i class="fas fa-copy"></i> Duplicate
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary" id="btn-export" title="Export as JSON">
                        <i class="fas fa-upload"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-import" title="Import from JSON">
                        <i class="fas fa-download"></i> Import
                    </button>
                    <input type="file" id="import-file-input" accept=".json" style="display: none;">
                    <button type="button" class="btn btn-outline-info" id="btn-test" title="Test this step">
                        <i class="fas fa-play"></i> Test
                    </button>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="tutorial-step-save.php" id="stepForm">
        <?= $csrf->renderTokenField() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="db_step_id" value="<?=$stepId?>">
        <?php endif; ?>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">
                    Basic Info
                    <span class="tab-status" id="status-basic"></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="ui-tab" data-toggle="tab" href="#ui" role="tab">
                    UI Config
                    <span class="tab-status" id="status-ui"></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="validation-tab" data-toggle="tab" href="#validation" role="tab">
                    Validation
                    <span class="tab-status" id="status-validation"></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="prerequisites-tab" data-toggle="tab" href="#prerequisites" role="tab">
                    Prerequisites
                    <span class="tab-status" id="status-prerequisites"></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="interactions-tab" data-toggle="tab" href="#interactions" role="tab">
                    Interactions
                    <span class="tab-status" id="status-interactions"></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="advanced-tab" data-toggle="tab" href="#advanced" role="tab">
                    Advanced
                    <span class="tab-status" id="status-advanced"></span>
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="step_number">Step Number *</label>
                                    <input type="number" step="0.1" class="form-control" id="step_number" name="step_number"
                                           value="<?= $isEdit ? $step['step_number'] : '' ?>" required>
                                    <small class="form-text text-muted">Decimal allowed (e.g., 0.5, 1.0, 1.5)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="step_id">Step ID</label>
                                    <input type="text" class="form-control" id="step_id" name="step_id"
                                           value="<?= $isEdit ? htmlspecialchars($step['step_id'] ?? '') : '' ?>">
                                    <small class="form-text text-muted">Human-readable identifier (e.g., "first_movement")</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="next_step">Next Step ID</label>
                                    <input type="text" class="form-control" id="next_step" name="next_step"
                                           value="<?= $isEdit ? htmlspecialchars($step['next_step'] ?? '') : '' ?>">
                                    <small class="form-text text-muted">Next step identifier (empty = final step)</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="step_type">
                                        Step Type *
                                        <i class="fas fa-question-circle help-icon" title="The type determines which validation and UI options are relevant"></i>
                                    </label>
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
                                    <small class="form-text text-muted">
                                        Use <strong>ui_interaction</strong> for info steps with "Suivant" button
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="template_selector">
                                        Quick Start Template
                                        <i class="fas fa-magic help-icon" title="Load pre-configured settings for common step patterns"></i>
                                    </label>
                                    <select class="form-control" id="template_selector">
                                        <option value="">-- Choose a template --</option>
                                        <option value="info_manual_advance">📝 Info Step (Manual Advance)</option>
                                        <option value="movement_basic">🚶 Movement Step</option>
                                        <option value="movement_position">🎯 Move to Position</option>
                                        <option value="action_use">⚡ Use Action</option>
                                        <option value="ui_open_panel">🖼️ Open UI Panel</option>
                                        <option value="combat_basic">⚔️ Combat Step</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        Load pre-configured settings for common step patterns
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
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
                            <label for="target_description">Target Description</label>
                            <input type="text" class="form-control" id="target_description" name="target_description"
                                   value="<?= $isEdit && $stepUi ? htmlspecialchars($stepUi['target_description'] ?? '') : '' ?>">
                            <small class="form-text text-muted">Human-readable description (e.g., "Characteristics button")</small>
                        </div>

                        <div class="form-group">
                            <label for="highlight_selector">Highlight Selector</label>
                            <input type="text" class="form-control font-monospace" id="highlight_selector" name="highlight_selector"
                                   value="<?= $isEdit && $stepUi ? htmlspecialchars($stepUi['highlight_selector'] ?? '') : '' ?>">
                            <small class="form-text text-muted">Alternative CSS selector for highlighting (if different from target)</small>
                        </div>

                        <div class="form-group">
                            <label for="tooltip_position">Tooltip Position</label>
                            <select class="form-control" id="tooltip_position" name="tooltip_position">
                                <option value="top" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'top' ? 'selected' : '' ?>>Top</option>
                                <option value="bottom" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'bottom' ? 'selected' : 'selected' ?>>Bottom</option>
                                <option value="left" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'left' ? 'selected' : '' ?>>Left</option>
                                <option value="right" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'right' ? 'selected' : '' ?>>Right</option>
                                <option value="center" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'center' ? 'selected' : '' ?>>Center (Middle)</option>
                                <option value="center-top" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'center-top' ? 'selected' : '' ?>>Center (Top)</option>
                                <option value="center-bottom" <?= $isEdit && $stepUi && $stepUi['tooltip_position'] === 'center-bottom' ? 'selected' : '' ?>>Center (Bottom)</option>
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
                            <input type="checkbox" class="form-check-input" id="allow_manual_advance" name="allow_manual_advance" value="1"
                                   <?= $isEdit && $stepUi && $stepUi['allow_manual_advance'] ? 'checked' : 'checked' ?>>
                            <label class="form-check-label" for="allow_manual_advance">
                                Allow manual advance (show "Next" button)
                            </label>
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
                                <label for="movement_count">Movement Count</label>
                                <input type="number" class="form-control" id="movement_count" name="movement_count"
                                       value="<?= $isEdit && $stepValidation && $stepValidation['movement_count'] !== null ? $stepValidation['movement_count'] : '' ?>">
                                <small class="form-text text-muted">For specific_count validation (number of movements required)</small>
                            </div>

                            <div class="form-group">
                                <label for="action_name">Action Name</label>
                                <input type="text" class="form-control" id="action_name" name="action_name"
                                       value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['action_name'] ?? '') : '' ?>">
                                <small class="form-text text-muted">For action_used validation (e.g., "fouiller", "attaquer")</small>
                            </div>

                            <div class="form-group">
                                <label for="action_charges_required">Action Charges Required</label>
                                <input type="number" class="form-control" id="action_charges_required" name="action_charges_required"
                                       value="<?= $isEdit && $stepValidation ? $stepValidation['action_charges_required'] : 1 ?>">
                                <small class="form-text text-muted">Number of times action must be used (default: 1)</small>
                            </div>

                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="combat_required" name="combat_required" value="1"
                                       <?= $isEdit && $stepValidation && $stepValidation['combat_required'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="combat_required">
                                    Combat required
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="panel_id">Panel ID</label>
                                <input type="text" class="form-control" id="panel_id" name="panel_id"
                                       value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['panel_id'] ?? '') : '' ?>">
                                <small class="form-text text-muted">For ui_panel_opened validation (e.g., "actions", "characteristics")</small>
                            </div>

                            <div class="form-group">
                                <label for="element_selector">Element Selector</label>
                                <input type="text" class="form-control font-monospace" id="element_selector" name="element_selector"
                                       value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['element_selector'] ?? '') : '' ?>">
                                <small class="form-text text-muted">For ui_element_hidden validation (CSS selector of element that should be hidden)</small>
                            </div>

                            <div class="form-group">
                                <label for="element_clicked">Element Clicked</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace" id="element_clicked" name="element_clicked"
                                           value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['element_clicked'] ?? '') : '' ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="btnManualAdvance" title="Configure as Manual Advance step">
                                            <i class="fas fa-hand-pointer"></i> Manual Advance
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    For ui_interaction validation (CSS selector).
                                    <strong>Tip:</strong> Use <code>tutorial_next</code> to create an info step where clicking "Suivant" button advances the step.
                                </small>
                            </div>

                            <div class="alert alert-info mt-3">
                                <strong><i class="fas fa-info-circle"></i> Manual Advance Pattern:</strong>
                                <p class="mb-1">For informational steps that require user acknowledgment:</p>
                                <ol class="mb-0 pl-3">
                                    <li>Set <strong>Validation Type</strong> to <code>ui_interaction</code></li>
                                    <li>Set <strong>Element Clicked</strong> to <code>tutorial_next</code></li>
                                    <li>Set <strong>Step Type</strong> (Basic Info tab) to <code>ui_interaction</code></li>
                                </ol>
                                <p class="mb-0 mt-2"><small>This will show the "Suivant" button and advance when clicked.</small></p>
                            </div>

                            <div class="form-group">
                                <label for="dialog_id">Dialog ID</label>
                                <input type="text" class="form-control" id="dialog_id" name="dialog_id"
                                       value="<?= $isEdit && $stepValidation ? htmlspecialchars($stepValidation['dialog_id'] ?? '') : '' ?>">
                                <small class="form-text text-muted">Dialog that must be completed (references tutorial_dialogs.dialog_id)</small>
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

                        <hr>
                        <h5>Entity Setup</h5>

                        <div class="form-group">
                            <label for="spawn_enemy">Spawn Enemy</label>
                            <input type="text" class="form-control" id="spawn_enemy" name="spawn_enemy"
                                   value="<?= $isEdit && $stepPrerequisites ? htmlspecialchars($stepPrerequisites['spawn_enemy'] ?? '') : '' ?>">
                            <small class="form-text text-muted">Enemy type to spawn (e.g., "tutorial_dummy")</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ensure_harvestable_tree_x">Harvestable Tree X</label>
                                    <input type="number" class="form-control" id="ensure_harvestable_tree_x" name="ensure_harvestable_tree_x"
                                           value="<?= $isEdit && $stepPrerequisites && $stepPrerequisites['ensure_harvestable_tree_x'] !== null ? $stepPrerequisites['ensure_harvestable_tree_x'] : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ensure_harvestable_tree_y">Harvestable Tree Y</label>
                                    <input type="number" class="form-control" id="ensure_harvestable_tree_y" name="ensure_harvestable_tree_y"
                                           value="<?= $isEdit && $stepPrerequisites && $stepPrerequisites['ensure_harvestable_tree_y'] !== null ? $stepPrerequisites['ensure_harvestable_tree_y'] : '' ?>">
                                </div>
                            </div>
                        </div>
                        <small class="form-text text-muted">Ensure a harvestable tree exists at these coordinates for gathering steps</small>
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

// Manual Advance button - auto-configure for info step with "Suivant" button
document.getElementById('btnManualAdvance').addEventListener('click', function() {
    // 1. Set step_type to ui_interaction
    const stepTypeField = document.getElementById('step_type');
    if (stepTypeField) {
        stepTypeField.value = 'ui_interaction';
    }

    // 2. Check/enable validation
    const requiresValidationCheckbox = document.getElementById('requires_validation');
    if (!requiresValidationCheckbox.checked) {
        requiresValidationCheckbox.checked = true;
        requiresValidationCheckbox.dispatchEvent(new Event('change'));
    }

    // 3. Set validation type
    document.getElementById('validation_type').value = 'ui_interaction';

    // 4. Set element_clicked
    document.getElementById('element_clicked').value = 'tutorial_next';

    // Show success message
    alert('✅ Manual Advance configured!\n\n' +
          'All settings applied:\n' +
          '- Step Type: ui_interaction\n' +
          '- Requires Validation: enabled\n' +
          '- Validation Type: ui_interaction\n' +
          '- Element Clicked: tutorial_next\n\n' +
          'This step will now show the "Suivant" button and advance when clicked.');

    // Briefly highlight the step_type field to show it was changed
    if (stepTypeField) {
        stepTypeField.style.backgroundColor = '#d4edda';
        stepTypeField.style.transition = 'background-color 0.3s';
        setTimeout(() => {
            stepTypeField.style.backgroundColor = '';
        }, 1500);
    }
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

// =========================================
// ENHANCED FEATURES - Phase 1 & 2
// =========================================

// Template System
document.getElementById('template_selector').addEventListener('change', function() {
    const templateId = this.value;
    if (!templateId) return;

    const template = TUTORIAL_TEMPLATES[templateId];
    if (!template) return;

    // Confirm with user
    if (!confirm(`Load template "${template.name}"?\n\n${template.description}\n\nThis will overwrite current settings.`)) {
        this.value = '';
        return;
    }

    // Apply template config
    const config = template.config;

    // Basic fields
    if (config.step_type) document.getElementById('step_type').value = config.step_type;
    if (config.xp_reward !== undefined) document.getElementById('xp_reward').value = config.xp_reward;

    // UI fields
    if (config.interaction_mode) document.getElementById('interaction_mode').value = config.interaction_mode;
    if (config.tooltip_position) document.getElementById('tooltip_position').value = config.tooltip_position;
    if (config.show_delay !== undefined) document.getElementById('show_delay').value = config.show_delay;

    // Validation fields
    if (config.requires_validation !== undefined) {
        document.getElementById('requires_validation').checked = config.requires_validation;
        document.getElementById('requires_validation').dispatchEvent(new Event('change'));
    }
    if (config.validation_type) document.getElementById('validation_type').value = config.validation_type;
    if (config.element_clicked) document.getElementById('element_clicked').value = config.element_clicked;

    // Prerequisites
    if (config.mvt_required !== undefined) document.getElementById('mvt_required').value = config.mvt_required;
    if (config.pa_required !== undefined) document.getElementById('pa_required').value = config.pa_required;
    if (config.auto_restore !== undefined) document.getElementById('auto_restore').checked = config.auto_restore;

    // Action fields
    if (config.action_name) document.getElementById('action_name').value = config.action_name;

    // Show success message & trigger updates
    showToast('success', `Template "${template.name}" loaded! Fill in the remaining fields.`);
    updateFieldVisibility();
    updateTabStatus();

    // Reset dropdown
    this.value = '';

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Contextual Field Visibility
function updateFieldVisibility() {
    const stepType = document.getElementById('step_type').value;
    const validationType = document.getElementById('validation_type').value;

    // Define which fields are relevant for each step type
    const fieldRelevance = {
        'movement': {
            show: ['target_x', 'target_y', 'movement_count', 'mvt_required'],
            hide: ['action_name', 'panel_id', 'dialog_id', 'pa_required']
        },
        'movement_limit': {
            show: ['target_x', 'target_y', 'movement_count', 'mvt_required'],
            hide: ['action_name', 'panel_id', 'dialog_id', 'pa_required']
        },
        'action': {
            show: ['action_name', 'action_charges_required', 'pa_required'],
            hide: ['panel_id', 'dialog_id', 'target_x', 'target_y']
        },
        'ui_interaction': {
            show: ['element_clicked', 'element_selector', 'panel_id'],
            hide: ['action_name', 'target_x', 'target_y', 'combat_required']
        },
        'combat': {
            show: ['action_name', 'combat_required', 'pa_required'],
            hide: ['panel_id', 'dialog_id']
        },
        'dialog': {
            show: ['dialog_id'],
            hide: ['target_x', 'target_y', 'action_name', 'panel_id']
        },
        'info': {
            show: [],
            hide: ['target_x', 'target_y', 'action_name', 'panel_id', 'dialog_id']
        },
        'welcome': {
            show: [],
            hide: ['target_x', 'target_y', 'action_name', 'panel_id', 'dialog_id']
        }
    };

    // Apply visibility rules
    const rules = fieldRelevance[stepType] || { show: [], hide: [] };

    rules.show.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        const formGroup = field?.closest('.form-group');
        if (formGroup) {
            formGroup.style.display = 'block';
            formGroup.classList.add('relevant-field');
        }
    });

    rules.hide.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        const formGroup = field?.closest('.form-group');
        if (formGroup) {
            formGroup.style.display = 'none';
            formGroup.classList.remove('relevant-field');
        }
    });

    // Validation-specific visibility
    if (validationType === 'position' || validationType === 'adjacent_to_position') {
        ['target_x', 'target_y'].forEach(id => {
            const field = document.getElementById(id);
            const formGroup = field?.closest('.form-group');
            if (formGroup) formGroup.style.display = 'block';
        });
    }

    if (validationType === 'action_used') {
        const field = document.getElementById('action_name');
        const formGroup = field?.closest('.form-group');
        if (formGroup) formGroup.style.display = 'block';
    }

    if (validationType === 'ui_panel_opened') {
        const field = document.getElementById('panel_id');
        const formGroup = field?.closest('.form-group');
        if (formGroup) formGroup.style.display = 'block';
    }
}

// Tab Status Indicators
function updateTabStatus() {
    // Basic Info tab
    const title = document.getElementById('title').value;
    const text = document.getElementById('text').value;
    const stepType = document.getElementById('step_type').value;

    const basicComplete = title && text && stepType;
    updateStatusIcon('status-basic', basicComplete ? 'complete' : 'incomplete');

    // UI Config tab
    const interactionMode = document.getElementById('interaction_mode').value;
    const uiComplete = interactionMode !== '';
    updateStatusIcon('status-ui', uiComplete ? 'complete' : 'optional');

    // Validation tab
    const requiresValidation = document.getElementById('requires_validation').checked;
    const validationType = document.getElementById('validation_type').value;

    let validationComplete = !requiresValidation;
    if (requiresValidation) {
        validationComplete = validationType !== '';
    }
    updateStatusIcon('status-validation', validationComplete ? 'complete' : 'warning');

    // Prerequisites, Interactions, Advanced - optional
    updateStatusIcon('status-prerequisites', 'optional');
    updateStatusIcon('status-interactions', 'optional');
    updateStatusIcon('status-advanced', 'optional');
}

function updateStatusIcon(elementId, status) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const icons = {
        complete: '<i class="fas fa-check-circle text-success"></i>',
        warning: '<i class="fas fa-exclamation-triangle text-warning"></i>',
        incomplete: '<i class="fas fa-times-circle text-danger"></i>',
        optional: '<i class="fas fa-circle text-muted"></i>'
    };

    element.innerHTML = icons[status] || '';
}

// Quick Actions: Preview
document.getElementById('btn-preview').addEventListener('click', function() {
    const title = document.getElementById('title').value || 'Untitled Step';
    const text = document.getElementById('text').value || 'No text provided';
    const position = document.getElementById('tooltip_position').value || 'center';

    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.className = 'preview-modal-overlay';
    overlay.innerHTML = `
        <div class="preview-modal">
            <div class="preview-modal-header">
                <h5>Step Preview</h5>
                <button type="button" class="preview-modal-close">&times;</button>
            </div>
            <div class="preview-modal-body">
                <div class="preview-container">
                    <div class="tutorial-tooltip ${position}">
                        <div class="tooltip-content">
                            <h3 class="tooltip-title">${title}</h3>
                            <div class="tooltip-text">${text}</div>
                            <button class="btn-tutorial-primary">Suivant &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    // Close on button click or overlay click
    overlay.querySelector('.preview-modal-close').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.remove();
    });
});

// Quick Actions: Duplicate
const duplicateBtn = document.getElementById('btn-duplicate');
if (duplicateBtn) {
    duplicateBtn.addEventListener('click', function() {
        if (confirm('Create a duplicate of this step?\n\nThe new step will have all the same settings but a new step number.')) {
            document.getElementById('step_id').value = '';
            const currentNumber = parseFloat(document.getElementById('step_number').value) || 0;
            document.getElementById('step_number').value = currentNumber + 0.1;

            window.scrollTo({ top: 0, behavior: 'smooth' });
            showToast('info', 'Step duplicated! Update the step number and save.');
        }
    });
}

// Quick Actions: Export JSON
document.getElementById('btn-export').addEventListener('click', function() {
    const formData = new FormData(document.getElementById('stepForm'));
    const json = {};
    formData.forEach((value, key) => {
        json[key] = value;
    });

    const blob = new Blob([JSON.stringify(json, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `tutorial-step-${json.step_id || 'export'}.json`;
    a.click();
    URL.revokeObjectURL(url);

    showToast('success', 'Step exported as JSON!');
});

// Quick Actions: Import
document.getElementById('btn-import').addEventListener('click', function() {
    document.getElementById('import-file-input').click();
});

document.getElementById('import-file-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
        try {
            const json = JSON.parse(event.target.result);

            if (!confirm('Import this step configuration?\n\nThis will overwrite current form values.')) {
                return;
            }

            // Map JSON keys to form fields
            const fieldMap = {
                'version': 'version',
                'step_number': 'step_number',
                'step_id': 'step_id',
                'next_step': 'next_step',
                'step_type': 'step_type',
                'xp_reward': 'xp_reward',
                'title': 'title',
                'text': 'text',
                'target_selector': 'target_selector',
                'target_description': 'target_description',
                'highlight_selector': 'highlight_selector',
                'tooltip_position': 'tooltip_position',
                'interaction_mode': 'interaction_mode',
                'blocked_click_message': 'blocked_click_message',
                'show_delay': 'show_delay',
                'auto_advance_delay': 'auto_advance_delay',
                'validation_type': 'validation_type',
                'validation_hint': 'validation_hint',
                'target_x': 'target_x',
                'target_y': 'target_y',
                'movement_count': 'movement_count',
                'action_name': 'action_name',
                'action_charges_required': 'action_charges_required',
                'panel_id': 'panel_id',
                'element_selector': 'element_selector',
                'element_clicked': 'element_clicked',
                'dialog_id': 'dialog_id',
                'mvt_required': 'mvt_required',
                'pa_required': 'pa_required',
                'spawn_enemy': 'spawn_enemy',
                'ensure_harvestable_tree_x': 'ensure_harvestable_tree_x',
                'ensure_harvestable_tree_y': 'ensure_harvestable_tree_y',
                'redirect_delay': 'redirect_delay'
            };

            // Apply values to text/number/select fields
            for (const [jsonKey, fieldId] of Object.entries(fieldMap)) {
                const field = document.getElementById(fieldId);
                if (field && json[jsonKey] !== undefined) {
                    field.value = json[jsonKey];
                }
            }

            // Handle checkbox fields
            const checkboxFields = {
                'is_active': 'is_active',
                'allow_manual_advance': 'allow_manual_advance',
                'auto_close_card': 'auto_close_card',
                'requires_validation': 'requires_validation',
                'combat_required': 'combat_required',
                'auto_restore': 'auto_restore',
                'consume_movements': 'consume_movements',
                'unlimited_mvt': 'unlimited_mvt',
                'unlimited_pa': 'unlimited_pa',
                'celebration': 'celebration',
                'show_rewards': 'show_rewards'
            };

            for (const [jsonKey, fieldId] of Object.entries(checkboxFields)) {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.checked = json[jsonKey] === '1' || json[jsonKey] === 1 || json[jsonKey] === true;
                }
            }

            // Trigger validation fields visibility
            document.getElementById('requires_validation').dispatchEvent(new Event('change'));

            // Update UI
            updateFieldVisibility();
            updateTabStatus();

            showToast('success', 'Step imported successfully!');
        } catch (err) {
            showToast('danger', 'Failed to import: ' + err.message);
        }
    };
    reader.readAsText(file);

    // Reset input so same file can be selected again
    this.value = '';
});

// Quick Actions: Test
document.getElementById('btn-test').addEventListener('click', function() {
    const stepId = document.getElementById('step_id').value;
    const dbStepId = document.querySelector('input[name="db_step_id"]')?.value;

    if (!stepId && !dbStepId) {
        showToast('warning', 'Please save the step first before testing.');
        return;
    }

    // Open game in new tab with tutorial test mode
    const testUrl = `/?tutorial_test=1&step=${encodeURIComponent(stepId || dbStepId)}`;

    const confirmMsg = `Open the game to test this step?\n\n` +
        `Step ID: ${stepId || 'N/A'}\n\n` +
        `Note: You need to be logged in and have an active tutorial session to test steps.`;

    if (confirm(confirmMsg)) {
        window.open(testUrl, '_blank');
    }
});

// Toast Notification Function
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show toast-notification`;
    toast.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Event listeners for updates
document.getElementById('step_type').addEventListener('change', () => {
    updateFieldVisibility();
    updateTabStatus();
});

document.getElementById('validation_type').addEventListener('change', updateFieldVisibility);

document.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('change', updateTabStatus);
    field.addEventListener('input', updateTabStatus);
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    updateFieldVisibility();
    updateTabStatus();
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

/* Preview Modal Styles */
.preview-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.preview-modal {
    background: white;
    border-radius: 8px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.preview-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
}

.preview-modal-header h5 {
    margin: 0;
    font-size: 1.25rem;
}

.preview-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    line-height: 1;
}

.preview-modal-close:hover {
    color: #333;
}

.preview-modal-body {
    padding: 1.5rem;
}

.preview-container {
    min-height: 300px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    padding: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-container .tutorial-tooltip {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    padding: 1.5rem;
    max-width: 400px;
}

.preview-container .tooltip-title {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 0.75rem;
    color: #333;
}

.preview-container .tooltip-text {
    font-size: 1rem;
    line-height: 1.5;
    color: #666;
    margin-bottom: 1rem;
}

.preview-container .btn-tutorial-primary {
    background: #007bff;
    color: white;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
}
</style>

<?php
$content = ob_get_clean();
echo admin_layout('Tutorial Step Editor', $content);
?>
