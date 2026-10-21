<?php
/**
 * Tutorial step editor: creates and edits one step.
 */

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\CsrfProtectionService;
use App\Tutorial\TutorialContextKeys;
use App\Tutorial\TutorialOptions;
use App\Tutorial\TutorialStepFormData;
use App\Tutorial\TutorialTemplates;

$database = new Db();
$csrf = new CsrfProtectionService();

$stepId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit = $stepId !== null;

// Pre-fill the version with the tutorial selected in the step list
$defaultVersion = trim($_GET['version'] ?? '') ?: '1.0.0';

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
        setFlash('danger', 'Étape introuvable.');
        redirectTo('/admin/tutorial.php');
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

// Repopulate from the failed save stashed by tutorial-step-save.php
// instead of wiping the form back to DB values / defaults.
$oldInput = $_SESSION['tutorial_step_old_input'] ?? null;
unset($_SESSION['tutorial_step_old_input']);

$hasFormData = $isEdit;
if (is_array($oldInput)) {
    $hasFormData = true;
    $step = TutorialStepFormData::step($oldInput);
    $stepUi = TutorialStepFormData::ui($oldInput);
    $stepValidation = TutorialStepFormData::validation($oldInput);
    $stepPrerequisites = TutorialStepFormData::prerequisites($oldInput);
    $stepFeatures = TutorialStepFormData::features($oldInput);
    $interactions = TutorialStepFormData::selectorList($oldInput, 'interactions');
    $highlights = TutorialStepFormData::selectorList($oldInput, 'highlights');
    $contextChanges = TutorialStepFormData::keyValueList($oldInput, 'context_keys', 'context_values', 'context_key', 'context_value');
    $nextPreparation = TutorialStepFormData::keyValueList($oldInput, 'prep_keys', 'prep_values', 'preparation_key', 'preparation_value');
}

ob_start();
?>

