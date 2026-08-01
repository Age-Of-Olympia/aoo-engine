<?php

require_once __DIR__ . '/../vendor/autoload.php';

/* A database the suite may RUIN.
 *
 * The legacy fixtures write real rows through the production paths, and their
 * teardown deletes across some twenty-five tables with no transaction: the
 * first foreign-key refusal abandons the rest. Run against the development
 * world, an interrupted teardown therefore leaves entities standing on tiles
 * that later cases build on — and each poisoned run poisons the next.
 *
 * `AOO_TEST_DB=` (empty) puts the suite back on the configured database, which
 * is how one reproduces something seen only against real data.
 */
$aooTestDb = getenv('AOO_TEST_DB');
if ($aooTestDb === false) {
    $aooTestDb = 'aoo4_phpunit';
}
if ($aooTestDb !== '') {
    App\Factory\EntityManagerFactory::useDatabase($aooTestDb);
}

// Sous le SAPI cli, error_log() sort sur stderr ; PHPUnit
// (beStrictAboutOutputDuringTests + failOnRisky) compte cette sortie comme
// du bruit de test et marque le test risky → run en échec. Les messages
// diagnostiques légitimes (ex. le garde-fou SVG de MainView) partent dans
// un fichier au lieu de polluer la sortie.
ini_set('error_log', sys_get_temp_dir() . '/phpunit-error.log');

// The engine/simulator unit tests deliberately run actions with no seeded
// XP/log rows; silence the data-driven-config warning so its error_log() does
// not trip the suite's strict no-output / fail-on-risky checks.
App\Service\Action\TypeConfigWarning::$silenced = true;

// Canonical game constants for the whole suite. Defining CARACS here, once and
// complete, removes the global-constant pollution where the first test to define
// a partial CARACS won the define-race and corrupted later tests — a CARACS
// without 'mvt' made the tutorial DB tests' getRemaining('mvt') return null.
// Mirrors config/constants.php; the per-test `if (!defined('CARACS'))` guards
// become no-ops.
if (!defined('CARACS')) {
    define('CARACS', [
        'a' => 'A', 'mvt' => 'Mvt', 'p' => 'P', 'pv' => 'PV', 'cc' => 'CC',
        'ct' => 'CT', 'f' => 'F', 'e' => 'E', 'agi' => 'Agi', 'pm' => 'PM',
        'fm' => 'FM', 'm' => 'M', 'r' => 'R', 'rm' => 'RM', 'spd' => 'Spd', 'ae' => 'Ae',
    ]);
}
if (!defined('ONE_DAY')) {
    define('ONE_DAY', 86400);
}
// XP tuning constants, mirrored from config/constants.php — the legacy
// calculate*Xp() fallbacks (e.g. AttackAction) read these.
if (!defined('ACTION_XP')) {
    define('ACTION_XP', 5);
}
if (!defined('MAX_XP_FOR_STEALING')) {
    define('MAX_XP_FOR_STEALING', 3);
}
// The equipment model, mirrored from config/constants.php so the simulator's
// slot/limit rules are exercised against the real shape (14 slots; 3 normal
// items + ring/munition/trophee on top).
if (!defined('ITEM_EMPLACEMENT_FORMAT')) {
    define('ITEM_EMPLACEMENT_FORMAT', [
        'main1', 'main2', 'deuxmains', 'doigt', 'tete', 'bouche', 'cou',
        'epaule', 'cape', 'tronc', 'taille', 'pieds', 'munition', 'trophee',
    ]);
}
if (!defined('ITEM_LIMIT')) {
    define('ITEM_LIMIT', 3);
}
