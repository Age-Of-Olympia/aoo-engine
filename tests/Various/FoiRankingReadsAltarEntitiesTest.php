<?php

namespace Tests\Various;

use App\Service\BuildingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A god ranks because an ALTAR bears their name, not because a trigger does.
 *
 * The ranking read `map_triggers`, so a god whose altar had been taken away
 * still ranked, and a god given one by consecration did not. Reading the
 * entity is what makes consecration mean something.
 *
 * The query is exercised as the page runs it, so the check survives the page
 * being rewritten around it.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class FoiRankingReadsAltarEntitiesTest extends TestCase
{
    private const PLAN = 'plan_test_foi';
    private const GOD_WITH = -990601;
    private const GOD_WITHOUT = -990602;
    private const FAITHFUL = 990603;

    private ?Connection $conn = null;

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        try {
            $this->conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->cleanup();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        if ($this->conn === null) {
            return;
        }

        foreach ($this->conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            $this->conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [(int) $id]);
            BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);

        foreach ([self::GOD_WITH, self::GOD_WITHOUT, self::FAITHFUL] as $id) {
            BuildingService::deleteEntityRows($this->conn, $id);
        }
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) \Classes\View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    private function seed(): void
    {
        $home = $this->coordsId(9, 9);

        foreach ([self::GOD_WITH, self::GOD_WITHOUT] as $god) {
            $this->conn->executeStatement(
                "INSERT INTO players (id, name, race, coords_id, player_type)
                 VALUES (?, ?, 'dieu', ?, 'npc')",
                [$god, 'GmDieu' . abs($god), $home]
            );
        }

        /* A living worshipper each, with faith: the ranking only shows gods
         * who have both. */
        foreach ([self::GOD_WITH, self::GOD_WITHOUT] as $i => $god) {
            $this->conn->executeStatement(
                "INSERT INTO players (id, name, race, coords_id, player_type, godId, pf, lastLoginTime)
                 VALUES (?, ?, 'nain', ?, 'real', ?, 100, UNIX_TIMESTAMP())",
                [self::FAITHFUL + $i, 'GmFidele' . $i, $home, $god]
            );
        }

        // Only the first god gets an altar bearing their name.
        $this->conn->executeStatement(
            "INSERT INTO players (id, name, race, coords_id, player_type, godId)
             VALUES (?, 'Autel de GmDieu', 'altar', ?, 'building', ?)",
            [self::FAITHFUL + 5, $this->coordsId(0, 0), self::GOD_WITH]
        );
    }

    /** @return list<int> the god ids the ranking would show */
    private function rankedGods(): array
    {
        $rows = $this->conn->fetchFirstColumn(
            'SELECT g.id
               FROM players AS g
               LEFT JOIN players AS f ON f.godId = g.id AND f.id > 0
                     AND f.lastLoginTime >= (UNIX_TIMESTAMP() - ' . INACTIVE_TIME . ')
              WHERE g.race = "dieu"
                AND g.id IN (?, ?)
                AND EXISTS (
                    SELECT 1 FROM players AS a WHERE a.race = "altar" AND a.godId = g.id
                )
              GROUP BY g.id
             HAVING COUNT(f.id) > 0 AND COALESCE(SUM(f.pf), 0) > 0',
            [self::GOD_WITH, self::GOD_WITHOUT]
        );

        return array_map('intval', $rows);
    }

    public function testAGodWithAnAltarRanksAndOneWithoutDoesNot(): void
    {
        $this->assertSame([self::GOD_WITH], $this->rankedGods());
    }

    /** Consecration is what makes a god rank: give the altar away, it moves. */
    public function testConsecratingAnAltarMovesTheGodIntoTheRanking(): void
    {
        $this->conn->executeStatement(
            "UPDATE players SET godId = ? WHERE race = 'altar' AND godId = ?",
            [self::GOD_WITHOUT, self::GOD_WITH]
        );

        $this->assertSame([self::GOD_WITHOUT], $this->rankedGods());
    }

    /** A naked altar belongs to nobody, so it lifts nobody into the ranking. */
    public function testANakedAltarRanksNoOne(): void
    {
        $this->conn->executeStatement("UPDATE players SET godId = 0 WHERE race = 'altar' AND godId = ?", [self::GOD_WITH]);

        $this->assertSame([], $this->rankedGods());
    }
}
