<?php
/**
 * Page d'accueil — mutations (POST only). Pendant de admin/landing.php.
 *
 * Routé sur ?action :
 *   section-save | section-delete
 *   news-save    | news-delete
 *   image-add    | image-update | image-delete
 *
 * CSRF validé ; même niveau d'accès que le menu landing.php (alias
 * AdminMenuAccessService) pour qu'un POST direct ne contourne rien.
 * Redirige (PRG) avec un flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\DateFormatService;
use App\Service\LandingContentService;

(new AdminMenuAccessService())->enforce('landing.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/landing.php');
}

/* Envoi > post_max_size : PHP vide $_POST ET $_FILES sans erreur — sans
 * cette garde, l'admin verrait « jeton de sécurité invalide », illisible. */
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    setFlash('danger', sprintf(
        'Envoi trop volumineux (%s) : le serveur accepte %s par requête (post_max_size).',
        round(((int) $_SERVER['CONTENT_LENGTH']) / 1048576, 1) . ' Mo',
        iniSizeLabel((string) ini_get('post_max_size'))
    ));
    redirectTo('/admin/landing.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/landing.php');
}

$service = new LandingContentService();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'section-save':
            $service->saveSection(
                strtolower(trim((string) ($_POST['slug'] ?? ''))),
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['body'] ?? ''),
                intWithDefault('position', 0),
                booleanCheckbox('is_active')
            );
            setFlash('success', 'Section enregistrée.');
            break;

        case 'section-delete':
            $service->deleteSection(strtolower(trim((string) ($_POST['slug'] ?? ''))));
            setFlash('success', 'Section supprimée.');
            break;

        case 'news-save':
            $service->saveNews(
                optionalInt('id'),
                /* Saisie française JJ/MM/AAAA → ISO pour la base */
                DateFormatService::parseFrench((string) ($_POST['news_date'] ?? '')),
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['text'] ?? ''),
                booleanCheckbox('is_active')
            );
            setFlash('success', 'Chronique enregistrée.');
            break;

        case 'news-delete':
            $service->deleteNews(intWithDefault('id', 0));
            setFlash('success', 'Chronique supprimée.');
            break;

        case 'image-add':
            $file = $_FILES['image_file'] ?? null;
            $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                $maxUpload = iniSizeLabel((string) ini_get('upload_max_filesize'));
                throw new RuntimeException(match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                        "Image trop lourde : le serveur accepte {$maxUpload} par fichier."
                        . ' Réduisez ou recompressez l\'image (jpg/webp).',
                    UPLOAD_ERR_PARTIAL => 'Transfert interrompu : l\'image n\'est arrivée qu\'en partie, réessayez.',
                    UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné : choisissez une image avant d\'ajouter.',
                    default => "Échec de l'upload (code {$uploadError}) : réessayez, ou voyez les logs serveur.",
                });
            }
            $service->addImage(
                (string) $file['tmp_name'],
                (string) $file['name'],
                (string) ($_POST['caption'] ?? ''),
                intWithDefault('position', 0)
            );
            setFlash('success', 'Aperçu ajouté à la galerie.');
            break;

        case 'image-update':
            $service->updateImage(
                intWithDefault('id', 0),
                (string) ($_POST['caption'] ?? ''),
                intWithDefault('position', 0),
                booleanCheckbox('is_active')
            );
            setFlash('success', 'Aperçu mis à jour.');
            break;

        case 'image-delete':
            $service->deleteImage(intWithDefault('id', 0));
            setFlash('success', 'Aperçu supprimé.');
            break;

        default:
            setFlash('warning', 'Action inconnue.');
    }
} catch (\Throwable $e) {
    setFlash('danger', $e->getMessage());
}

redirectTo('/admin/landing.php'); /* PRG : pas de re-soumission au refresh */
