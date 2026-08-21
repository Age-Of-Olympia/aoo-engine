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

    /* Une base jetable RETARDE d'une migration ressemble à un bug de code : la
     * colonne manque, le cas rougit, et on cherche dans le mauvais fichier.
     * Trois fois de suite sur un seul lot. Elle se compare donc à la base
     * configurée — même serveur, une requête — et dit quoi taper.
     */
    /* La config n'est pas encore chargée ici : chaque cas requiert la sienne.
     * Le fichier est gitignoré — absent, on ne compare rien. */
    if (!defined('DB_CONSTANTS') && file_exists(__DIR__ . '/../config/db_constants.php')) {
        require_once __DIR__ . '/../config/db_constants.php';
    }

    try {
        $conn = App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
        $source = defined('DB_CONSTANTS')
            ? (string) (DB_CONSTANTS['dbname'] ?? DB_CONSTANTS['db'] ?? '')
            : '';

        if ($source !== '' && $source !== $aooTestDb) {
            $counts = $conn->fetchAllKeyValue(
                'SELECT TABLE_SCHEMA, COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA IN (?, ?) GROUP BY TABLE_SCHEMA',
                [$source, $aooTestDb]
            );

            if (($counts[$source] ?? 0) !== ($counts[$aooTestDb] ?? 0)) {
                fwrite(STDERR, sprintf(
                    "\n  La base de test « %s » ne suit plus le schéma de « %s ».\n"
                    . "  Reconstruire :\n"
                    . "    docker exec -i -e DB_HOST=127.0.0.1 aoo-engine-mariadb-aoo4-1 \\\n"
                    . "      bash -s < scripts/testing/reset_phpunit_database.sh\n\n",
                    $aooTestDb,
                    $source
                ));
                exit(1);
            }
        }
    } catch (\Throwable) {
        // Base injoignable : les cas savent déjà se sauter proprement.
    }
}

/* The per-player files directory (.svg, .kills.html, .msg.html) is
 * ignored by git: a fresh working copy (CI) does not have it, and the
 * scenes that prime a cached board could not write it. */
@mkdir(__DIR__ . '/../datas/private/players', 0777, true);

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
        'fm' => 'FM', 'pui' => 'Pui', 'res' => 'Res', 'r' => 'R', 'rm' => 'RM', 'spd' => 'Spd', 'ae' => 'Ae',
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
