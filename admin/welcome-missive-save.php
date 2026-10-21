<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\WelcomeMissiveService;

(new AdminMenuAccessService())->enforce('players.welcome-missive');

requireAdminPost('/admin/welcome-missive.php');

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
