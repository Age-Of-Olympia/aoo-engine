<?php

namespace Tests\Various;

use App\Service\BuildingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

/**
 * Controlled plans: a god holds a plan when they are the ONLY one with a
 * consecrated altar on it.
 *
 * The rule comes from the altars work; what it rests on is that a NAKED altar
 * is neutral. Counted as a holder, it reads as a rival and takes the plan away
 * from the god who actually holds it — measured on the production copy, that
 * removed Héphaïstos from the board entirely and cut Fefnir from three plans
 * to one.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class PlansControlesTest extends TestCase
{
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN_A = 'plan_test_ctrl_a';
    private const PLAN_B = 'plan_test_ctrl_b';
    private const GOD_ONE = -990701;
    private const GOD_TWO = -990702;

    private ?Connection $conn = null;
    private int $nextAltar = 990710;

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

    /** @return array<int, int> god id => plans controlled */
    private function controlled(): array
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT a.godId, COUNT(DISTINCT ec.plan) AS total_plans
               FROM players AS a
               JOIN entity_cells AS ec ON ec.player_id = a.id
              WHERE a.race = "altar" AND a.godId != 0
                AND ec.plan IN (?, ?)
                AND ec.plan IN (
                    SELECT ec2.plan
                      FROM players AS a2
                      JOIN entity_cells AS ec2 ON ec2.player_id = a2.id
                     WHERE a2.race = "altar" AND a2.godId != 0
                     GROUP BY ec2.plan
                    HAVING COUNT(DISTINCT a2.godId) = 1
                )
              GROUP BY a.godId',
            [self::PLAN_A, self::PLAN_B]
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['godId']] = (int) $row['total_plans'];
        }

        return $out;
    }

    public function testAloneOnAPlanIsControl(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_A, 1, self::GOD_ONE);

        $this->assertSame([self::GOD_ONE => 1], $this->controlled(), 'deux autels, un seul plan');
    }

    /** Two gods on one plan: nobody holds it. */
    public function testAContestedPlanIsHeldByNobody(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_A, 1, self::GOD_TWO);

        $this->assertSame([], $this->controlled());
    }

    /**
     * The arbitration, and the reason the rule is written twice: a naked
     * altar beside a consecrated one must not cost the god their plan.
     */
    public function testANakedAltarTakesNoPlanAway(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_A, 1, 0);

        $this->assertSame([self::GOD_ONE => 1], $this->controlled(), 'l\'autel nu est neutre');
    }

    /** And it gives nobody anything either. */
    public function testANakedAltarAloneControlsNothing(): void
    {
        $this->altarOn(self::PLAN_B, 0, 0);

        $this->assertSame([], $this->controlled());
    }

    public function testPlansAreCountedNotAltars(): void
    {
        $this->altarOn(self::PLAN_A, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_B, 0, self::GOD_ONE);
        $this->altarOn(self::PLAN_B, 1, self::GOD_ONE);

        $this->assertSame([self::GOD_ONE => 2], $this->controlled());
    }
}
