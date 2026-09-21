<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\ImportExport\BundleEnvelope;
use App\Service\ImportExport\ImporterRegistry;
use App\View\Action\ImportFormView;

/** Ceiling on an uploaded bundle, in MB; set from the admin dashboard. */
const BUNDLE_MAX_MB_SETTING = 'import_bundle_max_mb';
const BUNDLE_MAX_MB_DEFAULT = 200;
$bundleMaxMb = max(1, (int) (new App\Service\AdminSettingsService())->get(BUNDLE_MAX_MB_SETTING, (string) BUNDLE_MAX_MB_DEFAULT));
define('MAX_BUNDLE_BYTES', $bundleMaxMb * 1024 * 1024);

$csrf = new CsrfProtectionService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

        $json = read_uploaded_bundle($_FILES['bundle'] ?? null);
        $parsed = BundleEnvelope::parse($json); // validates envelope + json_decode depth

        if (!in_array($parsed->objectType, (new ImporterRegistry())->objectTypes(), true)) {
            throw new InvalidArgumentException("Type d'objet non supporté : « {$parsed->objectType} ».");
        }

        // Stash the raw JSON (not the parsed report): preview and commit re-parse
        // and re-validate it, never trusting a precomputed result.
        $_SESSION['action_import_bundle'] = $json;
        $_SESSION['action_import_filename'] = (string) ($_FILES['bundle']['name'] ?? 'bundle.json');
        $csrf->regenerateToken();

        header('Location: /admin/action-import-preview.php');
        exit;
    } catch (InvalidArgumentException | RuntimeException $exception) {
        setFlash('warning', $exception->getMessage());
        header('Location: /admin/action-import.php');
        exit;
    } catch (\Throwable $exception) {
        setFlash('danger', "Erreur lors de la lecture du fichier.");
        header('Location: /admin/action-import.php');
        exit;
    }
}

/**
 * Validate the uploaded file and return its contents. Fails closed: real upload,
 * .json extension, within the size cap.
 *
 * @param mixed $file the $_FILES['bundle'] entry
 */
function read_uploaded_bundle($file): string
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Aucun fichier reçu (ou upload incomplet).');
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Fichier invalide.');
    }
    if ((int) ($file['size'] ?? 0) > MAX_BUNDLE_BYTES) {
        throw new InvalidArgumentException('Fichier trop volumineux (max ' . (MAX_BUNDLE_BYTES >> 20) . ' Mo).');
    }
    if (strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'json') {
        throw new InvalidArgumentException('Seuls les fichiers .json sont acceptés.');
    }

    $json = file_get_contents((string) $file['tmp_name']);
    if ($json === false || strlen($json) > MAX_BUNDLE_BYTES) {
        throw new InvalidArgumentException('Fichier illisible ou trop volumineux.');
    }

    return $json;
}

$body = (new ImportFormView())->render($csrf->renderTokenField());
echo admin_layout('Importer', renderFlashMessage() . $body);
