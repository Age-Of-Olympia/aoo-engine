<?php

namespace Tests\Various;

use App\Service\Map\MapResourcesRetirement;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Le reste de chantier se montre, et s'efface tout seul.
 *
 * `map_resources` n'a plus ni lecteur ni écrivain ni ligne : elle attend son
 * dépôt. Un « à supprimer plus tard » posté dans une conversation se perd ;
 * celui-ci vit au tableau de bord tant que l'objet existe, et disparaît le
 * jour où il est déposé — sans qu'on ait à penser à retirer l'avertissement.
 *
 * DB-backed ; skip propre quand la base est injoignable.
 */
class MapResourcesRetirementTest extends TestCase
{
    private ?Connection $conn = null;

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
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

    /** Tant que la table est là, l'écran a quelque chose à dire. */
    public function testThePresenceIsReportedWhileTheTableStands(): void
    {
        $status = (new MapResourcesRetirement($this->conn))->status();

        $exists = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'map_resources'"
        ) > 0;

        $this->assertSame($exists, $status['present'], 'la présence doit refléter la base, pas une mémoire');
    }

    /** Vidée de ses lignes, elle est déposable — c'est l'état visé. */
    public function testAnEmptyTableIsDroppable(): void
    {
        $status = (new MapResourcesRetirement($this->conn))->status();

        if (!$status['present']) {
            $this->assertFalse($status['droppable'], 'déposée : plus rien à annoncer');
            $this->assertSame([], $status['blockers']);

            return;
        }

        $this->assertSame(0, $status['rows'], 'le chantier a vidé la table');
        $this->assertTrue($status['droppable']);
        $this->assertSame([], $status['blockers']);
    }

    /**
     * Une ligne qui reviendrait retient le dépôt, et le dit.
     *
     * Vide et déposable ne sont pas la même chose : ce cas garantit que
     * l'écran refuserait le dépôt si quoi que ce soit réécrivait ici.
     */
    public function testARemainingRowBlocksTheDrop(): void
    {
        $status = (new MapResourcesRetirement($this->conn))->status();

        if (!$status['present']) {
            $this->markTestSkipped('Table déjà déposée.');
        }

        $coordsId = (int) $this->conn->fetchOne('SELECT id FROM coords ORDER BY id LIMIT 1');

        if ($coordsId === 0) {
            $this->markTestSkipped('Aucune coordonnée pour porter la ligne de test.');
        }

        $this->conn->executeStatement(
            "INSERT INTO map_resources (coords_id, name, damages) VALUES (?, 'gm_retraite', -1)",
            [$coordsId]
        );

        try {
            $blocked = (new MapResourcesRetirement($this->conn))->status();

            $this->assertFalse($blocked['droppable'], 'une ligne restante retient le dépôt');
            $this->assertNotSame([], $blocked['blockers'], 'et l\'écran dit laquelle');
        } finally {
            $this->conn->executeStatement("DELETE FROM map_resources WHERE name = 'gm_retraite'");
        }
    }
}
