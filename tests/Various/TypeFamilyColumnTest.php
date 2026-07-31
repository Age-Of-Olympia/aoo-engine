<?php

namespace Tests\Various;

use App\Service\RaceService;
use App\View\Admin\TypeEditorFace;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * `races.type_kind` dit la même chose que le code, pour chaque ligne.
 *
 * La colonne sera le discriminant du tronc `EntityType`
 * (docs/design-entity-types-inheritance.md). Avant qu'une classe en dépende,
 * il faut qu'elle soit d'accord avec `TypeEditorFace::of()` — qui est ce que
 * le moteur calcule aujourd'hui à chaque affichage — et qu'aucune ligne ne
 * reste sans famille.
 *
 * Un désaccord ici, c'est une ligne qui changerait de classe le jour où le
 * discriminant sera lu : mieux vaut le voir maintenant.
 *
 * DB-backed ; skip propre quand la base est injoignable.
 */
class TypeFamilyColumnTest extends TestCase
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

        $column = $this->conn->fetchOne("SHOW COLUMNS FROM races LIKE 'type_kind'");

        if ($column === false) {
            $this->markTestSkipped('Migration TypesTellTheirFamily non appliquée.');
        }
    }

    /** Aucune ligne sans famille : le discriminant ne tolère pas de trou. */
    public function testEveryTypeHasAFamily(): void
    {
        $orphans = $this->conn->fetchFirstColumn(
            "SELECT name FROM races WHERE type_kind = '' OR type_kind IS NULL"
        );

        $this->assertSame([], $orphans, 'ces types n\'appartiennent à aucune famille');
    }

    /** Et la famille écrite est celle que le code calcule. */
    public function testTheColumnAgreesWithTheCode(): void
    {
        $written = [];
        foreach ($this->conn->fetchAllAssociative('SELECT name, type_kind FROM races') as $row) {
            $written[(string) $row['name']] = (string) $row['type_kind'];
        }

        $this->assertNotSame([], $written, 'catalogue vide : rien à vérifier');

        foreach ((new RaceService())->getAllRaces() as $race) {
            $expected = TypeEditorFace::of($race)->key;

            $this->assertSame(
                $expected,
                $written[$race->getName()] ?? null,
                'famille écrite ≠ famille calculée pour « ' . $race->getName() . ' »'
            );
        }
    }

    /** Les quatre familles, et rien d'autre. */
    public function testOnlyTheFourKnownFamiliesAreWritten(): void
    {
        $families = $this->conn->fetchFirstColumn('SELECT DISTINCT type_kind FROM races ORDER BY type_kind');

        $this->assertSame(
            [],
            array_diff($families, [
                TypeEditorFace::CHARACTER,
                TypeEditorFace::BUILDING,
                TypeEditorFace::SCENERY,
                TypeEditorFace::RESOURCE,
            ]),
            'une famille inconnue est apparue dans la colonne'
        );
    }
}
