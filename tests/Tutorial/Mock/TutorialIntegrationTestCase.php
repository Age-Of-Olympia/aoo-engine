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

    /** Per-test plan for seedTile() — keeps sown tiles collision-free. */
    private ?string $seedPlan = null;

    /** Next auto-allocated x for seedTile() calls without coordinates. */
    private int $nextSeedX = 0;

    protected function setUp(): void
    {
        $this->conn = $this->openTestDbOrSkip();
        $this->conn->beginTransaction();
        $this->seedPlan = null;
        $this->nextSeedX = 0;
    }

    protected function tearDown(): void
    {
        if ($this->conn !== null && $this->conn->isTransactionActive()) {
            $this->conn->rollBack();
        }
        $this->conn = null;
    }

    /**
     * Sow a tile this test owns and return its coords id.
     *
     * The database the suite runs against holds catalogs only — there is
     * no world to borrow a tile from, and `players.coords_id` carries a
     * foreign key since the entity refactor. Each test sows what it
     * needs; the rollback takes it away again.
     *
     * Without arguments every call yields a FRESH tile (auto-incremented
     * x on a per-test plan). Explicit coordinates are reused when already
     * sown, so two seeds can share a tile on purpose.
     */
    protected function seedTile(?int $x = null, int $y = 0, int $z = 0, ?string $plan = null): int
    {
        $x ??= $this->nextSeedX++;
        $plan ??= $this->seedPlan ??= 'tut_t_' . bin2hex(random_bytes(4));

        $existing = $this->conn->fetchOne(
            'SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?',
            [$x, $y, $z, $plan]
        );
        if ($existing !== false) {
            return (int) $existing;
        }

        $this->conn->insert('coords', ['x' => $x, 'y' => $y, 'z' => $z, 'plan' => $plan]);

        return (int) $this->conn->lastInsertId();
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
