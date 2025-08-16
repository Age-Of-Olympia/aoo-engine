<?php
// tests/bootstrap.php

// Autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Définir les constantes nécessaires pour les tests
if (!defined('THREE_DAYS')) {
    define('THREE_DAYS', 259200); // 3 jours en secondes
}

// Mock des fonctions globales si nécessaire
if (!function_exists('time')) {
    // time() est native, mais on peut la mocker si besoin avec runkit ou une autre approche
}

// Inclure les enums et interfaces du jeu
require_once __DIR__ . '/../App/Enum/CoordType.php';
require_once __DIR__ . '/../App/Interface/ActorInterface.php';

// Initialiser les mocks globaux si nécessaire
global $jsonMock;
$jsonMock = null;

// Fonction pour nettoyer l'état entre les tests
function resetGlobalMocks(): void
{
    global $jsonMock;
    $jsonMock = null;
}