<?php

namespace Tests\Various;

use App\Entity\BuildingType;
use App\Entity\CharacterRace;
use App\Entity\HarvestableType;
use App\Entity\Race;
use App\Entity\SceneryType;
use App\Service\RaceService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Le tronc et ses déclinaisons : un type chargé SAIT ce qu'il est.
 *
 * `races` portait cinq populations dans une seule classe, et le code devait
 * redemander à chaque fois « de quelle sorte parle-t-on ? ». Depuis le
 * discriminant, la question a une réponse portée par l'objet.
 *
 * Ces cas épinglent les deux bouts de la chaîne : ce que la base contient
 * détermine la classe construite, et la seule dérivation restante
 * ({@see Race::ofFamily()}) construit bien celle qu'elle annonce.
 *
 * DB-backed ; skip propre quand la base est injoignable.
 */
class TypeInheritanceTest extends TestCase
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
            $this->conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, class-string<Race>>
     */
    private static function classPerFamily(): array
    {
        return [
            Race::FAMILY_CHARACTER => CharacterRace::class,
            Race::FAMILY_BUILDING => BuildingType::class,
            Race::FAMILY_SCENERY => SceneryType::class,
            Race::FAMILY_RESOURCE => HarvestableType::class,
        ];
    }

    /** Chaque ligne devient la classe que son discriminant annonce. */
    public function testEveryTypeLoadsAsItsFamilysClass(): void
    {
        $expected = [];
        foreach ($this->conn->fetchAllAssociative('SELECT name, type_kind FROM races') as $row) {
            $expected[(string) $row['name']] = (string) $row['type_kind'];
        }

        $this->assertNotSame([], $expected, 'catalogue vide : rien à vérifier');

        $classes = self::classPerFamily();
        $seen = [];

        foreach ((new RaceService())->getAllRaces() as $race) {
            $family = $expected[$race->getName()] ?? null;
            $seen[$family] = true;

            $this->assertInstanceOf(
                $classes[$family] ?? Race::class,
                $race,
                'le type « ' . $race->getName() .' » devrait être un ' . ($classes[$family] ?? '?')
            );

            $this->assertSame(
                $family,
                $race->familyKey(),
                'et sa classe doit dire la même famille que la base'
            );
        }

        $this->assertGreaterThan(1, count($seen), 'le catalogue doit couvrir plusieurs familles');
    }

    /** La seule dérivation restante construit ce qu'elle annonce. */
    public function testTheDerivationBuildsTheAnnouncedClass(): void
    {
        $cases = [
            ['character', 'edifice', CharacterRace::class, Race::FAMILY_CHARACTER],
            ['character', 'ressource', CharacterRace::class, Race::FAMILY_CHARACTER],
            ['structure', 'edifice', BuildingType::class, Race::FAMILY_BUILDING],
            ['structure', 'obstacle', BuildingType::class, Race::FAMILY_BUILDING],
            ['structure', 'decor', SceneryType::class, Race::FAMILY_SCENERY],
            ['structure', 'ressource', HarvestableType::class, Race::FAMILY_RESOURCE],
        ];

        foreach ($cases as [$kind, $nature, $class, $family]) {
            $type = Race::ofFamily($kind, $nature);

            $this->assertInstanceOf($class, $type, "{$kind}/{$nature}");
            $this->assertSame($family, $type->familyKey(), "{$kind}/{$nature}");
        }
    }

    /**
     * Un personnage se moque de `structure_nature`, et c'est le sujet.
     *
     * Seize races portent `edifice` sans être des bâtiments : la colonne ne
     * veut rien dire pour elles. La famille, elle, ne s'y trompe pas.
     */
    public function testACharacterIgnoresTheStructureNature(): void
    {
        foreach (['edifice', 'obstacle', 'decor', 'ressource'] as $nature) {
            $this->assertInstanceOf(CharacterRace::class, Race::ofFamily('character', $nature));
        }
    }
}
