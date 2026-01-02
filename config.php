<?php
use Classes\Ui;
use Classes\Db;
session_start();

require_once(__DIR__.'/config/constants.php');
require_once(__DIR__.'/config/db_constants.php');
require_once(__DIR__.'/config/bootstrap.php');
require_once(__DIR__.'/config/functions.php');

if(!defined('NO_LOGIN') && !isset($_SESSION['playerId'])){

    $ui = new Ui('Connexion requise');
    exit('<div><a href="/index.php">Connectez-vous</a> pour accéder à cette page.</div>');
}

// SECURITY NOTE: Tutorial session vars ($_SESSION['in_tutorial'], $_SESSION['tutorial_player_id'])
// are ONLY set by:
// 1. api/tutorial/start.php when starting a new tutorial
// 2. api/tutorial/resume.php when explicitly resuming via JavaScript
//
// We do NOT auto-activate tutorial mode on every page load, as this would:
// - Switch the player's character unexpectedly
// - Be a major security/UX issue
//
// If you want to check for active tutorials, use the resume.php API endpoint explicitly.
