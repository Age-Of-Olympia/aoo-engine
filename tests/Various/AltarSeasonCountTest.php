<?php

namespace Tests\Various;

use App\Service\AltarControlService;
use App\Service\BuildingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

/**
 * The shared altar counting (AltarControlService), restricted to a set of
 * plans: what the admin page, the ranking and the monthly reward all read.
 * PLAN_B plays "another season" simply by being left out of the plan list.
 */
class AltarSeasonCountTest extends TestCase
{
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN_A = 'plan_test_seas_a';
    private const PLAN_B = 'plan_test_seas_b';
    private const GOD_ONE = -990801;
    private const GOD_TWO = -990802;

    private ?Connection $conn = null;
    private int $nextAltar = 990810;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->cleanup();

        foreach ([self::GOD_ONE, self::GOD_TWO] as $god) {
            $this->conn->executeStatement(
                "INSERT INTO players (id, name, race, coords_id, player_type)
                 VALUES (?, ?, 'dieu', ?, 'npc')",
                [$god, 'GmDieu' . abs($god), $this->coordsIdOn(self::PLAN_A, 9, 9)]
            );
        }
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        foreach ([self::PLAN_A, self::PLAN_B] as $plan) {
            $this->purgePlan($this->conn, $plan);
        }

        foreach ([self::GOD_ONE, self::GOD_TWO] as $god) {
            BuildingService::deleteEntityRows($this->conn, $god);
        }
    }

    /** An altar on a plan, belonging to a god or to nobody. */
    private function altarOn(string $plan, int $x, int $godId): void
    {
        $id = $this->nextAltar++;
        $coordsId = $this->coordsIdOn($plan, $x, 0);

        $this->conn->executeStatement(
            "INSERT INTO players (id, name, race, coords_id, player_type, godId)
             VALUES (?, 'Autel', 'altar', ?, 'building', ?)",
            [$id, $coordsId, $godId]
        );

        $this->conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, 0, 0, 'block')",
            [$id, $coordsId, $plan, $x]
        );
    }

    private function service(): AltarControlService
    {
        return new AltarControlService();
    }

    public function testAltarsOutsideThePlanListAreNotCounted(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_B, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_B, 1, self::GOD_TWO);

        $this->assertSame([self::GOD_ONE => 1], $this->service()->altarCountsByGod([self::PLAN_A]));
        $this->assertSame([self::GOD_ONE => 1], $this->service()->controlledPlanCountsByGod([self::PLAN_A]));
    }

    public function testAContestedPlanIsHeldByNobody(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_A, 1, self::GOD_TWO);

        $plans = [self::PLAN_A, self::PLAN_B];
        $this->assertSame([], $this->service()->controlledPlanCountsByGod($plans));
        $this->assertEqualsCanonicalizing([self::GOD_ONE => 1, self::GOD_TWO => 1], $this->service()->altarCountsByGod($plans));
    }

    public function testANakedAltarIsNeutral(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_A, 1, 0);
        $this->altarOn(self::PLAN_B, 0, 0);

        $plans = [self::PLAN_A, self::PLAN_B];
        $this->assertSame([self::GOD_ONE => 1], $this->service()->controlledPlanCountsByGod($plans));
        $this->assertSame([self::GOD_ONE => 1], $this->service()->altarCountsByGod($plans));
    }

    public function testPlansAreCountedNotAltars(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_B, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_B, 1, self::GOD_ONE);

        $plans = [self::PLAN_A, self::PLAN_B];
        $this->assertSame([self::GOD_ONE => 2], $this->service()->controlledPlanCountsByGod($plans));
        $this->assertSame([self::GOD_ONE => 3], $this->service()->altarCountsByGod($plans));
    }

    public function testNoPlansMeansNothingCounted(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);

        $this->assertSame([], $this->service()->altarCountsByGod([]));
        $this->assertSame([], $this->service()->controlledPlanCountsByGod([]));
    }
}
