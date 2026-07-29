<?php

namespace Tests\Various;

use App\Service\ResourceTypeService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Harvestable resources have a structure type, and it changes nothing yet.
 *
 * The rows are what lets a resource become an entity later. Written from
 * `resource_types` where pv = -1, on the values this chantier already set for
 * `arbre7` and `glaise3`.
 *
 * The second test is the one that matters: pv 10 in the catalogue must NOT
 * make a resource destructible while it is still a `map_resources` row. What
 * refuses destruction is `resource_types`, and it is untouched.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class HarvestableTypesCatalogueTest extends TestCase
{
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

        try {
            $this->conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }
    }

    /** Every harvestable type has its catalogue row — none left behind. */
    public function testEveryHarvestableTypeHasAStructureRow(): void
    {
        $missing = $this->conn->fetchFirstColumn(
            'SELECT t.name
               FROM resource_types t
              WHERE t.pv = -1
                AND NOT EXISTS (
                    SELECT 1 FROM races r
                     WHERE r.name COLLATE utf8mb4_general_ci = t.name COLLATE utf8mb4_general_ci
                )'
        );

        $this->assertSame([], $missing, 'un type récoltable sans ligne de catalogue ne peut pas devenir une entité');
    }

    /** On the values this chantier already used, not on new ones. */
    public function testTheyFollowTheEstablishedConvention(): void
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT r.name, r.kind, r.structure_nature, r.pv, r.blocks_passage, r.playable
               FROM races r
               JOIN resource_types t
                 ON t.name COLLATE utf8mb4_general_ci = r.name COLLATE utf8mb4_general_ci
              WHERE t.pv = -1'
        );

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame('structure', $row['kind'], $row['name']);
            $this->assertSame('obstacle', $row['structure_nature'], $row['name']);
            $this->assertSame(10, (int) $row['pv'], $row['name'] . ' : la valeur de DEFAULT_PV');
            $this->assertSame(1, (int) $row['blocks_passage'], $row['name'] . ' barre le chemin');
            $this->assertSame(0, (int) $row['playable'], $row['name']);
        }
    }

    /**
     * INERT: a catalogue row must not make a resource destructible while it is
     * still a `map_resources` row. `destroy.php` reads `resource_types`, where
     * pv = -1 still means indestructible.
     */
    public function testTheCatalogueRowDoesNotMakeAResourceDestructible(): void
    {
        $harvestable = (string) $this->conn->fetchOne(
            'SELECT name FROM resource_types WHERE pv = -1 ORDER BY name LIMIT 1'
        );

        $this->assertNotSame('', $harvestable);
        $this->assertSame(
            -1,
            ResourceTypeService::pv($harvestable),
            $harvestable . ' doit rester indestructible tant qu\'il est une ligne de ressource'
        );
    }
}
