<?php

namespace Tests\Various;

use App\Enum\EntityCategory;
use PHPUnit\Framework\TestCase;

/**
 * Tout `player_type` POSÉ SUR LE PLATEAU se range dans une catégorie.
 *
 * `fromPlayerType()` lève à dessein sur un discriminant inconnu, pour qu'un
 * type ajouté sans étendre le mapping échoue bruyamment. Il a échoué
 * bruyamment — mais au fond d'une requête AJAX, où personne n'entend : la
 * conversion des plantes en entités a introduit `plant` sans toucher à
 * l'énumération, et cliquer une case fleurie rendait 500. Le panneau ne
 * bougeait pas, sans le moindre message.
 *
 * Le garde-fou ne servait donc qu'à celui qui lisait les journaux. Ce cas le
 * ramène là où il agit : la suite de tests. Il interroge la BASE plutôt qu'une
 * liste écrite à la main — une liste aurait été oubliée exactement comme le
 * mapping l'a été.
 */
class EntityCategoryCoversDiscriminatorsTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            \App\Entity\EntityManagerFactory::getEntityManager()->getConnection()->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }
    }

    public function testEveryDiscriminatorInUseMapsToACategory(): void
    {
        $inUse = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection()
            ->fetchFirstColumn('SELECT DISTINCT player_type FROM players');

        $this->assertNotEmpty($inUse, 'sans lignes, ce cas ne prouverait rien');

        $unmapped = [];

        foreach ($inUse as $type) {
            try {
                EntityCategory::fromPlayerType($type === null ? null : (string) $type);
            } catch (\ValueError $e) {
                $unmapped[] = (string) $type;
            }
        }

        $this->assertSame(
            [],
            $unmapped,
            'Ces types occupent des cases mais ne se rangent nulle part : '
            . "observe.php lèvera sur leur case.\n"
            . 'Étendre EntityCategory::fromPlayerType.'
        );
    }

    /** Une plante est posée, pas un interlocuteur : elle est une structure. */
    public function testAPlantIsAStructureAndNotASocialActor(): void
    {
        $plant = EntityCategory::fromPlayerType('plant');

        $this->assertTrue($plant->isStructure());
        $this->assertFalse($plant->isSocialActor(), 'on n envoie pas de missive à une fleur');
    }
}
