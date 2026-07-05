<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\ImportExport\BundleEnvelope;
use App\Service\ImportExport\ImporterRegistry;
use App\View\Action\ImportFormView;

/** Hard ceiling on an uploaded bundle; a real export of the whole catalogue is ~50 KB. */
const MAX_BUNDLE_BYTES = 1048576; // 1 MiB

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
        throw new InvalidArgumentException('Fichier trop volumineux (max 1 Mo).');
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
