<?php
/**
 * Tutorial Sessions API
 *
 * AJAX endpoint for fetching and managing tutorial sessions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\CsrfProtectionService;

header('Content-Type: application/json; charset=utf-8');

$database = new Db();
$csrf = new CsrfProtectionService();

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Fetch recent sessions with player info
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);

            $result = $database->exe("
                SELECT
                    tp.tutorial_session_id,
                    tp.player_id,
                    tp.current_step,
                    tp.completed,
                    tp.xp_earned,
                    tp.tutorial_version,
                    tp.created_at,
                    tp.updated_at,
                    p.name as player_name,
                    p.race as player_race,
                    ts.title as current_step_title,
                    ts.step_number,
                    (SELECT COUNT(*) FROM tutorial_steps WHERE version = tp.tutorial_version AND is_active = 1) as total_steps
                FROM tutorial_progress tp
                LEFT JOIN players p ON tp.player_id = p.id
                LEFT JOIN tutorial_steps ts ON tp.current_step = ts.step_id AND ts.version = tp.tutorial_version
                ORDER BY tp.updated_at DESC
                LIMIT ? OFFSET ?
            ", [$limit, $offset]);

            $sessions = [];
            while ($row = $result->fetch_assoc()) {
                $sessions[] = $row;
            }

            // Get total count
            $countResult = $database->exe("SELECT COUNT(*) as total FROM tutorial_progress");
            $total = $countResult->fetch_assoc()['total'];

            echo json_encode([
                'success' => true,
                'sessions' => $sessions,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;

        case 'detail':
            $sessionId = $_GET['session_id'] ?? null;
            if (!$sessionId) {
                throw new Exception('Session ID required');
            }

            // Get session info
            $result = $database->exe("
                SELECT
                    tp.*,
                    p.name as player_name,
                    p.race as player_race
                FROM tutorial_progress tp
                LEFT JOIN players p ON tp.player_id = p.id
                WHERE tp.tutorial_session_id = ?
            ", [$sessionId]);

            $session = $result->fetch_assoc();
            if (!$session) {
                throw new Exception('Session not found');
            }

            // Get step history (completed steps)
            $stepResult = $database->exe("
                SELECT
                    ts.step_number,
                    ts.step_id,
                    ts.title,
                    ts.step_type,
                    ts.xp_reward
                FROM tutorial_steps ts
                WHERE ts.version = ?
                AND ts.is_active = 1
                ORDER BY ts.step_number
            ", [$session['tutorial_version']]);

            $steps = [];
            while ($row = $stepResult->fetch_assoc()) {
                $row['is_current'] = ($row['step_id'] === $session['current_step']);
                $steps[] = $row;
            }

            // Get tutorial player if exists
            $tutorialPlayerResult = $database->exe("
                SELECT
                    tpl.id,
                    tpl.player_id as tutorial_player_id,
                    tpl.name as tutorial_player_name,
                    tpl.is_active,
                    p.id as real_id,
                    c.x, c.y, c.plan
                FROM tutorial_players tpl
                LEFT JOIN players p ON tpl.player_id = p.id
                LEFT JOIN coords c ON p.coords_id = c.id
                WHERE tpl.tutorial_session_id = ?
            ", [$sessionId]);

            $tutorialPlayer = $tutorialPlayerResult->fetch_assoc();

            echo json_encode([
                'success' => true,
                'session' => $session,
                'steps' => $steps,
                'tutorial_player' => $tutorialPlayer
            ]);
            break;

        case 'reset':
            // Reset a session to beginning
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST required');
            }

            $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

            $sessionId = $_POST['session_id'] ?? null;
            if (!$sessionId) {
                throw new Exception('Session ID required');
            }

            // Get first step
            $firstStepResult = $database->exe("
                SELECT step_id FROM tutorial_steps
                WHERE is_active = 1
                ORDER BY step_number
                LIMIT 1
            ");
            $firstStep = $firstStepResult->fetch_assoc();

            $database->exe("
                UPDATE tutorial_progress
                SET current_step = ?, completed = 0, xp_earned = 0, updated_at = NOW()
                WHERE tutorial_session_id = ?
            ", [$firstStep['step_id'], $sessionId]);

            echo json_encode(['success' => true, 'message' => 'Session reset']);
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST required');
            }

            $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

            $sessionId = $_POST['session_id'] ?? null;
            if (!$sessionId) {
                throw new Exception('Session ID required');
            }

            // Delete tutorial player first
            $database->exe("DELETE FROM tutorial_players WHERE tutorial_session_id = ?", [$sessionId]);
            // Delete enemies
            $database->exe("DELETE FROM tutorial_enemies WHERE tutorial_session_id = ?", [$sessionId]);
            // Delete progress
            $database->exe("DELETE FROM tutorial_progress WHERE tutorial_session_id = ?", [$sessionId]);

            echo json_encode(['success' => true, 'message' => 'Session deleted']);
            break;

        case 'export_all_steps':
            // Export all tutorial steps with all related data
            $stepsResult = $database->exe("
                SELECT * FROM tutorial_steps ORDER BY step_number
            ");

            $steps = [];
            while ($step = $stepsResult->fetch_assoc()) {
                $stepId = $step['id'];

                // Get UI config
                $uiResult = $database->exe("SELECT * FROM tutorial_step_ui WHERE step_id = ?", [$stepId]);
                $step['ui'] = $uiResult->fetch_assoc();

                // Get validation config
                $valResult = $database->exe("SELECT * FROM tutorial_step_validation WHERE step_id = ?", [$stepId]);
                $step['validation'] = $valResult->fetch_assoc();

                // Get prerequisites
                $prereqResult = $database->exe("SELECT * FROM tutorial_step_prerequisites WHERE step_id = ?", [$stepId]);
                $step['prerequisites'] = $prereqResult->fetch_assoc();

                // Get features
                $featResult = $database->exe("SELECT * FROM tutorial_step_features WHERE step_id = ?", [$stepId]);
                $step['features'] = $featResult->fetch_assoc();

                // Get interactions
                $intResult = $database->exe("SELECT selector, description FROM tutorial_step_interactions WHERE step_id = ?", [$stepId]);
                $step['interactions'] = [];
                while ($row = $intResult->fetch_assoc()) {
                    $step['interactions'][] = $row;
                }

                // Get highlights
                $hlResult = $database->exe("SELECT selector FROM tutorial_step_highlights WHERE step_id = ?", [$stepId]);
                $step['highlights'] = [];
                while ($row = $hlResult->fetch_assoc()) {
                    $step['highlights'][] = $row['selector'];
                }

                // Get context changes
                $ctxResult = $database->exe("SELECT context_key, context_value FROM tutorial_step_context_changes WHERE step_id = ?", [$stepId]);
                $step['context_changes'] = [];
                while ($row = $ctxResult->fetch_assoc()) {
                    $step['context_changes'][] = $row;
                }

                // Get next preparation
                $prepResult = $database->exe("SELECT preparation_key, preparation_value FROM tutorial_step_next_preparation WHERE step_id = ?", [$stepId]);
                $step['next_preparation'] = [];
                while ($row = $prepResult->fetch_assoc()) {
                    $step['next_preparation'][] = $row;
                }

                // Remove internal id for cleaner export
                unset($step['id']);
                if ($step['ui']) unset($step['ui']['id'], $step['ui']['step_id']);
                if ($step['validation']) unset($step['validation']['id'], $step['validation']['step_id']);
                if ($step['prerequisites']) unset($step['prerequisites']['id'], $step['prerequisites']['step_id']);
                if ($step['features']) unset($step['features']['id'], $step['features']['step_id']);

                $steps[] = $step;
            }

            echo json_encode([
                'success' => true,
                'steps' => $steps,
                'exported_at' => date('Y-m-d H:i:s'),
                'count' => count($steps)
            ]);
            break;

        case 'import_all_steps':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST required');
            }

            $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

            $stepsJson = $_POST['steps'] ?? null;
            if (!$stepsJson) {
                throw new Exception('No steps data provided');
            }

            $steps = json_decode($stepsJson, true);
            if (!is_array($steps)) {
                throw new Exception('Invalid steps format');
            }

            $created = 0;
            $updated = 0;

            foreach ($steps as $stepData) {
                $stepId = $stepData['step_id'] ?? null;
                $version = $stepData['version'] ?? '1.0.0';

                // Check if step exists
                $existingResult = $database->exe(
                    "SELECT id FROM tutorial_steps WHERE step_id = ? AND version = ?",
                    [$stepId, $version]
                );
                $existing = $existingResult->fetch_assoc();

                if ($existing) {
                    // Update existing step
                    $dbStepId = $existing['id'];
                    $database->exe("
                        UPDATE tutorial_steps SET
                            step_number = ?,
                            next_step = ?,
                            step_type = ?,
                            title = ?,
                            text = ?,
                            xp_reward = ?,
                            is_active = ?
                        WHERE id = ?
                    ", [
                        $stepData['step_number'] ?? 0,
                        $stepData['next_step'] ?? null,
                        $stepData['step_type'] ?? 'info',
                        $stepData['title'] ?? '',
                        $stepData['text'] ?? '',
                        $stepData['xp_reward'] ?? 0,
                        $stepData['is_active'] ?? 1,
                        $dbStepId
                    ]);
                    $updated++;
                } else {
                    // Insert new step
                    $database->exe("
                        INSERT INTO tutorial_steps (version, step_number, step_id, next_step, step_type, title, text, xp_reward, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $version,
                        $stepData['step_number'] ?? 0,
                        $stepId,
                        $stepData['next_step'] ?? null,
                        $stepData['step_type'] ?? 'info',
                        $stepData['title'] ?? '',
                        $stepData['text'] ?? '',
                        $stepData['xp_reward'] ?? 0,
                        $stepData['is_active'] ?? 1
                    ]);
                    $dbStepId = $database->lastInsertId();
                    $created++;
                }

                // Update/insert related tables (UI, Validation, Prerequisites, Features)
                if (isset($stepData['ui']) && $stepData['ui']) {
                    $ui = $stepData['ui'];
                    $database->exe("DELETE FROM tutorial_step_ui WHERE step_id = ?", [$dbStepId]);
                    $database->exe("
                        INSERT INTO tutorial_step_ui (step_id, target_selector, target_description, highlight_selector, tooltip_position, interaction_mode, blocked_click_message, show_delay, auto_advance_delay, allow_manual_advance, auto_close_card)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $dbStepId,
                        $ui['target_selector'] ?? null,
                        $ui['target_description'] ?? null,
                        $ui['highlight_selector'] ?? null,
                        $ui['tooltip_position'] ?? 'bottom',
                        $ui['interaction_mode'] ?? 'blocking',
                        $ui['blocked_click_message'] ?? null,
                        $ui['show_delay'] ?? 0,
                        $ui['auto_advance_delay'] ?? null,
                        $ui['allow_manual_advance'] ?? 1,
                        $ui['auto_close_card'] ?? 0
                    ]);
                }

                if (isset($stepData['validation']) && $stepData['validation']) {
                    $val = $stepData['validation'];
                    $database->exe("DELETE FROM tutorial_step_validation WHERE step_id = ?", [$dbStepId]);
                    $database->exe("
                        INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint, target_x, target_y, movement_count, action_name, action_charges_required, combat_required, panel_id, element_selector, element_clicked, dialog_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $dbStepId,
                        $val['requires_validation'] ?? 0,
                        $val['validation_type'] ?? null,
                        $val['validation_hint'] ?? null,
                        $val['target_x'] ?? null,
                        $val['target_y'] ?? null,
                        $val['movement_count'] ?? null,
                        $val['action_name'] ?? null,
                        $val['action_charges_required'] ?? 1,
                        $val['combat_required'] ?? 0,
                        $val['panel_id'] ?? null,
                        $val['element_selector'] ?? null,
                        $val['element_clicked'] ?? null,
                        $val['dialog_id'] ?? null
                    ]);
                }

                if (isset($stepData['prerequisites']) && $stepData['prerequisites']) {
                    $prereq = $stepData['prerequisites'];
                    $database->exe("DELETE FROM tutorial_step_prerequisites WHERE step_id = ?", [$dbStepId]);
                    $database->exe("
                        INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore, consume_movements, unlimited_mvt, unlimited_pa, spawn_enemy, ensure_harvestable_tree_x, ensure_harvestable_tree_y)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $dbStepId,
                        $prereq['mvt_required'] ?? null,
                        $prereq['pa_required'] ?? null,
                        $prereq['auto_restore'] ?? 1,
                        $prereq['consume_movements'] ?? 0,
                        $prereq['unlimited_mvt'] ?? 0,
                        $prereq['unlimited_pa'] ?? 0,
                        $prereq['spawn_enemy'] ?? null,
                        $prereq['ensure_harvestable_tree_x'] ?? null,
                        $prereq['ensure_harvestable_tree_y'] ?? null
                    ]);
                }

                if (isset($stepData['features']) && $stepData['features']) {
                    $feat = $stepData['features'];
                    $database->exe("DELETE FROM tutorial_step_features WHERE step_id = ?", [$dbStepId]);
                    $database->exe("
                        INSERT INTO tutorial_step_features (step_id, celebration, show_rewards, redirect_delay)
                        VALUES (?, ?, ?, ?)
                    ", [
                        $dbStepId,
                        $feat['celebration'] ?? 0,
                        $feat['show_rewards'] ?? 0,
                        $feat['redirect_delay'] ?? null
                    ]);
                }

                // Handle arrays: interactions, highlights, context_changes, next_preparation
                if (isset($stepData['interactions']) && is_array($stepData['interactions'])) {
                    $database->exe("DELETE FROM tutorial_step_interactions WHERE step_id = ?", [$dbStepId]);
                    foreach ($stepData['interactions'] as $int) {
                        $database->exe(
                            "INSERT INTO tutorial_step_interactions (step_id, selector, description) VALUES (?, ?, ?)",
                            [$dbStepId, $int['selector'] ?? $int, $int['description'] ?? null]
                        );
                    }
                }

                if (isset($stepData['highlights']) && is_array($stepData['highlights'])) {
                    $database->exe("DELETE FROM tutorial_step_highlights WHERE step_id = ?", [$dbStepId]);
                    foreach ($stepData['highlights'] as $hl) {
                        $database->exe(
                            "INSERT INTO tutorial_step_highlights (step_id, selector) VALUES (?, ?)",
                            [$dbStepId, is_string($hl) ? $hl : $hl['selector']]
                        );
                    }
                }

                if (isset($stepData['context_changes']) && is_array($stepData['context_changes'])) {
                    $database->exe("DELETE FROM tutorial_step_context_changes WHERE step_id = ?", [$dbStepId]);
                    foreach ($stepData['context_changes'] as $ctx) {
                        $database->exe(
                            "INSERT INTO tutorial_step_context_changes (step_id, context_key, context_value) VALUES (?, ?, ?)",
                            [$dbStepId, $ctx['context_key'], $ctx['context_value']]
                        );
                    }
                }

                if (isset($stepData['next_preparation']) && is_array($stepData['next_preparation'])) {
                    $database->exe("DELETE FROM tutorial_step_next_preparation WHERE step_id = ?", [$dbStepId]);
                    foreach ($stepData['next_preparation'] as $prep) {
                        $database->exe(
                            "INSERT INTO tutorial_step_next_preparation (step_id, preparation_key, preparation_value) VALUES (?, ?, ?)",
                            [$dbStepId, $prep['preparation_key'], $prep['preparation_value']]
                        );
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'total' => count($steps)
            ]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
