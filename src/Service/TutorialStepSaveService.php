<?php declare(strict_types=1);

namespace App\Service;

use Classes\Db;
use Exception;

/**
 * Tutorial Step Save Service
 *
 * Handles saving tutorial step data to normalized database schema.
 * Breaks down the complex save operation into focused, testable methods.
 */
class TutorialStepSaveService
{
    private Db $db;
    private TutorialStepValidationService $validator;

    public function __construct(Db $db, TutorialStepValidationService $validator)
    {
        $this->db = $db;
        $this->validator = $validator;
    }

    /**
     * Save complete tutorial step (create or update)
     *
     * @param array $data Form data from $_POST
     * @param int|null $stepId Database step ID for edits (null for new)
     * @return int Database step ID
     * @throws Exception If save fails
     */
    public function saveStep(array $data, ?int $stepId = null): int
    {
        $this->db->beginTransaction();

        try {
            $stepId = $this->saveBasicStepData($data, $stepId);
            $this->saveUIConfig($stepId, $data);
            $this->saveValidationConfig($stepId, $data);
            $this->savePrerequisites($stepId, $data);
            $this->saveInteractions($stepId, $data);
            $this->saveHighlights($stepId, $data);
            $this->saveContextChanges($stepId, $data);
            $this->saveNextPreparation($stepId, $data);
            $this->saveFeatures($stepId, $data);

            $this->db->commit();

            return $stepId;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Save basic step information
     *
     * @param array $data Form data
     * @param int|null $stepId Existing step ID or null for new
     * @return int Step ID
     */
    private function saveBasicStepData(array $data, ?int $stepId): int
    {
        $version = $this->validator->validateVersion($data['version'] ?? '1.0.0');
        $stepNumber = $this->validator->validateStepNumber($data['step_number']);
        $stepType = $this->validator->validateStepType($data['step_type']);
        $stepIdString = $this->validator->validateStepId($data['step_id'] ?? null);
        $nextStep = $this->validator->validateStepId($data['next_step'] ?? null);
        $title = $this->validator->validateString($data['title'] ?? '', 255);
        $text = $this->validator->validateText($data['text'] ?? '', 65535);
        $xpReward = $this->validator->validateXpReward($data['xp_reward'] ?? 0);
        $isActive = $this->validator->validateCheckbox($data['is_active'] ?? false);

        if ($stepId !== null) {
            // Update existing step
            $this->db->exe("
                UPDATE tutorial_steps SET
                    version = ?, step_id = ?, next_step = ?, step_number = ?, step_type = ?,
                    title = ?, text = ?, xp_reward = ?, is_active = ?
                WHERE id = ?
            ", [
                $version, $stepIdString, $nextStep, $stepNumber, $stepType,
                $title, $text, $xpReward, $isActive ? 1 : 0, $stepId
            ]);
        } else {
            // Insert new step
            $this->db->exe("
                INSERT INTO tutorial_steps (version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $version, $stepIdString, $nextStep, $stepNumber, $stepType,
                $title, $text, $xpReward, $isActive ? 1 : 0
            ]);

            // Get the new step ID
            $result = $this->db->exe("SELECT LAST_INSERT_ID() as id");
            $row = $result->fetch_assoc();
            $stepId = (int)$row['id'];
        }

        return $stepId;
    }

    /**
     * Save UI configuration
     */
    private function saveUIConfig(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_ui WHERE step_id = ?", [$stepId]);

        $targetSelector = $this->validator->validateCssSelector($data['target_selector'] ?? null);
        $targetDescription = $this->validator->validateString($data['target_description'] ?? null, 255);
        $highlightSelector = $this->validator->validateCssSelector($data['highlight_selector'] ?? null);
        $tooltipPosition = $this->validator->validateTooltipPosition($data['tooltip_position'] ?? 'bottom');
        $interactionMode = $this->validator->validateInteractionMode($data['interaction_mode'] ?? 'blocking');
        $blockedClickMessage = $this->validator->validateText($data['blocked_click_message'] ?? null);
        $showDelay = $this->validator->validatePositiveInt($data['show_delay'] ?? 0, 10000) ?? 0;
        $autoAdvanceDelay = $this->validator->validatePositiveInt($data['auto_advance_delay'] ?? null, 60000);
        $allowManualAdvance = $this->validator->validateCheckbox($data['allow_manual_advance'] ?? false);
        $autoCloseCard = isset($data['auto_close_card']) ? 1 : null;

        $this->db->exe("
            INSERT INTO tutorial_step_ui (step_id, target_selector, target_description, highlight_selector,
                tooltip_position, interaction_mode, blocked_click_message, show_delay, auto_advance_delay,
                allow_manual_advance, auto_close_card, tooltip_offset_x, tooltip_offset_y)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $stepId, $targetSelector, $targetDescription, $highlightSelector,
            $tooltipPosition, $interactionMode, $blockedClickMessage, $showDelay, $autoAdvanceDelay,
            $allowManualAdvance ? 1 : 0, $autoCloseCard, 0, 0
        ]);
    }

