<?php
/**
 * Tutorial Sessions API
 *
 * AJAX endpoint for fetching and managing tutorial sessions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\TutorialStepSaveService;
use App\Service\TutorialStepValidationService;

AdminAuthorizationService::DoAdminCheck();

header('Content-Type: application/json; charset=utf-8');

$database = new Db();
$csrf = new CsrfProtectionService();
$saveService = new TutorialStepSaveService($database, new TutorialStepValidationService());

/**
 * Flatten an exported step JSON into the form-shape that
 * TutorialStepSaveService::saveStep expects ($_POST style: nested
 * sub-objects merged into top-level, list-of-objects collapsed to
 * parallel key/value arrays).
 *
 * @param array<string, mixed> $stepData Exported step (nested shape).
 * @return array<string, mixed>          Flattened form-shape.
 */
function flattenImportStep(array $stepData): array
{
    $data = [
        'version'     => $stepData['version']     ?? '1.0.0',
        'step_id'     => $stepData['step_id']     ?? null,
        'next_step'   => $stepData['next_step']   ?? null,
        'step_number' => $stepData['step_number'] ?? 0,
        'step_type'   => $stepData['step_type']   ?? 'info',
        'title'       => $stepData['title']       ?? '',
        'text'        => $stepData['text']        ?? '',
        'xp_reward'   => $stepData['xp_reward']   ?? 0,
        'is_active'   => $stepData['is_active']   ?? 1,
    ];

    foreach (['ui', 'validation', 'prerequisites', 'features'] as $section) {
        if (!empty($stepData[$section]) && is_array($stepData[$section])) {
            $data = array_merge($data, $stepData[$section]);
        }
    }

    if (isset($stepData['interactions']) && is_array($stepData['interactions'])) {
        $data['interactions'] = array_map(
            fn($i) => is_array($i) ? ($i['selector'] ?? '') : (string)$i,
            $stepData['interactions']
        );
    }

    if (isset($stepData['highlights']) && is_array($stepData['highlights'])) {
        $data['highlights'] = array_map(
            fn($h) => is_array($h) ? ($h['selector'] ?? '') : (string)$h,
            $stepData['highlights']
        );
    }

    if (isset($stepData['context_changes']) && is_array($stepData['context_changes'])) {
        $data['context_keys']   = array_column($stepData['context_changes'], 'context_key');
        $data['context_values'] = array_column($stepData['context_changes'], 'context_value');
    }

    if (isset($stepData['next_preparation']) && is_array($stepData['next_preparation'])) {
        $data['prep_keys']   = array_column($stepData['next_preparation'], 'preparation_key');
        $data['prep_values'] = array_column($stepData['next_preparation'], 'preparation_value');
    }

    return $data;
}

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
                $stepIdStr = $stepData['step_id'] ?? null;
                $version = $stepData['version'] ?? '1.0.0';

                $existingResult = $database->exe(
                    "SELECT id FROM tutorial_steps WHERE step_id = ? AND version = ?",
                    [$stepIdStr, $version]
                );
                $existing = $existingResult->fetch_assoc();
                $dbStepId = $existing['id'] ?? null;

                $saveService->saveStep(flattenImportStep($stepData), $dbStepId);

                if ($dbStepId === null) {
                    $created++;
                } else {
                    $updated++;
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
