<?php

namespace Tests\Various;

use App\Migrations\Version20260729220000_AltarsBecomeEntities;
use App\Service\BuildingService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Altars becoming entities, on the shapes the real map holds.
 *
 * Measured on the production copy and reproduced here: a cell with duplicate
 * altar rows, a damaged plain altar with an owner, a broken one, a trigger
 * naming a god with no resource under it, and a trigger naming something that
 * is not a god.
 *
 * The migration is run directly rather than through the CLI: it is the only
 * way to assert on what a data migration produces.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class AltarsBecomeEntitiesTest extends TestCase
{
    private const PLAN = 'plan_test_autels_bascule';
    private const GOD_ID = -990501;
    private const BEAST_ID = -990502;
    private const OWNER_ID = 990503;

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
            $this->conn->executeStatement('DELETE FROM map_walls_archive WHERE entity_id = ?', [(int) $id]);
            BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        foreach (['map_resources', 'map_triggers'] as $table) {
            $this->conn->executeStatement(
                "DELETE m FROM {$table} m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?",
                [self::PLAN]
            );
        }

        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);

        foreach ([self::GOD_ID, self::BEAST_ID, self::OWNER_ID] as $id) {
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
        foreach ([
            [self::GOD_ID, 'GmDieu', 'dieu', 'npc'],
            [self::BEAST_ID, 'GmBete', 'animal', 'npc'],
            [self::OWNER_ID, 'GmBatisseur', 'nain', 'real'],
        ] as [$id, $name, $race, $type]) {
            $this->conn->executeStatement(
                'INSERT INTO players (id, name, race, coords_id, player_type) VALUES (?, ?, ?, ?, ?)',
                [$id, $name, $race, $this->coordsId(9, 9), $type]
            );
        }

        $altar = function (int $x, int $y, string $name, int $damages, ?int $owner): void {
            $this->conn->executeStatement(
                'INSERT INTO map_resources (coords_id, name, damages, player_id) VALUES (?, ?, ?, ?)',
                [$this->coordsId($x, $y), $name, $damages, $owner]
            );
        };

        $trigger = function (int $x, int $y, int $params): void {
            $this->conn->executeStatement(
                'INSERT INTO map_triggers (coords_id, name, params) VALUES (?, "altar", ?)',
                [$this->coordsId($x, $y), (string) $params]
            );
        };

        // (0,0) consecrated altar
        $altar(0, 0, 'altar', 0, null);
        $trigger(0, 0, self::GOD_ID);

        // (1,0) the same cell carrying TWO altar rows — four such cells exist
        $altar(1, 0, 'altar', 0, null);
        $altar(1, 0, 'altar', 0, null);

        // (2,0) a PLAIN altar carrying damage, with a builder
        $altar(2, 0, 'altar', 20, self::OWNER_ID);

        // (3,0) a broken one
        $altar(3, 0, 'altar_broken', 0, null);

        // (4,0) a god with no altar under it — Thétis's case
        $trigger(4, 0, self::GOD_ID);

        // (5,0) a trigger naming something that is not a god
        $trigger(5, 0, self::BEAST_ID);
    }

    private function convert(): void
    {
        (new Version20260729220000_AltarsBecomeEntities($this->conn, new NullLogger()))->up(new Schema());
    }

    /** @return array<string, mixed>|false */
    private function altarAt(int $x, int $y)
    {
        return $this->conn->fetchAssociative(
            "SELECT p.id, p.name, p.race, p.godId, p.avatar, p.owner_id,
                    (SELECT COALESCE(SUM(n), 0) FROM players_bonus pb WHERE pb.player_id = p.id AND pb.name = 'pv') AS wound,
                    (SELECT COUNT(*) FROM entity_cells ec WHERE ec.player_id = p.id) AS cells
               FROM players p
               JOIN buildings b ON b.player_id = p.id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ? AND c.x = ? AND c.y = ? AND p.race = 'altar'",
            [self::PLAN, $x, $y]
        );
    }

    public function testAConsecratedAltarKeepsItsGodAndSaysSo(): void
    {
        $this->convert();

        $altar = $this->altarAt(0, 0);

        $this->assertNotFalse($altar);
        $this->assertSame(self::GOD_ID, (int) $altar['godId']);
        $this->assertSame('Autel de GmDieu', $altar['name'], 'l\'autel dit de qui il est');
        $this->assertSame(1, (int) $altar['cells']);
    }

    /** Four cells really do carry two altar rows: one entity, not two. */
    public function testACellWithTwoAltarRowsGivesOneEntity(): void
    {
        $this->convert();

        $this->assertSame(
            1,
            (int) $this->conn->fetchOne(
                "SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id
                  WHERE c.plan = ? AND c.x = 1 AND c.y = 0 AND p.race = 'altar'",
                [self::PLAN]
            )
        );
    }

    /**
     * A wound is read for every altar, not only the broken ones — a plain
     * altar carries damage on the real map, and its builder with it.
     */
    public function testAPlainAltarKeepsItsWoundAndItsBuilder(): void
    {
        $this->convert();

        $altar = $this->altarAt(2, 0);

        $this->assertSame(-20, (int) $altar['wound']);
        $this->assertSame(self::OWNER_ID, (int) $altar['owner_id']);
    }

    /** Broken is a state, not a type: a damaged altar keeping its sprite. */
    public function testABrokenAltarBecomesADamagedAltar(): void
    {
        $this->convert();

        $altar = $this->altarAt(3, 0);

        $this->assertSame('altar', $altar['race'], 'pas de type à part');
        $this->assertStringContainsString('altar_broken', (string) $altar['avatar'], 'il garde son image');
        $this->assertLessThanOrEqual(-13, (int) $altar['wound'], 'blessé au moins de moitié');
    }

    /** A god with nothing under it is still an altar — Thétis's case. */
    public function testAGodWithoutAResourceStillBecomesAnAltar(): void
    {
        $this->convert();

        $altar = $this->altarAt(4, 0);

        $this->assertNotFalse($altar, 'le seul autel d\'un dieu classé ne doit pas être oublié');
        $this->assertSame(self::GOD_ID, (int) $altar['godId']);
    }

    /** A trigger naming something that is not a god names nothing. */
    public function testATriggerNamingNoGodMakesNoAltar(): void
    {
        $this->convert();

        $this->assertFalse($this->altarAt(5, 0));
    }

    /** The rows are archived before they go, so the way back is a re-insert. */
    public function testTheResourceRowsAreArchivedThenRemoved(): void
    {
        $this->convert();

        $left = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM map_resources r JOIN coords c ON c.id = r.coords_id
              WHERE c.plan = ? AND r.name LIKE 'altar%'",
            [self::PLAN]
        );

        $this->assertSame(0, $left, 'la troisième adresse se ferme');

        $archived = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM map_walls_archive a
               JOIN players p ON p.id = a.entity_id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ?",
            [self::PLAN]
        );

        $this->assertSame(5, $archived, 'les cinq lignes de ressource, doublons compris');
    }
}
