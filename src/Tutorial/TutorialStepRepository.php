<?php

namespace App\Tutorial;

use Classes\Db;

/**
 * Tutorial Step Repository
 *
 * Encapsulates database queries for tutorial steps using normalized schema.
 * Provides clean API for TutorialManager to fetch step data.
 */
class TutorialStepRepository
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /**
     * Get step data by step_id with all related configuration
     *
     * @param string $stepId Step identifier (e.g., "first_movement")
     * @param string $version Tutorial version
     * @return array|null Step data with config array matching old format
     */
    public function getStepById(string $stepId, string $version = '1.0.0'): ?array
    {
        $sql = 'SELECT
                    ts.id,
                    ts.version,
                    ts.step_id,
                    ts.next_step,
                    ts.step_number,
                    ts.step_type,
                    ts.title,
                    ts.text,
                    ts.xp_reward,
                    -- UI config
                    ui.target_selector,
                    ui.target_description,
                    ui.highlight_selector,
                    ui.tooltip_position,
                    ui.interaction_mode,
                    ui.blocked_click_message,
                    ui.show_delay,
                    ui.auto_advance_delay,
                    ui.allow_manual_advance,
                    ui.auto_close_card,
                    ui.tooltip_offset_x,
                    ui.tooltip_offset_y,
                    -- Validation config
                    v.requires_validation,
                    v.validation_type,
                    v.validation_hint,
                    v.target_x,
                    v.target_y,
                    v.movement_count,
                    v.panel_id,
                    v.element_selector,
                    v.element_clicked,
                    v.action_name,
                    v.action_charges_required,
                    v.combat_required,
                    v.dialog_id,
                    -- Prerequisites
                    p.mvt_required,
                    p.pa_required,
                    p.auto_restore,
                    p.consume_movements,
                    p.unlimited_mvt,
                    p.unlimited_pa,
                    p.spawn_enemy,
                    p.ensure_harvestable_tree_x,
                    p.ensure_harvestable_tree_y,
                    -- Features
                    f.celebration,
                    f.show_rewards,
                    f.redirect_delay,
                    -- 1:N relationships using JSON aggregation (eliminates N+1 queries)
                    (SELECT JSON_ARRAYAGG(selector)
                     FROM tutorial_step_interactions
                     WHERE step_id = ts.id) as interactions_json,
                    (SELECT JSON_ARRAYAGG(selector)
                     FROM tutorial_step_highlights
                     WHERE step_id = ts.id) as highlights_json,
                    (SELECT JSON_OBJECTAGG(context_key, context_value)
                     FROM tutorial_step_context_changes
                     WHERE step_id = ts.id) as context_changes_json,
                    (SELECT JSON_OBJECTAGG(preparation_key, preparation_value)
                     FROM tutorial_step_next_preparation
                     WHERE step_id = ts.id) as next_preparation_json
                FROM tutorial_steps ts
                LEFT JOIN tutorial_step_ui ui ON ts.id = ui.step_id
                LEFT JOIN tutorial_step_validation v ON ts.id = v.step_id
                LEFT JOIN tutorial_step_prerequisites p ON ts.id = p.step_id
                LEFT JOIN tutorial_step_features f ON ts.id = f.step_id
                WHERE ts.version = ? AND ts.step_id = ? AND ts.is_active = 1';

        $result = $this->db->exe($sql, [$version, $stepId]);

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $row = $result->fetch_assoc();

        // Convert database row to format expected by AbstractStep constructor
        return $this->convertRowToStepData($row);
    }

    /**
     * Get step data by step_number
     *
     * @param float $stepNumber Step number (can be decimal like 0.5)
     * @param string $version Tutorial version
     * @return array|null
     */
    public function getStepByNumber(float $stepNumber, string $version = '1.0.0'): ?array
    {
        $sql = 'SELECT
                    ts.id,
                    ts.version,
                    ts.step_id,
                    ts.next_step,
                    ts.step_number,
                    ts.step_type,
                    ts.title,
                    ts.text,
                    ts.xp_reward,
                    -- UI config
                    ui.target_selector,
                    ui.target_description,
                    ui.highlight_selector,
                    ui.tooltip_position,
                    ui.interaction_mode,
                    ui.blocked_click_message,
                    ui.show_delay,
                    ui.auto_advance_delay,
                    ui.allow_manual_advance,
                    ui.auto_close_card,
                    ui.tooltip_offset_x,
                    ui.tooltip_offset_y,
                    -- Validation config
                    v.requires_validation,
                    v.validation_type,
                    v.validation_hint,
                    v.target_x,
                    v.target_y,
                    v.movement_count,
                    v.panel_id,
                    v.element_selector,
                    v.element_clicked,
                    v.action_name,
                    v.action_charges_required,
                    v.combat_required,
                    v.dialog_id,
                    -- Prerequisites
                    p.mvt_required,
                    p.pa_required,
                    p.auto_restore,
                    p.consume_movements,
                    p.unlimited_mvt,
                    p.unlimited_pa,
                    p.spawn_enemy,
                    p.ensure_harvestable_tree_x,
                    p.ensure_harvestable_tree_y,
                    -- Features
                    f.celebration,
                    f.show_rewards,
                    f.redirect_delay,
                    -- 1:N relationships using JSON aggregation (eliminates N+1 queries)
                    (SELECT JSON_ARRAYAGG(selector)
                     FROM tutorial_step_interactions
                     WHERE step_id = ts.id) as interactions_json,
                    (SELECT JSON_ARRAYAGG(selector)
                     FROM tutorial_step_highlights
                     WHERE step_id = ts.id) as highlights_json,
                    (SELECT JSON_OBJECTAGG(context_key, context_value)
                     FROM tutorial_step_context_changes
                     WHERE step_id = ts.id) as context_changes_json,
                    (SELECT JSON_OBJECTAGG(preparation_key, preparation_value)
                     FROM tutorial_step_next_preparation
                     WHERE step_id = ts.id) as next_preparation_json
                FROM tutorial_steps ts
                LEFT JOIN tutorial_step_ui ui ON ts.id = ui.step_id
                LEFT JOIN tutorial_step_validation v ON ts.id = v.step_id
                LEFT JOIN tutorial_step_prerequisites p ON ts.id = p.step_id
                LEFT JOIN tutorial_step_features f ON ts.id = f.step_id
                WHERE ts.version = ? AND ts.step_number = ? AND ts.is_active = 1';

        $result = $this->db->exe($sql, [$version, $stepNumber]);

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $row = $result->fetch_assoc();
        return $this->convertRowToStepData($row);
    }

    /**
     * Get first step ID for a version (lowest step_number)
     *
     * @param string $version
     * @return string|null
     */
    public function getFirstStepId(string $version = '1.0.0'): ?string
    {
        $sql = 'SELECT step_id FROM tutorial_steps
                WHERE version = ? AND is_active = 1
                ORDER BY step_number ASC LIMIT 1';

        $result = $this->db->exe($sql, [$version]);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['step_id'];
        }

        return null;
    }

    /**
     * Get total active steps count for a version
     *
     * @param string $version
     * @return int
     */
    public function getTotalSteps(string $version = '1.0.0'): int
    {
        $sql = 'SELECT COUNT(*) as total FROM tutorial_steps
                WHERE version = ? AND is_active = 1';

        $result = $this->db->exe($sql, [$version]);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['total'];
        }

        return 0;
    }

    /**
     * Calculate step position in sequence (1-indexed)
     *
     * @param string $stepId
     * @param string $version
     * @return int
     */
    public function calculateStepPosition(string $stepId, string $version = '1.0.0'): int
    {
        $sql = 'SELECT COUNT(*) as position
                FROM tutorial_steps
                WHERE version = ?
                AND is_active = 1
                AND step_number <= (
                    SELECT step_number
                    FROM tutorial_steps
                    WHERE version = ? AND step_id = ?
                )';

        $result = $this->db->exe($sql, [$version, $version, $stepId]);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['position'];
        }

        return 1;
    }

    /**
     * Convert database row to step data array with config matching old JSON format
     *
     * This ensures backward compatibility with existing AbstractStep code
     *
     * @param array $row Database row
     * @return array Step data with 'config' array
     */
    private function convertRowToStepData(array $row): array
    {
        // Parse JSON-aggregated 1:N relationships (eliminates 4 additional queries)
        $interactions = $this->parseJsonArray($row['interactions_json'] ?? null);
        $highlights = $this->parseJsonArray($row['highlights_json'] ?? null);
        $contextChanges = $this->parseJsonObject($row['context_changes_json'] ?? null);
        $prepareNextStep = $this->parseJsonObject($row['next_preparation_json'] ?? null);

        // Convert context change values to appropriate types
        if (!empty($contextChanges)) {
            foreach ($contextChanges as $key => $value) {
                if (is_numeric($value)) {
                    $contextChanges[$key] = (int)$value;
                } elseif (is_string($value)) {
                    // Convert 'true'/'false' strings to boolean
                    $lowerValue = strtolower($value);
                    if ($lowerValue === 'true' || $lowerValue === 'false') {
                        $contextChanges[$key] = ($lowerValue === 'true');
                    }
                }
            }
        }

        // Convert preparation values to appropriate types
        if (!empty($prepareNextStep)) {
            foreach ($prepareNextStep as $key => $value) {
                if (is_numeric($value)) {
                    $prepareNextStep[$key] = (int)$value;
                }
            }
        }

        // Build config array matching old JSON structure
        $config = [
            'text' => $row['text'],
            'target_selector' => $row['target_selector'],
            'target_description' => $row['target_description'],
            'highlight_selector' => $row['highlight_selector'],
            'tooltip_position' => $row['tooltip_position'] ?? 'bottom',
            'interaction_mode' => $row['interaction_mode'] ?? 'blocking',
            'blocked_click_message' => $row['blocked_click_message'],
            'show_delay' => (int)$row['show_delay'],
            'requires_validation' => (bool)$row['requires_validation'],
            'validation_type' => $row['validation_type'],
            'validation_hint' => $row['validation_hint'],
            'dialog_id' => $row['dialog_id'],
        ];

        // Add optional UI fields
        if ($row['auto_advance_delay'] !== null) {
            $config['auto_advance_delay'] = (int)$row['auto_advance_delay'];
        }
        if ($row['allow_manual_advance'] !== null) {
            $config['allow_manual_advance'] = (bool)$row['allow_manual_advance'];
        }
        if ($row['auto_close_card'] !== null) {
            $config['auto_close_card'] = (bool)$row['auto_close_card'];
        }

        // Add tooltip offset if non-zero
        if ($row['tooltip_offset_x'] || $row['tooltip_offset_y']) {
            $config['tooltip_offset'] = [
                'x' => (int)$row['tooltip_offset_x'],
                'y' => (int)$row['tooltip_offset_y']
            ];
        }

        // Add validation params if present
        if ($row['requires_validation']) {
            $validationParams = [];

            if ($row['target_x'] !== null) $validationParams['target_x'] = (int)$row['target_x'];
            if ($row['target_y'] !== null) $validationParams['target_y'] = (int)$row['target_y'];
            if ($row['movement_count'] !== null) $validationParams['movement_count'] = (int)$row['movement_count'];
            if ($row['panel_id'] !== null) $validationParams['panel'] = $row['panel_id'];
            if ($row['element_selector'] !== null) $validationParams['element'] = $row['element_selector'];
            if ($row['element_clicked'] !== null) $validationParams['element_clicked'] = $row['element_clicked'];
            if ($row['action_name'] !== null) $validationParams['action_name'] = $row['action_name'];
            if ($row['action_charges_required'] !== null) $validationParams['action_charges_required'] = (int)$row['action_charges_required'];
            if ($row['combat_required']) $validationParams['combat_required'] = true;

            if (!empty($validationParams)) {
                $config['validation_params'] = $validationParams;
            }
        }

        // Add prerequisites if present
        if ($row['mvt_required'] !== null || $row['pa_required'] !== null) {
            $prerequisites = [];

            if ($row['mvt_required'] !== null) $prerequisites['mvt'] = (int)$row['mvt_required'];
            if ($row['pa_required'] !== null) {
                $prerequisites['pa'] = (int)$row['pa_required'];
                $prerequisites['actions'] = (int)$row['pa_required']; // Backward compat
            }
            if ($row['auto_restore'] !== null) $prerequisites['auto_restore'] = (bool)$row['auto_restore'];

            // Add special prerequisites
            if ($row['ensure_harvestable_tree_x'] !== null && $row['ensure_harvestable_tree_y'] !== null) {
                $prerequisites['ensure_harvestable_tree'] = [
                    'x' => (int)$row['ensure_harvestable_tree_x'],
                    'y' => (int)$row['ensure_harvestable_tree_y']
                ];
            }

            $config['prerequisites'] = $prerequisites;
        }

        // Add context changes if present OR if boolean flags from prerequisites
        $hasContextFlags = $row['consume_movements'] || $row['unlimited_mvt'] || $row['unlimited_pa'];

        if (!empty($contextChanges) || $hasContextFlags) {
            $config['context_changes'] = $contextChanges ?? [];

            // Add boolean flags from prerequisites table ONLY if not already in context_changes
            // Context changes table takes precedence over prerequisites table
            if (!isset($config['context_changes']['consume_movements']) && $row['consume_movements']) {
                $config['context_changes']['consume_movements'] = true;
            }
            if (!isset($config['context_changes']['unlimited_mvt']) && $row['unlimited_mvt']) {
                $config['context_changes']['unlimited_mvt'] = true;
            }
            if (!isset($config['context_changes']['unlimited_actions']) && $row['unlimited_pa']) {
                $config['context_changes']['unlimited_actions'] = true;
            }
        }

        // Add next step preparation if present
        if (!empty($prepareNextStep)) {
            $config['prepare_next_step'] = $prepareNextStep;

            // Add spawn_enemy from prerequisites if present
            if ($row['spawn_enemy']) {
                $config['prepare_next_step']['spawn_enemy'] = $row['spawn_enemy'];
            }
        }

        // Add allowed interactions if present
        if (!empty($interactions)) {
            $config['allowed_interactions'] = $interactions;
        }

        // Add additional highlights if present
        if (!empty($highlights)) {
            $config['additional_highlights'] = $highlights;
        }

        // Add features if present
        if ($row['celebration']) $config['celebration'] = true;
        if ($row['show_rewards']) $config['show_rewards'] = true;
        if ($row['redirect_delay'] !== null) $config['redirect_delay'] = (int)$row['redirect_delay'];

        return [
            'step_id' => $row['step_id'],
            'next_step' => $row['next_step'],
            'step_number' => (float)$row['step_number'],
            'step_type' => $row['step_type'],
            'title' => $row['title'],
            'config' => $config,
            'xp_reward' => (int)$row['xp_reward']
        ];
    }

    // Removed: getStepInteractions(), getStepHighlights(), getStepContextChanges(), getStepNextPreparation()
    // These methods have been replaced by JSON aggregation in the main queries (eliminates N+1 query pattern).
    // The data is now fetched in a single query using JSON_ARRAYAGG and JSON_OBJECTAGG,
    // reducing 5 queries per step to just 1 query.

    /**
     * Parse JSON array from database (handles NULL and decode errors)
     *
     * @param string|null $json JSON string from JSON_ARRAYAGG
     * @return array Decoded array or empty array on failure
     */
    private function parseJsonArray(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[TutorialStepRepository] JSON decode error for array: " . json_last_error_msg() . " | JSON: " . substr($json, 0, 100));
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Parse JSON object from database (handles NULL and decode errors)
     *
     * @param string|null $json JSON string from JSON_OBJECTAGG
     * @return array Decoded associative array or empty array on failure
     */
    private function parseJsonObject(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[TutorialStepRepository] JSON decode error for object: " . json_last_error_msg() . " | JSON: " . substr($json, 0, 100));
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
