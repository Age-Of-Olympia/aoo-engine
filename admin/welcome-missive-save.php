<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\WelcomeMissiveService;

(new AdminMenuAccessService())->enforce('players.welcome-missive');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/welcome-missive.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/welcome-missive.php');
}

$service = new WelcomeMissiveService();
$title = trim((string) ($_POST['title'] ?? ''));
$text = trim((string) ($_POST['text'] ?? ''));

try {
    switch ($_GET['action'] ?? '') {
        case 'author':
            $service->setAuthor(intWithDefault('author', 0));
            setFlash('success', 'Expéditeur enregistré.');
            break;

        case 'choose':
            $service->choose((string) ($_POST['missive'] ?? ''));
            setFlash('success', 'Missive d\'accueil choisie.');
            break;

        case 'save':
        case 'create':
            if ($title === '' || $text === '') {
                throw new RuntimeException('Le titre et le texte sont obligatoires.');
            }
            $_GET['action'] === 'save' ? $service->save($title, $text) : $service->create($title, $text);
            setFlash('success', 'Missive d\'accueil enregistrée.');
            break;

        default:
            setFlash('warning', 'Action inconnue.');
    }
} catch (\Throwable $e) {
    setFlash('danger', $e->getMessage());
}

redirectTo('/admin/welcome-missive.php'); /* PRG : pas de re-soumission au refresh */
