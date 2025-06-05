<?php
use Classes\Ui;
session_start();

require_once(__DIR__.'/config/constants.php');
require_once(__DIR__.'/config/db_constants.php');
require_once(__DIR__.'/config/bootstrap.php');
require_once(__DIR__.'/config/functions.php');

if(!defined('NO_LOGIN') && !isset($_SESSION['playerId'])){

    $ui = new Ui('Connexion requise');
    exit('<div><a href="/index.php">Connectez-vous</a> pour accéder à cette page.</div>');
}
