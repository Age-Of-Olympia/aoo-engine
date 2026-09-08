<?php

namespace Tests\Various;

use App\Entity\Resource;
use App\Entity\Structure;
use App\Enum\EntityCategory;
use App\Factory\PlayerFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

/**
 * The `resource` entity type answers everywhere, before any row wears it.
 *
 * Adding a player type touches more maps than it looks: the discriminator, the
 * category enum, the id ranges, the default cell role. `scenery` reached the
 * table before it reached two of them — the category enum threw, and lookups
 * through the entity root returned null for a decor. This test is that lesson,
 * paid once.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class ResourceIsAnEntityTypeTest extends TestCase
{
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_resource_entity';

    private ?Connection $conn = null;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();

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
        $this->purgePlan($this->conn, self::PLAN);
    }

    /** The type has a range of its own, next to the others. */
    public function testItHasItsOwnIdRange(): void
    {
        $this->assertArrayHasKey('resource', ENTITY_ID_RANGES);
        $this->assertSame(50000000, ENTITY_ID_RANGES['resource']['start']);

        foreach (['building', 'unique', 'scenery'] as $other) {
            $this->assertLessThan(
                ENTITY_ID_RANGES['resource']['start'],
                ENTITY_ID_RANGES[$other]['start'],
                'les plages ne doivent pas se chevaucher'
            );
        }
    }

    /** The enum that THREW on an unknown type knows it. */
    public function testItIsAStructureForTheCategoryEnum(): void
    {
        $this->assertSame(EntityCategory::Structure, EntityCategory::fromPlayerType('resource'));
    }

    /** And the entity root resolves it — the gap that bit twice. */
    public function testItResolvesThroughTheEntityRoot(): void
    {
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]
        );
        /* Haut de la plage : le bas est désormais occupé par les ressources
           converties, et une fixture ne doit pas squatter un identifiant réel. */
        $id = ENTITY_ID_RANGES['resource']['end'] - 1;

        $this->conn->executeStatement(
            "INSERT INTO players (id, name, race, coords_id, player_type)
             VALUES (?, 'GmArbre', 'arbre1', ?, 'resource')",
            [$id, $coordsId]
        );

        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
        $entity = PlayerFactory::gameEntity($id);

        $this->assertNotNull($entity, 'une ressource doit répondre comme toute entité');
        $this->assertInstanceOf(Resource::class, $entity);
        $this->assertInstanceOf(Structure::class, $entity);
        $this->assertFalse($entity->isRealPlayer());
    }
}
