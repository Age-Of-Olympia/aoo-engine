<?php declare(strict_types=1);
/**
 * Tutorial Step Save Handler
 *
 * Processes form submission using services for:
 * - CSRF protection
 * - Input validation
 * - Data persistence
 */

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
require_once(__DIR__ . '/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\TutorialStepValidationService;
use App\Service\TutorialStepSaveService;
use Classes\Db;

// Check admin authorization
(new AdminMenuAccessService())->enforce('tutorial.steps');

// Get database ID from db_step_id (for edits), not from step_id field
$dbStepId = optionalInt('db_step_id');
$isEdit = $dbStepId !== null;
$editorUrl = '/admin/tutorial-step-editor.php' . ($isEdit ? "?id={$dbStepId}" : '');

// POST with a valid token only; a refused one is not stashed into the editor.
requireAdminPost($editorUrl);

$database = new Db();
$validator = new TutorialStepValidationService();
$saveService = new TutorialStepSaveService($database, $validator);

try {
    $saveService->saveStep($_POST, $dbStepId);

    // Success flash message
    setFlash('success', $isEdit ? 'Étape enregistrée.' : 'Étape créée.');

    redirectTo('/admin/tutorial.php');

} catch (\InvalidArgumentException $e) {
    // Validation errors - user-friendly messages
    setFlash('warning', $e->getMessage());
    stashOldInput();

    redirectTo($editorUrl);

} catch (\Exception $e) {
    // Unexpected errors - log technical details, show generic message
    error_log("[TutorialStepSave] Unexpected error: " . $e->getMessage());

    setFlash('danger', 'Erreur inattendue pendant l\'enregistrement. Réessayez ; si l\'erreur persiste, prévenez un développeur.');
    stashOldInput();

    redirectTo($editorUrl);
}

/**
 * Keep the submitted form for the editor to repopulate after the
 * error redirect (PRG pattern).
 */
function stashOldInput(): void
{
    $input = $_POST;
    unset($input['csrf_token']);
    $_SESSION['tutorial_step_old_input'] = $input;
}
