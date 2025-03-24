<?php

session_start();


// display php errors
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);


// classes autoloader
spl_autoload_register(function ($class_name) {
    $base_dir = __DIR__ . '/classes/';

    $file_name = strtolower($class_name) . '.php';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getFilename()) === $file_name) {
            require_once $file->getPathname();
            return;
        }
    }
});

require_once(__DIR__.'/config/constants.php');
require_once(__DIR__.'/config/db_constants.php');
require_once(__DIR__.'/config/bootstrap.php');
require_once(__DIR__.'/config/functions.php');

if(!defined('NO_LOGIN') && !isset($_SESSION['playerId'])){

    $ui = new Ui('Connexion requise');
    exit('<div><a href="/index.php">Connectez-vous</a> pour accéder à cette page.</div>');
}
