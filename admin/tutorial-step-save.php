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

use App\Service\AdminAuthorizationService;
use App\Service\CsrfProtectionService;
use App\Service\TutorialStepValidationService;
use App\Service\TutorialStepSaveService;
use Classes\Db;

// Check admin authorization
AdminAuthorizationService::DoAdminCheck();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('tutorial.php');
}

// Initialize services
$csrf = new CsrfProtectionService();
$database = new Db();
$validator = new TutorialStepValidationService();
$saveService = new TutorialStepSaveService($database, $validator);

// Get database ID from db_step_id (for edits), not from step_id field
$dbStepId = optionalInt('db_step_id');
$isEdit = $dbStepId !== null;

try {
    // CSRF Protection
    $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

    // Save step using service
    $savedStepId = $saveService->saveStep($_POST, $dbStepId);

    // Regenerate CSRF token for next request
    $csrf->regenerateToken();

    // Success flash message
    setFlash('success', ($isEdit ? 'Step updated' : 'Step created') . ' successfully!');

    redirectTo('tutorial.php');

} catch (\InvalidArgumentException $e) {
    // Validation errors - user-friendly messages
    setFlash('warning', $e->getMessage());

    // Redirect back to form with step ID if editing
    redirectTo('tutorial-step-editor.php' . ($isEdit ? "?id={$dbStepId}" : ''));

} catch (\RuntimeException $e) {
    // Security errors (CSRF, etc.) - user-friendly messages
    setFlash('danger', $e->getMessage());

    redirectTo('tutorial-step-editor.php' . ($isEdit ? "?id={$dbStepId}" : ''));

} catch (\Exception $e) {
    // Unexpected errors - log technical details, show generic message
    error_log("[TutorialStepSave] Unexpected error: " . $e->getMessage());
    error_log($e->getTraceAsString());

    setFlash('danger', 'An unexpected error occurred while saving. Please try again or contact support if the problem persists.');

    redirectTo('tutorial-step-editor.php' . ($isEdit ? "?id={$dbStepId}" : ''));
}
