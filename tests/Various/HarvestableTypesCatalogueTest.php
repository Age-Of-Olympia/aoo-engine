<?php

namespace Tests\Various;

use App\Service\Map\StructureTypeService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * What a harvestable type is, now that one catalogue says it.
 *
 * There were two. `resource_types` held a name and a number whose SIGN carried
 * the meaning — -1 harvestable, positive a life total, absent indestructible —
 * while `races` held the same types with a nature saying it in words. A type
 * made harvestable in the type editor stayed an obstacle to the map editor,
 * which read the other table.
 *
 * These tests hold the surviving catalogue to what the map editor, the import
 * paths and the harvest need from it.
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

        StructureTypeService::forget();
    }

    protected function tearDown(): void
    {
        StructureTypeService::forget();
    }

    /** The legacy catalogue is gone: nothing may read it back into being. */
    public function testTheLegacyCatalogueIsGone(): void
    {
        $this->assertSame(
            [],
            $this->conn->fetchFirstColumn("SHOW TABLES LIKE 'resource_types'"),
            'deux catalogues pour une seule vérité, c\'est un de trop'
        );
    }

    /** Harvestable types follow the convention this chantier settled on. */
    public function testHarvestableTypesFollowTheEstablishedConvention(): void
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT name, kind, structure_nature, pv, blocks_passage, playable
               FROM races WHERE kind = 'structure' AND structure_nature = 'ressource'"
        );

        $this->assertNotEmpty($rows, 'un monde sans type récoltable ne se fouille pas');

        foreach ($rows as $row) {
            $this->assertSame(1, (int) $row['blocks_passage'], $row['name'] . ' barre le chemin');
            $this->assertSame(0, (int) $row['playable'], $row['name']);
            /* Abattable depuis l'arbitrage : dix tombait en un coup ou deux,
               `melee` et `distance` visant déjà une structure. */
            $this->assertGreaterThan(1, (int) $row['pv'], $row['name'] . ' tomberait à un souffle');
        }
    }

    /** The gateway answers on the nature, which is what the editors ask. */
    public function testTheGatewayReadsTheSurvivingCatalogue(): void
    {
        $harvestable = (string) $this->conn->fetchOne(
            "SELECT name FROM races WHERE kind = 'structure' AND structure_nature = 'ressource' ORDER BY name LIMIT 1"
        );

        $this->assertNotSame('', $harvestable);
        $this->assertTrue(StructureTypeService::isHarvestable($harvestable), $harvestable);
        $this->assertTrue(StructureTypeService::isKnown($harvestable), $harvestable);
        $this->assertGreaterThan(0, (int) StructureTypeService::pv($harvestable), $harvestable);

        $this->assertFalse(StructureTypeService::isKnown('gm_type_qui_n_existe_pas'));
        $this->assertNull(StructureTypeService::pv('gm_type_qui_n_existe_pas'));
        $this->assertFalse(StructureTypeService::isHarvestable('gm_type_qui_n_existe_pas'));
    }
}
