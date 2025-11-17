<?php
/**
 * Tutorial Step Save Handler
 *
 * Processes form submission and saves step data to normalized tables
 */

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

use App\Service\AdminAuthorizationService;
use Classes\Db;

AdminAuthorizationService::DoAdminCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tutorial.php');
    exit;
}

$database = new Db();

// Get database ID from db_step_id (for edits), not from step_id field
$dbStepId = isset($_POST['db_step_id']) ? (int)$_POST['db_step_id'] : null;
$isEdit = $dbStepId !== null;

try {
    $database->beginTransaction();

    // Basic step data
    if ($isEdit) {
        // Update existing step
        $database->exe("
            UPDATE tutorial_steps SET
                version = ?, step_id = ?, step_number = ?, step_type = ?,
                title = ?, text = ?, xp_reward = ?, is_active = ?
            WHERE id = ?
        ", [
            $_POST['version'] ?? '1.0.0',
            !empty($_POST['step_id']) ? $_POST['step_id'] : null,
            (float)$_POST['step_number'],
            $_POST['step_type'],
            $_POST['title'],
            $_POST['text'],
            (int)($_POST['xp_reward'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
            $dbStepId
        ]);
    } else {
        // Insert new step
        $database->exe("
            INSERT INTO tutorial_steps (version, step_id, step_number, step_type, title, text, xp_reward, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $_POST['version'] ?? '1.0.0',
            !empty($_POST['step_id']) ? $_POST['step_id'] : null,
            (float)$_POST['step_number'],
            $_POST['step_type'],
            $_POST['title'],
            $_POST['text'],
            (int)($_POST['xp_reward'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0
        ]);

        // Get the new step ID
        $result = $database->exe("SELECT LAST_INSERT_ID() as id");
        $row = $result->fetch_assoc();
        $dbStepId = (int)$row['id'];
    }

    // Save UI config
    $database->exe("DELETE FROM tutorial_step_ui WHERE step_id = ?", [$dbStepId]);
    $database->exe("
        INSERT INTO tutorial_step_ui (step_id, target_selector, tooltip_position, interaction_mode,
            blocked_click_message, show_delay, auto_advance_delay, auto_close_card, tooltip_offset_x, tooltip_offset_y)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $dbStepId,
        $_POST['target_selector'] ?? null,
        $_POST['tooltip_position'] ?? 'bottom',
        $_POST['interaction_mode'] ?? 'blocking',
        $_POST['blocked_click_message'] ?? null,
        (int)($_POST['show_delay'] ?? 0),
        !empty($_POST['auto_advance_delay']) ? (int)$_POST['auto_advance_delay'] : null,
        isset($_POST['auto_close_card']) ? 1 : null,
        0, // tooltip_offset_x
        0  // tooltip_offset_y
    ]);

    // Save validation config
    $database->exe("DELETE FROM tutorial_step_validation WHERE step_id = ?", [$dbStepId]);
    $database->exe("
        INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, validation_hint,
            target_x, target_y, action_name, panel_id, element_clicked)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $dbStepId,
        isset($_POST['requires_validation']) ? 1 : 0,
        $_POST['validation_type'] ?? null,
        $_POST['validation_hint'] ?? null,
        !empty($_POST['target_x']) ? (int)$_POST['target_x'] : null,
        !empty($_POST['target_y']) ? (int)$_POST['target_y'] : null,
        $_POST['action_name'] ?? null,
        $_POST['panel_id'] ?? null,
        $_POST['element_clicked'] ?? null
    ]);

    // Save prerequisites
    $database->exe("DELETE FROM tutorial_step_prerequisites WHERE step_id = ?", [$dbStepId]);

    $hasPrereq = !empty($_POST['mvt_required']) || !empty($_POST['pa_required']) ||
                 isset($_POST['consume_movements']) || isset($_POST['unlimited_mvt']) || isset($_POST['unlimited_pa']);

    if ($hasPrereq) {
        $database->exe("
            INSERT INTO tutorial_step_prerequisites (step_id, mvt_required, pa_required, auto_restore,
                consume_movements, unlimited_mvt, unlimited_pa)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $dbStepId,
            !empty($_POST['mvt_required']) ? (int)$_POST['mvt_required'] : null,
            !empty($_POST['pa_required']) ? (int)$_POST['pa_required'] : null,
            isset($_POST['auto_restore']) ? 1 : 0,
            isset($_POST['consume_movements']) ? 1 : 0,
            isset($_POST['unlimited_mvt']) ? 1 : 0,
            isset($_POST['unlimited_pa']) ? 1 : 0
        ]);
    }

    // Save allowed interactions
    $database->exe("DELETE FROM tutorial_step_interactions WHERE step_id = ?", [$dbStepId]);
    if (!empty($_POST['interactions'])) {
        foreach ($_POST['interactions'] as $selector) {
            if (!empty($selector)) {
                $database->exe("INSERT INTO tutorial_step_interactions (step_id, selector) VALUES (?, ?)", [$dbStepId, $selector]);
            }
        }
    }

    // Save additional highlights
    $database->exe("DELETE FROM tutorial_step_highlights WHERE step_id = ?", [$dbStepId]);
    if (!empty($_POST['highlights'])) {
        foreach ($_POST['highlights'] as $selector) {
            if (!empty($selector)) {
                $database->exe("INSERT INTO tutorial_step_highlights (step_id, selector) VALUES (?, ?)", [$dbStepId, $selector]);
            }
        }
    }

    // Save context changes
    $database->exe("DELETE FROM tutorial_step_context_changes WHERE step_id = ?", [$dbStepId]);
    if (!empty($_POST['context_keys'])) {
        for ($i = 0; $i < count($_POST['context_keys']); $i++) {
            $key = $_POST['context_keys'][$i];
            $value = $_POST['context_values'][$i] ?? '';
            if (!empty($key)) {
                $database->exe("INSERT INTO tutorial_step_context_changes (step_id, context_key, context_value) VALUES (?, ?, ?)",
                    [$dbStepId, $key, $value]);
            }
        }
    }

    // Save next step preparation
    $database->exe("DELETE FROM tutorial_step_next_preparation WHERE step_id = ?", [$dbStepId]);
    if (!empty($_POST['prep_keys'])) {
        for ($i = 0; $i < count($_POST['prep_keys']); $i++) {
            $key = $_POST['prep_keys'][$i];
            $value = $_POST['prep_values'][$i] ?? '';
            if (!empty($key)) {
                $database->exe("INSERT INTO tutorial_step_next_preparation (step_id, preparation_key, preparation_value) VALUES (?, ?, ?)",
                    [$dbStepId, $key, $value]);
            }
        }
    }

    // Save features
    $database->exe("DELETE FROM tutorial_step_features WHERE step_id = ?", [$dbStepId]);

    $hasFeatures = isset($_POST['celebration']) || isset($_POST['show_rewards']) || !empty($_POST['redirect_delay']);

    if ($hasFeatures) {
        $database->exe("
            INSERT INTO tutorial_step_features (step_id, celebration, show_rewards, redirect_delay)
            VALUES (?, ?, ?, ?)
        ", [
            $dbStepId,
            isset($_POST['celebration']) ? 1 : 0,
            isset($_POST['show_rewards']) ? 1 : 0,
            !empty($_POST['redirect_delay']) ? (int)$_POST['redirect_delay'] : null
        ]);
    }

    $database->commit();

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => ($isEdit ? 'Step updated' : 'Step created') . ' successfully!'
    ];

    header('Location: tutorial.php');
    exit;

} catch (Exception $e) {
    $database->rollback();

    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Error saving step: ' . $e->getMessage()
    ];

    header('Location: tutorial-step-editor.php' . ($isEdit ? '?id=' . $dbStepId : ''));
    exit;
}