<div class="container">

    <!-- Quick Actions Panel -->
    <div class="card mb-3 quick-actions-card">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-muted"><i class="fas fa-bolt"></i> Actions rapides</h6>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="btn-preview" title="Aperçu de l'étape">
                        <i class="fas fa-eye"></i> Aperçu
                    </button>
                    <?php if ($isEdit): ?>
                    <button type="button" class="btn btn-outline-secondary" id="btn-duplicate" title="Dupliquer cette étape">
                        <i class="fas fa-copy"></i> Dupliquer
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary" id="btn-export" title="Télécharger en JSON">
                        <i class="fas fa-upload"></i> Exporter
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-import" title="Importer depuis un fichier JSON">
                        <i class="fas fa-download"></i> Importer
                    </button>
                    <input type="file" id="import-file-input" accept=".json" hidden>
                    <button type="button" class="btn btn-outline-info" id="btn-test" title="Tester cette étape dans le jeu">
                        <i class="fas fa-play"></i> Tester
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
        <ul class="nav-tabs" id="stepTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">
                    Général
                    <span class="tab-status" id="status-basic"></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="ui-tab" data-toggle="tab" href="#ui" role="tab">
                    Affichage
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
                    Prérequis
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
                    Avancé
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
                        <h3 class="card-title">Général</h3>

                        <div class="form-group">
                            <label for="version">Version *</label>
                            <input type="text" class="form-control" id="version" name="version"
                                   value="<?= htmlspecialchars($hasFormData ? $step['version'] : $defaultVersion) ?>" required>
                            <small class="form-text text-muted">Version du tutoriel (ex. 1.0.0, 1.1.0)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="step_number">Numéro *</label>
                                    <input type="number" step="0.1" class="form-control" id="step_number" name="step_number"
                                           value="<?= $hasFormData ? $step['step_number'] : '' ?>" required>
                                    <small class="form-text text-muted">Décimales acceptées (ex. 0.5, 1.0, 1.5)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="step_id">Identifiant</label>
                                    <input type="text" class="form-control" id="step_id" name="step_id"
                                           value="<?= $hasFormData ? htmlspecialchars($step['step_id'] ?? '') : '' ?>">
                                    <small class="form-text text-muted">Identifiant lisible (ex. « first_movement »)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="next_step">Étape suivante</label>
                                    <input type="text" class="form-control" id="next_step" name="next_step"
                                           value="<?= $hasFormData ? htmlspecialchars($step['next_step'] ?? '') : '' ?>">
                                    <small class="form-text text-muted">Identifiant de l'étape suivante (vide : dernière étape)</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="step_type">
                                        Type *
                                        <i class="fas fa-question-circle help-icon" title="Le type détermine les options de validation et d'affichage proposées"></i>
                                    </label>
                                    <select class="form-control" id="step_type" name="step_type" required>
                                        <?= renderSelectOptions(
                                            TutorialOptions::STEP_TYPES,
                                            $hasFormData ? (string)($step['step_type'] ?? '') : null,
                                            '-- Choisir un type --'
                                        ) ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        <strong>Clic dans l'interface</strong> pour une étape d'information validée par le bouton « Suivant »
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="template_selector">
                                        Modèle
                                        <i class="fas fa-magic help-icon" title="Remplit les réglages d'un type d'étape courant"></i>
                                    </label>
                                    <select class="form-control" id="template_selector">
                                        <?= renderSelectOptions(
                                            TutorialTemplates::dropdownOptions(),
                                            null,
                                            '-- Choisir un modèle --'
                                        ) ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Remplit les réglages d'un type d'étape courant
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="xp_reward">XP gagnée</label>
                                    <input type="number" class="form-control" id="xp_reward" name="xp_reward"
                                           value="<?= $hasFormData ? $step['xp_reward'] : 0 ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="title">Titre *</label>
                            <input type="text" class="form-control" id="title" name="title"
                                   value="<?= $hasFormData ? htmlspecialchars($step['title']) : '' ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="text">Texte *</label>
                            <textarea class="form-control" id="text" name="text" rows="5" required><?= $hasFormData ? htmlspecialchars($step['text']) : '' ?></textarea>
                            <small class="form-text text-muted">
                                HTML accepté : <strong>&lt;strong&gt;</strong> pour mettre en gras.
                                <br>
                                <strong>Valeurs remplacées à l'affichage :</strong>
                                <code>{max_mvt}</code> (mouvements maximum de la race),
                                <code>{max_pa}</code> (points d'action maximum),
                                <code>{player_name}</code>,
                                <code>{race}</code>
                            </small>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                                   <?= $hasFormData && $step['is_active'] ? 'checked' : (!$hasFormData ? 'checked' : '') ?>>
                            <label class="form-check-label" for="is_active">
                                Active (l'étape apparaît dans le tutoriel)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- UI Config Tab -->
            <div class="tab-pane fade" id="ui" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Affichage</h3>

                        <div class="form-group">
                            <label for="target_selector">Sélecteur de la cible</label>
                            <input type="text" class="form-control font-monospace" id="target_selector" name="target_selector"
                                   value="<?= $hasFormData && $stepUi ? htmlspecialchars($stepUi['target_selector'] ?? '') : '' ?>">
                            <small class="form-text text-muted">Élément à mettre en évidence, en sélecteur CSS (ex. <code>#show-caracs</code>, <code>.case[data-coords='0,0']</code>)</small>
                        </div>

                        <div class="form-group">
                            <label for="target_description">Description de la cible</label>
                            <input type="text" class="form-control" id="target_description" name="target_description"
                                   value="<?= $hasFormData && $stepUi ? htmlspecialchars($stepUi['target_description'] ?? '') : '' ?>">
                            <small class="form-text text-muted">Description lisible (ex. « bouton Caractéristiques »)</small>
                        </div>

                        <div class="form-group">
                            <label for="highlight_selector">Sélecteur de mise en évidence</label>
                            <input type="text" class="form-control font-monospace" id="highlight_selector" name="highlight_selector"
                                   value="<?= $hasFormData && $stepUi ? htmlspecialchars($stepUi['highlight_selector'] ?? '') : '' ?>">
                            <small class="form-text text-muted">Autre élément à mettre en évidence, s'il diffère de la cible</small>
                        </div>

                        <div class="form-group">
                            <label for="tooltip_position">Position de la bulle</label>
                            <select class="form-control" id="tooltip_position" name="tooltip_position">
                                <?= renderSelectOptions(
                                    TutorialOptions::TOOLTIP_POSITIONS,
                                    (string)($stepUi['tooltip_position'] ?? 'bottom')
                                ) ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="interaction_mode">Mode d'interaction</label>
                            <select class="form-control" id="interaction_mode" name="interaction_mode">
                                <?= renderSelectOptions(
                                    TutorialOptions::INTERACTION_MODES,
                                    (string)($stepUi['interaction_mode'] ?? 'blocking')
                                ) ?>
                            </select>
                            <small class="form-text text-muted">Bloquant : seul le bouton « Suivant » répond. Semi-bloquant : certains éléments restent cliquables. Libre : tout est cliquable.</small>
                        </div>

                        <div class="form-group">
                            <label for="blocked_click_message">Message sur clic bloqué</label>
                            <textarea class="form-control" id="blocked_click_message" name="blocked_click_message" rows="2"><?= $hasFormData && $stepUi ? htmlspecialchars($stepUi['blocked_click_message'] ?? '') : '' ?></textarea>
                            <small class="form-text text-muted">Message affiché quand le joueur clique sur un élément bloqué</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="show_delay">Délai d'affichage (ms)</label>
                                    <input type="number" class="form-control" id="show_delay" name="show_delay"
                                           value="<?= $hasFormData && $stepUi ? $stepUi['show_delay'] : 0 ?>">
                                    <small class="form-text text-muted">Délai avant l'affichage de la bulle (utile si l'interface met du temps à s'afficher)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="auto_advance_delay">Passage automatique (ms)</label>
                                    <input type="number" class="form-control" id="auto_advance_delay" name="auto_advance_delay"
                                           value="<?= $hasFormData && $stepUi && $stepUi['auto_advance_delay'] !== null ? $stepUi['auto_advance_delay'] : '' ?>">
                                    <small class="form-text text-muted">Passe à l'étape suivante après ce délai (vide : passage manuel seulement)</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="allow_manual_advance" name="allow_manual_advance" value="1"
                                   <?= $hasFormData && $stepUi && $stepUi['allow_manual_advance'] ? 'checked' : 'checked' ?>>
                            <label class="form-check-label" for="allow_manual_advance">
                                Passage manuel (bouton « Suivant » affiché)
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="auto_close_card" name="auto_close_card" value="1"
                                   <?= $hasFormData && $stepUi && $stepUi['auto_close_card'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_close_card">
                                Fermer la fiche d'action à la fin de l'étape
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="highlight_padding">Marge de mise en évidence (px)</label>
                            <input type="number" class="form-control" id="highlight_padding" name="highlight_padding"
                                   value="<?= $hasFormData && $stepUi && isset($stepUi['highlight_padding']) ? (int)$stepUi['highlight_padding'] : 0 ?>">
                            <small class="form-text text-muted">
                                Pixels ajoutés autour de la zone mise en évidence. 50 élargit d'une
                                case (par exemple les 8 cases autour du personnage pour une étape de
                                déplacement). 0 : au plus près de la cible.
                            </small>
                        </div>

                        <?php $caracsState = $hasFormData && $stepUi && isset($stepUi['caracs_panel_state']) ? $stepUi['caracs_panel_state'] : ''; ?>
                        <div class="form-group">
                            <label for="caracs_panel_state">Panneau Caractéristiques</label>
                            <select class="form-control" id="caracs_panel_state" name="caracs_panel_state">
                                <option value="" <?= $caracsState === '' || $caracsState === null ? 'selected' : '' ?>>Ne pas toucher (par défaut)</option>
                                <option value="open" <?= $caracsState === 'open' ? 'selected' : '' ?>>Ouvert au début de l'étape</option>
                                <option value="closed" <?= $caracsState === 'closed' ? 'selected' : '' ?>>Fermé au début de l'étape</option>
                            </select>
                            <small class="form-text text-muted">
                                État du panneau Caractéristiques au début de l'étape. « Fermé » quand
                                le joueur doit découvrir le panneau et l'ouvrir lui-même ; « Ouvert »
                                quand l'étape parle d'une valeur visible seulement dans le panneau.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation Tab -->
            <div class="tab-pane fade" id="validation" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Validation</h3>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="requires_validation" name="requires_validation" value="1"
                                   <?= $hasFormData && $stepValidation && $stepValidation['requires_validation'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="requires_validation">
                                Validation requise (l'étape doit être réussie pour passer à la suivante)
                            </label>
                        </div>

                        <div id="validationFields" style="display: <?= $hasFormData && $stepValidation && $stepValidation['requires_validation'] ? 'block' : 'none' ?>">
                            <div class="form-group">
                                <label for="validation_type">Type de validation</label>
                                <select class="form-control" id="validation_type" name="validation_type">
                                    <?= renderSelectOptions(
                                        TutorialOptions::VALIDATION_TYPES,
                                        $stepValidation['validation_type'] ?? null,
                                        '-- Choisir un type --'
                                    ) ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="validation_hint">Indice</label>
                                <textarea class="form-control" id="validation_hint" name="validation_hint" rows="2"><?= $hasFormData && $stepValidation ? htmlspecialchars($stepValidation['validation_hint'] ?? '') : '' ?></textarea>
                                <small class="form-text text-muted">Indice affiché quand la validation échoue</small>
                            </div>

                            <hr>
                            <h5>Paramètres de la validation</h5>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="target_x">Cible X</label>
                                        <input type="number" class="form-control" id="target_x" name="target_x"
                                               value="<?= $hasFormData && $stepValidation && $stepValidation['target_x'] !== null ? $stepValidation['target_x'] : '' ?>">
                                        <small class="form-text text-muted">For position/adjacent_to_position validation</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="target_y">Cible Y</label>
                                        <input type="number" class="form-control" id="target_y" name="target_y"
                                               value="<?= $hasFormData && $stepValidation && $stepValidation['target_y'] !== null ? $stepValidation['target_y'] : '' ?>">
                                        <small class="form-text text-muted">For position/adjacent_to_position validation</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="movement_count">Nombre de déplacements</label>
                                <input type="number" class="form-control" id="movement_count" name="movement_count"
                                       value="<?= $hasFormData && $stepValidation && $stepValidation['movement_count'] !== null ? $stepValidation['movement_count'] : '' ?>">
                                <small class="form-text text-muted">Pour la validation « Nombre de déplacements »</small>
                            </div>

                            <div class="form-group">
                                <label for="action_name">Nom de l'action</label>
                                <input type="text" class="form-control" id="action_name" name="action_name"
                                       value="<?= $hasFormData && $stepValidation ? htmlspecialchars($stepValidation['action_name'] ?? '') : '' ?>">
                                <small class="form-text text-muted">Pour la validation « Action utilisée » (ex. « fouiller », « attaquer »)</small>
                            </div>

                            <div class="form-group">
                                <label for="action_charges_required">Nombre d'utilisations</label>
                                <input type="number" class="form-control" id="action_charges_required" name="action_charges_required"
                                       value="<?= $hasFormData && $stepValidation ? $stepValidation['action_charges_required'] : 1 ?>">
                                <small class="form-text text-muted">Nombre de fois où l'action doit être utilisée (1 par défaut)</small>
                            </div>

                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="combat_required" name="combat_required" value="1"
                                       <?= $hasFormData && $stepValidation && $stepValidation['combat_required'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="combat_required">
                                    Combat requis
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="panel_id">Panneau</label>
                                <input type="text" class="form-control" id="panel_id" name="panel_id"
                                       value="<?= $hasFormData && $stepValidation ? htmlspecialchars($stepValidation['panel_id'] ?? '') : '' ?>">
                                <small class="form-text text-muted">Pour la validation « Panneau ouvert » (ex. « actions », « characteristics »)</small>
                            </div>

                            <div class="form-group">
                                <label for="element_selector">Élément masqué</label>
                                <input type="text" class="form-control font-monospace" id="element_selector" name="element_selector"
                                       value="<?= $hasFormData && $stepValidation ? htmlspecialchars($stepValidation['element_selector'] ?? '') : '' ?>">
                                <small class="form-text text-muted">Pour la validation « Élément masqué » : l'élément qui doit disparaître, en sélecteur CSS</small>
                            </div>

                            <div class="form-group">
                                <label for="element_clicked">Élément cliqué</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace" id="element_clicked" name="element_clicked"
                                           value="<?= $hasFormData && $stepValidation ? htmlspecialchars($stepValidation['element_clicked'] ?? '') : '' ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="btnManualAdvance" title="Régler en étape à passage manuel">
                                            <i class="fas fa-hand-pointer"></i> Passage manuel
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    Pour la validation « Clic sur un élément » (sélecteur CSS).
                                    <strong>Astuce :</strong> <code>tutorial_next</code> crée une étape d'information validée par le bouton « Suivant ».
                                </small>
                            </div>

                            <div class="alert alert-info mt-3">
                                <strong><i class="fas fa-info-circle"></i> Étape à passage manuel :</strong>
                                <p class="mb-1">Pour une étape d'information que le joueur valide lui-même :</p>
                                <ol class="mb-0 pl-3">
                                    <li><strong>Type de validation</strong> : Clic sur un élément <code class="tech-name">ui_interaction</code></li>
                                    <li><strong>Élément cliqué</strong> : <code>tutorial_next</code></li>
                                    <li><strong>Type</strong> (onglet Général) : Clic dans l'interface <code class="tech-name">ui_interaction</code></li>
                                </ol>
                                <p class="mb-0 mt-2"><small>Le bouton « Suivant » s'affiche et fait passer à l'étape suivante.</small></p>
                            </div>

                            <div class="form-group">
                                <label for="dialog_id">Dialogue</label>
                                <input type="text" class="form-control" id="dialog_id" name="dialog_id"
                                       value="<?= $hasFormData && $stepValidation ? htmlspecialchars($stepValidation['dialog_id'] ?? '') : '' ?>">
                                <small class="form-text text-muted">Dialogue que le joueur doit terminer <code class="tech-name">tutorial_dialogs.dialog_id</code></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prerequisites Tab -->
            <div class="tab-pane fade" id="prerequisites" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Prérequis</h3>
                        <p class="text-muted">Ressources nécessaires au début de l'étape</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mvt_required">
                                        Mouvements requis
                                        <i class="fas fa-info-circle text-info" title="-1 : le maximum de la race"></i>
                                    </label>
                                    <input type="number" class="form-control" id="mvt_required" name="mvt_required"
                                           value="<?= $hasFormData && $stepPrerequisites && $stepPrerequisites['mvt_required'] !== null ? $stepPrerequisites['mvt_required'] : '' ?>">
                                    <small class="form-text text-muted">
                                        Nombre fixe (ex. 1, 2, 3) ou <strong>-1</strong> pour le maximum de la race
                                        <span class="text-info">(Nain: 4, Elfe: 5, HS: 6)</span>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pa_required">Points d'action requis</label>
                                    <input type="number" class="form-control" id="pa_required" name="pa_required"
                                           value="<?= $hasFormData && $stepPrerequisites && $stepPrerequisites['pa_required'] !== null ? $stepPrerequisites['pa_required'] : '' ?>">
                                    <small class="form-text text-muted">Nombre de points d'action (en général 1 ou 2)</small>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mb-3">
                            <strong><i class="fas fa-magic"></i> Mouvements selon la race :</strong>
                            <p class="mb-1"><code>-1</code> en mouvements requis donne le maximum de la race du joueur :</p>
                            <ul class="mb-0 pl-3">
                                <li><strong>Nain :</strong> 4 mouvements</li>
                                <li><strong>Elfe / Olympien / Géant :</strong> 5 mouvements</li>
                                <li><strong>HS (Homme-Sauvage) :</strong> 6 mouvements</li>
                            </ul>
                            <p class="mb-0 mt-2"><small>Utile pour une étape où le joueur épuise ses mouvements.</small></p>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="auto_restore" name="auto_restore" value="1"
                                   <?= $hasFormData && $stepPrerequisites && $stepPrerequisites['auto_restore'] ? 'checked' : 'checked' ?>>
                            <label class="form-check-label" for="auto_restore">
                                Rendre les ressources au début de l'étape
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="consume_movements" name="consume_movements" value="1"
                                   <?= $hasFormData && $stepPrerequisites && $stepPrerequisites['consume_movements'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="consume_movements">
                                Les déplacements consomment des mouvements
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="unlimited_mvt" name="unlimited_mvt" value="1"
                                   <?= $hasFormData && $stepPrerequisites && $stepPrerequisites['unlimited_mvt'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="unlimited_mvt">
                                Mouvements illimités
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="unlimited_pa" name="unlimited_pa" value="1"
                                   <?= $hasFormData && $stepPrerequisites && $stepPrerequisites['unlimited_pa'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="unlimited_pa">
                                Points d'action illimités
                            </label>
                        </div>

                        <hr>
                        <h5>Mise en place</h5>

                        <div class="form-group">
                            <label for="spawn_enemy">Ennemi à faire apparaître</label>
                            <input type="text" class="form-control" id="spawn_enemy" name="spawn_enemy"
                                   value="<?= $hasFormData && $stepPrerequisites ? htmlspecialchars($stepPrerequisites['spawn_enemy'] ?? '') : '' ?>">
                            <small class="form-text text-muted">Type d'ennemi (ex. « tutorial_dummy »)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ensure_harvestable_tree_x">Arbre récoltable X</label>
                                    <input type="number" class="form-control" id="ensure_harvestable_tree_x" name="ensure_harvestable_tree_x"
                                           value="<?= $hasFormData && $stepPrerequisites && $stepPrerequisites['ensure_harvestable_tree_x'] !== null ? $stepPrerequisites['ensure_harvestable_tree_x'] : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ensure_harvestable_tree_y">Arbre récoltable Y</label>
                                    <input type="number" class="form-control" id="ensure_harvestable_tree_y" name="ensure_harvestable_tree_y"
                                           value="<?= $hasFormData && $stepPrerequisites && $stepPrerequisites['ensure_harvestable_tree_y'] !== null ? $stepPrerequisites['ensure_harvestable_tree_y'] : '' ?>">
                                </div>
                            </div>
                        </div>
                        <small class="form-text text-muted">Place un arbre récoltable à ces coordonnées, pour une étape de récolte</small>
                    </div>
                </div>
            </div>

            <!-- Interactions Tab -->
            <div class="tab-pane fade" id="interactions" role="tabpanel">
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title">Éléments cliquables</h3>
                        <p class="text-muted">En mode semi-bloquant : les éléments sur lesquels le joueur peut cliquer</p>

                        <div id="interactionsList">
                            <?php foreach ($interactions as $index => $interaction): ?>
                                <div class="input-group mb-2 interaction-row">
                                    <input type="text" class="form-control font-monospace" name="interactions[]"
                                           value="<?= htmlspecialchars($interaction['selector']) ?>"
                                           placeholder=".selecteur ou #id">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-danger remove-row">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-success" id="addInteraction">
                            <i class="fas fa-plus"></i> Ajouter un élément
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Autres mises en évidence</h3>
                        <p class="text-muted">Éléments mis en évidence en plus de la cible</p>

                        <div id="highlightsList">
                            <?php foreach ($highlights as $index => $highlight): ?>
                                <div class="input-group mb-2 highlight-row">
                                    <input type="text" class="form-control font-monospace" name="highlights[]"
                                           value="<?= htmlspecialchars($highlight['selector']) ?>"
                                           placeholder=".selecteur ou #id">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-danger remove-row">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-success" id="addHighlight">
                            <i class="fas fa-plus"></i> Ajouter une mise en évidence
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Tab -->
            <div class="tab-pane fade" id="advanced" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Avancé</h3>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="celebration" name="celebration" value="1"
                                   <?= $hasFormData && $stepFeatures && $stepFeatures['celebration'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="celebration">
                                Animation de réussite
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="show_rewards" name="show_rewards" value="1"
                                   <?= $hasFormData && $stepFeatures && $stepFeatures['show_rewards'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_rewards">
                                Récapitulatif des récompenses
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="redirect_delay">Retour au jeu après (ms)</label>
                            <input type="number" class="form-control" id="redirect_delay" name="redirect_delay"
                                   value="<?= $hasFormData && $stepFeatures && $stepFeatures['redirect_delay'] !== null ? $stepFeatures['redirect_delay'] : '' ?>">
                            <small class="form-text text-muted">Renvoie vers le jeu après ce délai (dernière étape)</small>
                        </div>

                        <hr>

                        <h5>Changements de contexte</h5>
                        <p class="text-muted">Réglages modifiés pendant l'étape</p>

                        <?= renderDatalist('tutorialContextKeys', TutorialContextKeys::CONTEXT_CHANGES) ?>

                        <div id="contextChangesList">
                            <?php foreach ($contextChanges as $index => $change): ?>
                                <div class="row mb-2 context-row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="context_keys[]"
                                               list="tutorialContextKeys"
                                               value="<?= htmlspecialchars($change['context_key']) ?>"
                                               placeholder="Clé (ex. set_mvt_limit)">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="context_values[]"
                                               value="<?= htmlspecialchars($change['context_value']) ?>"
                                               placeholder="Valeur">
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
                            <i class="fas fa-plus"></i> Ajouter un changement
                        </button>

                        <hr>

                        <h5>Préparation de l'étape suivante</h5>
                        <p class="text-muted">Actions exécutées à la fin de l'étape, pour préparer la suivante</p>

                        <div class="alert alert-info mb-3">
                            <strong><i class="fas fa-lightbulb"></i> Astuce :</strong>
                            pour <code>restore_mvt</code>, <strong>-1</strong> rend le maximum de mouvements de la race.
                            <small class="d-block mt-1">Exemple : <code>restore_mvt: -1</code> donne 4 mouvements au Nain, 5 à l'Elfe, 6 au HS.</small>
                        </div>

                        <?= renderDatalist('tutorialPrepKeys', TutorialContextKeys::NEXT_PREPARATIONS) ?>

                        <div id="nextPrepList">
                            <?php foreach ($nextPreparation as $index => $prep): ?>
                                <div class="row mb-2 prep-row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="prep_keys[]"
                                               list="tutorialPrepKeys"
                                               value="<?= htmlspecialchars($prep['preparation_key']) ?>"
                                               placeholder="Clé (ex. restore_mvt)">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" name="prep_values[]"
                                               value="<?= htmlspecialchars($prep['preparation_value']) ?>"
                                               placeholder="Valeur">
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
                            <i class="fas fa-plus"></i> Ajouter une préparation
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card mt-4">
            <div class="card-body">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> <?= $isEdit ? 'Enregistrer' : 'Créer l\'étape' ?>
                </button>
                <a href="tutorial.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Annuler
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
        document.querySelectorAll('#stepTabs .nav-link').forEach(link => {
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
    alert('Passage manuel réglé :\n\n' +
          '- Type : Clic dans l\'interface\n' +
          '- Validation requise : oui\n' +
          '- Type de validation : Clic sur un élément\n' +
          '- Élément cliqué : tutorial_next\n\n' +
          'Le bouton « Suivant » s\'affiche et fait passer à l\'étape suivante.');

    // Briefly highlight the step_type field to show it was changed
    if (stepTypeField) {
        stepTypeField.style.backgroundColor = '#d4edda';
        stepTypeField.style.transition = 'background-color 0.3s';
        setTimeout(() => {
            stepTypeField.style.backgroundColor = '';
        }, 1500);
    }
});

/* Add a row to one of the four sub-tables (interactions, highlights,
 * context changes, next-step preparation). Single helper + one config per
 * button so a schema tweak (new field, new datalist, new layout) happens in
 * one place instead of four. */
function appendSubRow(cfg) {
    const list = document.getElementById(cfg.listId);
    if (!list) return;
    const row = document.createElement('div');
    row.className = cfg.rowClass;

    const inputHtml = f =>
        `<input type="text" class="form-control${f.monospace ? ' font-monospace' : ''}"`
        + ` name="${f.name}"${f.list ? ` list="${f.list}"` : ''}`
        + ` placeholder="${f.placeholder}">`;

    if (cfg.layout === 'row-cols') {
        row.innerHTML =
            cfg.fields.map(f => `<div class="col-md-5">${inputHtml(f)}</div>`).join('') +
            `<div class="col-md-2">
                <button type="button" class="btn btn-danger remove-row w-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>`;
    } else {
        /* input-group layout: single input + append-slot delete button */
        row.innerHTML =
            inputHtml(cfg.fields[0]) +
            `<div class="input-group-append">
                <button type="button" class="btn btn-danger remove-row">
                    <i class="fas fa-times"></i>
                </button>
            </div>`;
    }

    list.appendChild(row);
}

const SUB_ROW_CONFIGS = {
    addInteraction: {
        listId: 'interactionsList',
        rowClass: 'input-group mb-2 interaction-row',
        layout: 'input-group',
        fields: [{ name: 'interactions[]', placeholder: '.selecteur ou #id', monospace: true }],
    },
    addHighlight: {
        listId: 'highlightsList',
        rowClass: 'input-group mb-2 highlight-row',
        layout: 'input-group',
        fields: [{ name: 'highlights[]', placeholder: '.selecteur ou #id', monospace: true }],
    },
    addContextChange: {
        listId: 'contextChangesList',
        rowClass: 'row mb-2 context-row',
        layout: 'row-cols',
        fields: [
            { name: 'context_keys[]', list: 'tutorialContextKeys', placeholder: 'Clé (ex. set_mvt_limit)' },
            { name: 'context_values[]', placeholder: 'Valeur' },
        ],
    },
    addNextPrep: {
        listId: 'nextPrepList',
        rowClass: 'row mb-2 prep-row',
        layout: 'row-cols',
        fields: [
            { name: 'prep_keys[]', list: 'tutorialPrepKeys', placeholder: 'Clé (ex. restore_mvt)' },
            { name: 'prep_values[]', placeholder: 'Valeur' },
        ],
    },
};

Object.entries(SUB_ROW_CONFIGS).forEach(([btnId, cfg]) => {
    const btn = document.getElementById(btnId);
    if (btn) btn.addEventListener('click', () => appendSubRow(cfg));
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

/* =========================================
 * ENHANCED FEATURES - Phase 1 & 2
 * ========================================= */

/* Template System — bootstrapped from App\Tutorial\TutorialTemplates so the
 * editor dropdown and the handler below are driven by the same PHP array. */
const TUTORIAL_TEMPLATES = <?= json_encode(TutorialTemplates::ALL, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

document.getElementById('template_selector').addEventListener('change', function() {
    const templateId = this.value;
    if (!templateId) return;

    const template = TUTORIAL_TEMPLATES[templateId];
    if (!template) return;

    // Confirm with user
    if (!confirm(`Appliquer le modèle « ${template.name} » ?\n\n${template.description}\n\nLes réglages actuels seront remplacés.`)) {
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
    showToast('success', `Modèle « ${template.name} » appliqué : complétez les autres champs.`);
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
    const title = document.getElementById('title').value || 'Étape sans titre';
    const text = document.getElementById('text').value || 'Aucun texte';
    const position = document.getElementById('tooltip_position').value || 'center';

    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.className = 'preview-modal-overlay';
    overlay.innerHTML = `
        <div class="preview-modal">
            <div class="preview-modal-header">
                <h5>Aperçu de l'étape</h5>
                <button type="button" class="preview-modal-close">&times;</button>
            </div>
            <div class="preview-modal-body">
                <div class="preview-container">
                    <div class="tutorial-tooltip">
                        <div class="tooltip-content">
                            <h3 class="tooltip-title"></h3>
                            <div class="tooltip-text"></div>
                            <button class="btn-tutorial-primary">Suivant &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    /* As in game (TutorialTooltip.js): the title is text, the step text is HTML. */
    overlay.querySelector('.tutorial-tooltip').classList.add(position);
    overlay.querySelector('.tooltip-title').textContent = title;
    overlay.querySelector('.tooltip-text').innerHTML = text;
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
        if (confirm('Dupliquer cette étape ?\n\nLa copie reprend tous les réglages avec un nouveau numéro.')) {
            document.getElementById('step_id').value = '';
            const currentNumber = parseFloat(document.getElementById('step_number').value) || 0;
            document.getElementById('step_number').value = currentNumber + 0.1;

            window.scrollTo({ top: 0, behavior: 'smooth' });
            showToast('info', 'Étape dupliquée : vérifiez le numéro puis enregistrez.');
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

    showToast('success', 'Étape exportée en JSON.');
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

            if (!confirm('Importer cette étape ?\n\nLes valeurs du formulaire seront remplacées.')) {
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

            showToast('success', 'Étape importée.');
        } catch (err) {
            showToast('danger', 'Échec de l\'import : ' + err.message);
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
        showToast('warning', 'Enregistrez l\'étape avant de la tester.');
        return;
    }

    // Open game in new tab with tutorial test mode
    const testUrl = `/?tutorial_test=1&step=${encodeURIComponent(stepId || dbStepId)}`;

    const confirmMsg = `Ouvrir le jeu pour tester cette étape ?\n\n` +
        `Identifiant : ${stepId || '—'}\n\n` +
        `Il faut être connecté et avoir une session de tutoriel en cours.`;

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


<?php
$content = ob_get_clean();
echo admin_layout($isEdit ? 'Étape : ' . e($step['title']) : 'Nouvelle étape', $content, [
    'back' => ['/admin/tutorial.php', 'Étapes'],
    'styles' => ['/admin/css/tutorial-admin.css'],
]);
?>
