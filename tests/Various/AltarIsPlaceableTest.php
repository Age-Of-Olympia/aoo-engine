<?php

namespace Tests\Various;

use App\Service\BuildingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * An altar can be placed as an entity.
 *
 * It is the whole point of the catalogue row: without it `place()` answers
 * "Type inconnu" and nothing downstream in the altars work is possible.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class AltarIsPlaceableTest extends TestCase
{
    private const PLAN = 'plan_test_autel_pose';

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
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->cleanup();
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
    }

    public function testTheAltarIsInTheCatalogue(): void
    {
        $race = $this->conn->fetchAssociative(
            'SELECT kind, structure_nature, pv, blocks_passage, playable FROM races WHERE name = ?',
            ['altar']
        );

        $this->assertNotFalse($race, 'le type doit exister, et par son NOM');
        $this->assertSame('structure', $race['kind']);
        $this->assertSame(25, (int) $race['pv']);
        $this->assertSame(1, (int) $race['blocks_passage'], 'un autel barre le chemin');
        $this->assertSame(0, (int) $race['playable']);
    }

    /** Placed bare: a god comes later, by consecration. */
    public function testAnAltarIsPlacedAsANakedEntity(): void
    {
        $id = (new BuildingService())->place(
            'altar',
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN],
            null,
            '',
            null,
            overScenery: true
        );

        $this->assertGreaterThan(0, $id);

        $row = $this->conn->fetchAssociative(
            'SELECT race, player_type, godId FROM players WHERE id = ?',
            [$id]
        );

        $this->assertSame('altar', $row['race']);
        $this->assertSame('building', $row['player_type']);
        $this->assertSame(0, (int) $row['godId'], 'un autel naît sans Dieu');

        $this->assertSame(
            1,
            (int) $this->conn->fetchOne('SELECT COUNT(*) FROM entity_cells WHERE player_id = ?', [$id]),
            'il tient sa case'
        );
        $this->assertSame(
            1,
            (int) $this->conn->fetchOne('SELECT COUNT(*) FROM buildings WHERE player_id = ?', [$id]),
            'et sa ligne satellite'
        );
    }
}