    /**
     * Save validation configuration
     */
    private function saveValidationConfig(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_validation WHERE step_id = ?", [$stepId]);

        $requiresValidation = $this->validator->validateCheckbox($data['requires_validation'] ?? false);
        $validationType = $this->validator->validateValidationType($data['validation_type'] ?? null);
        $validationHint = $this->validator->validateText($data['validation_hint'] ?? null);
        $targetX = $this->validator->validateCoordinate($data['target_x'] ?? null);
        $targetY = $this->validator->validateCoordinate($data['target_y'] ?? null);
        $movementCount = $this->validator->validatePositiveInt($data['movement_count'] ?? null);
        $panelId = $this->validator->validateString($data['panel_id'] ?? null, 50);
        $elementSelector = $this->validator->validateCssSelector($data['element_selector'] ?? null);
        $elementClicked = $this->validator->validateCssSelector($data['element_clicked'] ?? null);
        $actionName = $this->validator->validateString($data['action_name'] ?? null, 50);
        $actionChargesRequired = $this->validator->validatePositiveInt($data['action_charges_required'] ?? null) ?? 1;
        $combatRequired = $this->validator->validateCheckbox($data['combat_required'] ?? false);
        $dialogId = $this->validator->validateString($data['dialog_id'] ?? null, 50);

        $this->db->exe("
            INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint,
                target_x, target_y, movement_count, panel_id, element_selector, element_clicked,
                action_name, action_charges_required, combat_required, dialog_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $stepId, $requiresValidation ? 1 : 0, $validationType, $validationHint,
            $targetX, $targetY, $movementCount, $panelId, $elementSelector, $elementClicked,
            $actionName, $actionChargesRequired, $combatRequired ? 1 : 0, $dialogId
        ]);
    }

    /**
     * Save prerequisites
     */
    private function savePrerequisites(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_prerequisites WHERE step_id = ?", [$stepId]);

        $mvtRequired = $this->validator->validatePositiveInt($data['mvt_required'] ?? null);
        $paRequired = $this->validator->validatePositiveInt($data['pa_required'] ?? null);
        $autoRestore = $this->validator->validateCheckbox($data['auto_restore'] ?? false);
        $consumeMovements = $this->validator->validateCheckbox($data['consume_movements'] ?? false);
        $unlimitedMvt = $this->validator->validateCheckbox($data['unlimited_mvt'] ?? false);
        $unlimitedPa = $this->validator->validateCheckbox($data['unlimited_pa'] ?? false);
        $spawnEnemy = $this->validator->validateString($data['spawn_enemy'] ?? null, 50);
        $ensureTreeX = $this->validator->validateCoordinate($data['ensure_harvestable_tree_x'] ?? null);
        $ensureTreeY = $this->validator->validateCoordinate($data['ensure_harvestable_tree_y'] ?? null);

        $hasPrereq = $mvtRequired !== null || $paRequired !== null ||
                     $consumeMovements || $unlimitedMvt || $unlimitedPa ||
                     $spawnEnemy !== null || $ensureTreeX !== null;

        if ($hasPrereq) {
            $this->db->exe("
                INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore,
                    consume_movements, unlimited_mvt, unlimited_pa, spawn_enemy,
                    ensure_harvestable_tree_x, ensure_harvestable_tree_y)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $stepId, $mvtRequired, $paRequired, $autoRestore ? 1 : 0,
                $consumeMovements ? 1 : 0, $unlimitedMvt ? 1 : 0, $unlimitedPa ? 1 : 0, $spawnEnemy,
                $ensureTreeX, $ensureTreeY
            ]);
        }
    }

    /**
     * Save allowed interactions
     */
    private function saveInteractions(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_interactions WHERE step_id = ?", [$stepId]);

        if (!empty($data['interactions']) && is_array($data['interactions'])) {
            foreach ($data['interactions'] as $selector) {
                $cleanSelector = $this->validator->validateCssSelector($selector);
                if ($cleanSelector !== null) {
                    $this->db->exe(
                        "INSERT INTO tutorial_step_interactions (step_id, selector) VALUES (?, ?)",
                        [$stepId, $cleanSelector]
                    );
                }
            }
        }
    }

    /**
     * Save additional highlights
     */
    private function saveHighlights(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_highlights WHERE step_id = ?", [$stepId]);

        if (!empty($data['highlights']) && is_array($data['highlights'])) {
            foreach ($data['highlights'] as $selector) {
                $cleanSelector = $this->validator->validateCssSelector($selector);
                if ($cleanSelector !== null) {
                    $this->db->exe(
                        "INSERT INTO tutorial_step_highlights (step_id, selector) VALUES (?, ?)",
                        [$stepId, $cleanSelector]
                    );
                }
            }
        }
    }

    /**
     * Save context changes
     */
    private function saveContextChanges(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_context_changes WHERE step_id = ?", [$stepId]);

        // Auto-add checkbox values to context changes
        $autoContextChanges = [];

        // Add unlimited_mvt if checked
        if (!empty($data['unlimited_mvt'])) {
            $autoContextChanges['unlimited_mvt'] = 'true';
        }

        // Add unlimited_pa if checked
        if (!empty($data['unlimited_pa'])) {
            $autoContextChanges['unlimited_actions'] = 'true';
        }

        // Add consume_movements if checked (or false if unlimited_mvt is checked)
        if (!empty($data['consume_movements'])) {
            $autoContextChanges['consume_movements'] = 'true';
        } elseif (!empty($data['unlimited_mvt'])) {
            // If unlimited movements, explicitly disable consumption
            $autoContextChanges['consume_movements'] = 'false';
        }

        // Save auto-added context changes first
        foreach ($autoContextChanges as $key => $value) {
            $this->db->exe(
                "INSERT INTO tutorial_step_context_changes (step_id, context_key, context_value) VALUES (?, ?, ?)",
                [$stepId, $key, $value]
            );
        }

        // Then save manual context changes
        if (!empty($data['context_keys']) && is_array($data['context_keys'])) {
            $keys = $data['context_keys'];
            $values = $data['context_values'] ?? [];

            for ($i = 0; $i < count($keys); $i++) {
                $key = $this->validator->validateString($keys[$i] ?? '', 50);
                $value = $this->validator->validateText($values[$i] ?? '', 255);

                if ($key !== null && !isset($autoContextChanges[$key])) {
                    // Don't duplicate auto-added keys
                    $this->db->exe(
                        "INSERT INTO tutorial_step_context_changes (step_id, context_key, context_value) VALUES (?, ?, ?)",
                        [$stepId, $key, $value ?? '']
                    );
                }
            }
        }
    }

    /**
     * Save next step preparation
     */
    private function saveNextPreparation(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_next_preparation WHERE step_id = ?", [$stepId]);

        if (!empty($data['prep_keys']) && is_array($data['prep_keys'])) {
            $keys = $data['prep_keys'];
            $values = $data['prep_values'] ?? [];

            for ($i = 0; $i < count($keys); $i++) {
                $key = $this->validator->validateString($keys[$i] ?? '', 50);
                $value = $this->validator->validateText($values[$i] ?? '', 255);

                if ($key !== null) {
                    $this->db->exe(
                        "INSERT INTO tutorial_step_next_preparation (step_id, preparation_key, preparation_value) VALUES (?, ?, ?)",
                        [$stepId, $key, $value ?? '']
                    );
                }
            }
        }
    }

    /**
     * Save features
     */
    private function saveFeatures(int $stepId, array $data): void
    {
        $this->db->exe("DELETE FROM tutorial_step_features WHERE step_id = ?", [$stepId]);

        $celebration = $this->validator->validateCheckbox($data['celebration'] ?? false);
        $showRewards = $this->validator->validateCheckbox($data['show_rewards'] ?? false);
        $redirectDelay = $this->validator->validatePositiveInt($data['redirect_delay'] ?? null, 60000);

        $hasFeatures = $celebration || $showRewards || $redirectDelay !== null;

        if ($hasFeatures) {
            $this->db->exe("
                INSERT INTO tutorial_step_features (step_id, celebration, show_rewards, redirect_delay)
                VALUES (?, ?, ?, ?)
            ", [
                $stepId, $celebration ? 1 : 0, $showRewards ? 1 : 0, $redirectDelay
            ]);
        }
    }
}
