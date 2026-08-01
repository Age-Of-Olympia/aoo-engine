<?php

namespace Tests\Various;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * `map_resources` is empty, and stays empty.
 *
 * The table is the last thing holding a whole legacy tail: a destruction page,
 * an admin catalogue, a service, and a dead branch in the board's render. None
 * of it can go while a single row still stands there — so the emptiness itself
 * is what gets pinned, once, here.
 *
 * Sixty-two rows survived every conversion of this chantier. They did not
 * survive by accident: each was excluded by a filter that made sense at the
 * time, and nobody looked back. This test is that second look, kept.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class MapResourcesIsEmptyTest extends TestCase
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
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }
    }

    /** Nothing left in the table — the whole point of the sweep. */
    public function testNothingStandsInMapResourcesAnyMore(): void
    {
        $left = $this->conn->fetchAllAssociative(
            'SELECT name, COUNT(*) n FROM map_resources GROUP BY name ORDER BY n DESC'
        );

        $this->assertSame(
            [],
            $left,
            'une ligne restante retient la table, ses lecteurs et ses écrans : ' . json_encode($left)
        );
    }

    /**
     * The coconut palms agree: all three harvestable, none left saying one
     * thing on the board and another in the catalogue.
     */
    public function testTheCoconutPalmsAgreeWithTheirCatalogue(): void
    {
        $palms = $this->conn->fetchAllAssociative(
            "SELECT name, structure_nature, pv FROM races WHERE kind = 'structure' AND name LIKE 'cocotier%'"
        );

        if ($palms === []) {
            $this->markTestSkipped('Pas de cocotier dans ce catalogue.');
        }

        foreach ($palms as $palm) {
            $this->assertSame('ressource', $palm['structure_nature'], $palm['name']);
            $this->assertGreaterThan(
                1,
                (int) $palm['pv'],
                $palm['name'] . ' : 1 PV est le chiffre de l\'ère obstacle, un souffle l\'abat'
            );
        }
    }

    /**
     * Every resource entity stands on a cell. An entity without one is
     * invisible and steps through — it would be worse than the row it replaced.
     */
    public function testEveryResourceEntityStandsSomewhere(): void
    {
        $homeless = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM players p
              WHERE p.player_type = 'resource'
                AND NOT EXISTS (SELECT 1 FROM entity_cells ec WHERE ec.player_id = p.id)"
        );

        $this->assertSame(0, $homeless, 'une ressource sans case ne barre rien et ne se voit pas');
    }
}
