<?php
use Classes\Ui;
use Classes\Db;

require_once(__DIR__.'/config/deploy_targets.php');

// --- Per-environment session isolation & hardening --------------------------
// prod, test and experimental now share a single o2switch account. Without a
// distinct cookie name and storage per env they would share PHP session cookies
// and files on the same host, leaking logins between environments.
if (PHP_SAPI !== 'cli') {
    $aooEnv = aoo_deploy_env($_SERVER['HTTP_HOST'] ?? null);

    // Distinct cookie name per env so prod/test/exp sessions never collide.
    session_name($aooEnv['session_name']);

    // Store sessions inside the docroot (web access blocked by .htaccess) so
    // each env writes to its own directory. Only when present + writable, to
    // avoid breaking local/CI where it is not provisioned.
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $aooSessionDir = $_SERVER['DOCUMENT_ROOT'] . '/sessions';
        if (is_dir($aooSessionDir) && is_writable($aooSessionDir)) {
            session_save_path($aooSessionDir);
        }
    }

    // Harden the session cookie. Secure only under HTTPS so local http works.
    // domain '' keeps the cookie host-only: do NOT scope it to
    // .age-of-olympia.net or it would bleed across the subdomains.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

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
