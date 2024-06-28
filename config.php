<?php

session_start();


// display php errors
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);


// classes autoloader
spl_autoload_register(function ($class_name) {

    require_once(dirname(__FILE__) .'/classes/'. strtolower($class_name) . '.php');
});


require_once('config/constants.php');
require_once('config/db_constants.php');
require_once('config/functions.php');


if(!defined('NO_LOGIN') && !isset($_SESSION['playerId'])){

    $ui = new Ui('Connexion requise');
    exit('<div><a href="index.php">Connectez-vous</a> pour accéder à cette page.</div>');
}
