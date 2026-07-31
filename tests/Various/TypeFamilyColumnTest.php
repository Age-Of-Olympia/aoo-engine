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

    /**
     * Un type créé APRÈS la migration porte sa famille tout seul.
     *
     * C'est le cas que la CI a levé et que la machine de dev ne pouvait pas
     * voir : une suite complète insère des races en cours de route, sans rien
     * savoir de cette colonne. Avec un simple `UPDATE` au moment de la
     * migration, ces lignes-là naissaient sans famille — et `RaceImporter`,
     * `RaceSeedService` et l'écran d'admin sont dans le même cas en
     * production. La colonne est donc GÉNÉRÉE : l'écrivain n'a rien à savoir.
     */
    public function testARaceInsertedAfterwardsStillHasItsFamily(): void
    {
        $name = 'gm_famille_' . bin2hex(random_bytes(4));

        try {
            /* Volontairement sans mentionner type_kind : c'est le geste des
             * écrivains existants. */
            $this->conn->executeStatement(
                "INSERT INTO races (code, name, label, description, playable, hidden, kind,
                                    structure_nature, bleeds, wound_color, blocks_passage,
                                    blocks_projectiles, bgColor, color, faction, plan, pv)
                 VALUES ('GM_FAM', ?, 'Gm famille', '', 0, 1, 'structure', 'ressource',
                         '', '#cd7f32', 1, 1, '#8a8a8a', 'black', '', '', 10)",
                [$name]
            );

            $this->assertSame(
                TypeEditorFace::RESOURCE,
                $this->conn->fetchOne('SELECT type_kind FROM races WHERE name = ?', [$name]),
                'une race insérée sans connaître la colonne doit tout de même porter sa famille'
            );
        } finally {
            $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [$name]);
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
