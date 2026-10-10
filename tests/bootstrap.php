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
/* Le fichier est gitignoré — absent, la base de test ne se compare à rien. */
if (!defined('DB_CONSTANTS') && file_exists(__DIR__ . '/../config/db_constants.php')) {
    require_once __DIR__ . '/../config/db_constants.php';
}
if ($aooTestDb !== '') {
    App\Factory\EntityManagerFactory::useDatabase($aooTestDb);

    /* Une base jetable RETARDE d'une migration ressemble à un bug de code : la
     * colonne manque, le cas rougit, et on cherche dans le mauvais fichier.
     * Trois fois de suite sur un seul lot. Elle se compare donc à la base
     * configurée — même serveur, une requête — et dit quoi taper.
     */
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

/* The services resolve JSON, PNG and datas/ through DOCUMENT_ROOT; under the
 * CLI it is the repository root, which is also the web docroot. */
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
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

// The game constants, from the config itself: a test that defines its own copy
// races the others, and PHPStan, which cannot see a define() inside a method,
// may index that copy instead of the config's.
// Legacy Classes\Db reads the global $link, as config/bootstrap.php sets it.
$GLOBALS['link'] = App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
require_once __DIR__ . '/../config/constants.php';
