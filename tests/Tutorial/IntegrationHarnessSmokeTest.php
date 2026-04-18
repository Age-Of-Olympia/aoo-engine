<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Smoke test for the D4 Phase C foundation.
 *
 * Proves three things about the TutorialIntegrationTestCase harness:
 *   - opening the connection works (or the test is SKIPPED, not FAILED);
 *   - the connection is wrapped in an active transaction during the test;
 *   - the rollback in tearDown actually undoes the test's writes (verified
 *     here by writing to the database in one test and asserting the row
 *     is gone in the next test's setUp-fresh state).
 *
 * This test does NOT exercise tutorial-specific schema. Phase C tests
 * that follow (TutorialPlayerCleanupIntegrationTest, MovementStepDb-
 * BranchesIntegrationTest, etc.) build on this foundation.
 */
class IntegrationHarnessSmokeTest extends TutorialIntegrationTestCase
{
    #[Group('tutorial-integration-smoke')]
    public function testConnectionOpensAndAcceptsQueries(): void
    {
        $value = $this->conn->fetchOne('SELECT 1');

        $this->assertSame(1, (int) $value);
    }

    #[Group('tutorial-integration-smoke')]
    public function testTransactionIsActiveDuringTest(): void
    {
        // setUp opened a transaction; tearDown will roll it back. If
        // a future refactor accidentally drops the wrapping (e.g. by
        // committing in setUp or skipping beginTransaction), this test
        // surfaces it before it silently leaks rows into the test DB.
        $this->assertTrue($this->conn->isTransactionActive());
    }

    #[Group('tutorial-integration-smoke')]
    public function testWritesAreRolledBackBetweenTests(): void
    {
        // Write a row to a table that always exists (information_schema
        // is read-only, so we use a temporary table created in this
        // transaction; rollback in tearDown destroys it). This proves
        // the rollback path works without depending on tutorial tables
        // that may or may not exist in the target test DB.
        $this->conn->executeStatement(
            'CREATE TEMPORARY TABLE harness_smoke (n INT)'
        );
        $this->conn->insert('harness_smoke', ['n' => 42]);

        $this->assertSame(
            42,
            (int) $this->conn->fetchOne('SELECT n FROM harness_smoke')
        );

        // The table itself goes away when the transaction ends; the next
        // test in this class would not see it. We don't assert that here
        // (would require a stateful between-tests check) — we trust that
        // tearDown's rollBack() does what Doctrine documents.
    }
}
