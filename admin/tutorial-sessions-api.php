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
