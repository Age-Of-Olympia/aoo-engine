<?php

namespace Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * One process-wide probe of the dedicated test database (aoo4_test).
 *
 * Every DB-backed integration test used to open its own connection in
 * setUp() and skip on failure. Where the host does not exist at all — the
 * CI test job has no aoo4_test, only the endpoint job does — that meant
 * one full connect timeout PER TEST: minutes of wall clock spent skipping.
 * The outcome is now decided once, with a short timeout, and remembered
 * for the whole process.
 */
final class TestDb
{
    private static ?Connection $connection = null;
    private static ?string $failure = null;
    private static bool $probed = false;

    /**
     * The shared connection to aoo4_test, or null when unreachable —
     * callers skip with {@see failure()} as the reason.
     */
    public static function connectionOrNull(): ?Connection
    {
        if (!self::$probed) {
            self::$probed = true;

            $params = [
                'host'     => getenv('TEST_DB_HOST') ?: 'mariadb-aoo4',
                'user'     => getenv('TEST_DB_USER') ?: 'root',
                'password' => getenv('TEST_DB_PASS') ?: 'passwordRoot',
                'dbname'   => getenv('TEST_DB_NAME') ?: 'aoo4_test',
                'driver'   => 'mysqli',
                'charset'  => 'utf8mb4',
                // An absent host must fail fast: this probe runs once, but
                // it must not cost a DNS/TCP timeout's worth of seconds.
                'driverOptions' => [MYSQLI_OPT_CONNECT_TIMEOUT => 2],
            ];

            try {
                $conn = DriverManager::getConnection($params);
                // DriverManager is lazy: force-connect so "host unreachable"
                // surfaces here, not at first use.
                $conn->executeQuery('SELECT 1');
                self::$connection = $conn;
            } catch (\Throwable $e) {
                self::$failure = sprintf(
                    'Test DB %s@%s/%s unavailable (%s). Run scripts/testing/reset_test_database.sh.',
                    $params['user'],
                    $params['host'],
                    $params['dbname'],
                    $e->getMessage()
                );
            }
        }

        return self::$connection;
    }

    /** Why the probe failed — set exactly when connectionOrNull() is null. */
    public static function failure(): string
    {
        return self::$failure ?? 'aoo4_test unavailable';
    }
}
