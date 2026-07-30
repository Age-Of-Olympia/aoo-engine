<?php

namespace Tests\Various;

use App\Entity\Resource;
use App\Entity\Structure;
use App\Enum\EntityCategory;
use App\Factory\PlayerFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

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
    private const PLAN = 'plan_test_resource_entity';

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

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        try {
            $this->conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
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
        if ($this->conn === null) {
            return;
        }

        foreach ($this->conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            \App\Service\BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
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
        $id = ENTITY_ID_RANGES['resource']['start'];

        $this->conn->executeStatement(
            "INSERT INTO players (id, name, race, coords_id, player_type)
             VALUES (?, 'GmArbre', 'arbre1', ?, 'resource')",
            [$id, $coordsId]
        );

        \App\Entity\EntityManagerFactory::getEntityManager()->clear();
        $entity = PlayerFactory::gameEntity($id);

        $this->assertNotNull($entity, 'une ressource doit répondre comme toute entité');
        $this->assertInstanceOf(Resource::class, $entity);
        $this->assertInstanceOf(Structure::class, $entity);
        $this->assertFalse($entity->isRealPlayer());
    }
}
