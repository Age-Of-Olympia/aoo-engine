<?php

namespace Tests\Tutorial\Mock;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tutorial integration tests that need a real database.
 *
 * D4 Phase C foundation. Phase B's reflection-priming pattern (used in
 * TutorialPlaceholderServiceTest, TutorialFeatureFlagTest, MovementStep-
 * Test, ActionStepTest, UIInteractionStepTest) covers everything that's
 * pure-data + config; Phase C exists for what's NOT — the DB-touching
 * branches of MovementStep (movements_depleted, position, adjacent_to_-
 * position) and the integration paths in TutorialManager::completeTutorial,
 * TutorialPlayerCleanup, TutorialEnemyCleanup.
 *
 * Design constraints:
 *
 *   1. **Skip cleanly when no test DB is available.** A developer running
 *      `make test` in a fresh checkout without first running
 *      `scripts/testing/reset_test_database.sh` should see these tests
 *      marked SKIPPED, not FAILED. Same for CI environments that haven't
 *      provisioned the test DB yet.
 *
 *   2. **Transaction-rollback isolation.** Each test wraps its work in a
 *      transaction that's rolled back in tearDown. No fixture cleanup
 *      code, no per-test scoped IDs, no risk of leftover rows poisoning
 *      the next test. (MariaDB/InnoDB fully supports this.)
 *
 *   3. **No reset between tests.** Tests assume the test DB has the
 *      schema in place from `scripts/testing/reset_test_database.sh`.
 *      They do not re-create tables. This keeps the per-test cost
 *      sub-second.
 *
 *   4. **Environment-overridable connection params.** Defaults match the
 *      devcontainer (mariadb-aoo4 host, root/passwordRoot creds,
 *      aoo4_test DB). CI overrides via env vars (TEST_DB_HOST, etc.) to
 *      hit the `mariadb` service alias on its own creds.
 *
 * Usage in concrete tests:
 *
 *     class MyIntegrationTest extends TutorialIntegrationTestCase
 *     {
 *         public function testSomething(): void
 *         {
 *             $this->conn->insert('tutorial_progress', [...]);
 *             $row = $this->conn->fetchAssociative('SELECT ...');
 *             $this->assertSame('expected', $row['col']);
 *             // tearDown rolls back automatically
 *         }
 *     }
 */
abstract class TutorialIntegrationTestCase extends TestCase
{
    protected ?Connection $conn = null;

    protected function setUp(): void
    {
        $this->conn = $this->openTestDbOrSkip();
        $this->conn->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->conn !== null && $this->conn->isTransactionActive()) {
            $this->conn->rollBack();
        }
        $this->conn = null;
    }

    /**
     * Open a Doctrine DBAL connection to the test database, or call
     * markTestSkipped() if it's unreachable.
     *
     * Devcontainer defaults: mariadb-aoo4 / root / passwordRoot / aoo4_test.
     * Override via TEST_DB_HOST / TEST_DB_USER / TEST_DB_PASS / TEST_DB_NAME.
     *
     * @return never|Connection Returns the connection on success; never
     *         returns when the DB is unavailable (markTestSkipped throws).
     */
    private function openTestDbOrSkip(): Connection
    {
        $params = [
            'host'     => getenv('TEST_DB_HOST') ?: 'mariadb-aoo4',
            'user'     => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASS') ?: 'passwordRoot',
            'dbname'   => getenv('TEST_DB_NAME') ?: 'aoo4_test',
            'driver'   => 'mysqli',
            'charset'  => 'utf8mb4',
        ];

        try {
            $conn = DriverManager::getConnection($params);
            // Force-connect by running a sanity query — DriverManager is
            // lazy and won't surface "host unreachable" until first use.
            $conn->executeQuery('SELECT 1');
            return $conn;
        } catch (\Throwable $e) {
            $this->markTestSkipped(sprintf(
                'Test DB %s@%s/%s unavailable (%s). Run scripts/testing/reset_test_database.sh.',
                $params['user'],
                $params['host'],
                $params['dbname'],
                $e->getMessage()
            ));
        }
    }
}
