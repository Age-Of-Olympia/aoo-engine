<?php

namespace Tests\Tutorial\Mock;

use App\Factory\EntityManagerFactory;
use App\Service\PlanService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A case that runs inside a transaction on the SUITE's own connection — the
 * one db() serves — so the legacy paths it drives (View::get_coords_id, the
 * teardown helpers) see its rows, and the rollback takes everything away.
 *
 * Unlike TutorialIntegrationTestCase this needs the seeded catalogues, hence
 * the suite database rather than aoo4_test.
 */
abstract class SuiteDbTransactionTestCase extends TestCase
{
    protected Connection $conn;

    private mixed $previousLink = null;

    protected function setUp(): void
    {
        try {
            $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('suite DB unreachable: ' . $e->getMessage());
        }

        $this->previousLink = $GLOBALS['link'] ?? null;
        $GLOBALS['link'] = $this->conn;

        $this->conn->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->conn) && $this->conn->isTransactionActive()) {
            $this->conn->rollBack();
        }
        $GLOBALS['link'] = $this->previousLink;

        // Read caches and the identity map survive the rollback.
        PlanService::forget();
        EntityManagerFactory::getEntityManager()->clear();
    }
}
