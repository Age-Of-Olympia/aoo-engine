<?php

namespace Tests\Various;

use App\Entity\EntityManagerFactory;
use App\Entity\GameEntity;
use PHPUnit\Framework\TestCase;

/**
 * Tout `player_type` en base est mappé par l'arbre Doctrine.
 *
 * Le pendant de {@see EntityCategoryCoversDiscriminatorsTest}, pour la faute
 * SŒUR — et plus sournoise. Un discriminant absent de la DiscriminatorMap ne
 * lève pas : le chargement rend `null`. L'entité existe, la requête réussit, et
 * elle n'est simplement PAS LÀ pour tout ce qui passe par la racine.
 *
 * Le docblock de {@see \App\Entity\Resource} le raconte déjà pour `scenery`.
 * `plant` puis `item` ont refait exactement la même chose : les lignes sont
 * arrivées dans la table avant d'arriver dans la carte.
 *
 * Il interroge la BASE plutôt qu'une liste écrite à la main — une liste aurait
 * été oubliée exactement comme la carte l'a été.
 */
class GameEntityMapsEveryDiscriminatorTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            EntityManagerFactory::getEntityManager()->getConnection()->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }
    }

    public function testEveryDiscriminatorInUseIsMapped(): void
    {
        $em = EntityManagerFactory::getEntityManager();
        $mapped = $em->getClassMetadata(GameEntity::class)->discriminatorMap;

        $inUse = $em->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT player_type FROM players WHERE player_type <> ''"
        );
        if ($inUse === []) {
            /* Cette garde INTERROGE la base : sans entités, elle n'a rien à
             * dire. Sur la base jetable de la suite — des catalogues et rien
             * d'autre — elle passe donc son tour, et garde tout son sens là où
             * un monde existe. */
            $this->markTestSkipped('aucune entité en base : rien à confronter à la carte.');
        }

        $missing = array_values(array_diff(
            array_map('strval', $inUse),
            array_map('strval', array_keys($mapped))
        ));

        $this->assertSame(
            [],
            $missing,
            "Ces types existent en base mais l'arbre Doctrine les ignore : toute\n"
            . "lecture par la racine les rendra `null`, sans erreur.\n"
            . 'Étendre la DiscriminatorMap de GameEntity.'
        );
    }

    /**
     * Une entité NULLE PART se charge.
     *
     * `coords_id` est devenu nullable quand les limbes ont fermé ; le mapping
     * ne l'a pas suivi, et charger un bâtiment remisé levait un TypeError sur
     * une propriété `int`.
     */
    public function testAnEntityThatIsNowhereCanBeLoaded(): void
    {
        $em = EntityManagerFactory::getEntityManager();

        $nowhere = $em->getConnection()->fetchOne(
            'SELECT id FROM players WHERE coords_id IS NULL LIMIT 1'
        );
        if ($nowhere === false || $nowhere === null) {
            $this->markTestSkipped('aucune entité nulle part sur cette base.');
        }

        $entity = $em->find(GameEntity::class, (int) $nowhere);

        $this->assertNotNull($entity, 'une entité remisée reste chargeable');
        $this->assertNull($entity->getCoordsIdOrNull(), 'et elle assume de n être nulle part');
        $this->assertSame(0, $entity->getCoordsId(), 'zéro pour qui ne demande que « où »');
    }
}
