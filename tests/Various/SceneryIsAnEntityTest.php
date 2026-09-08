<?php

namespace Tests\Various;

use App\Entity\Scenery;
use App\Entity\Structure;
use App\Factory\PlayerFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

/**
 * A decor answers when looked up through the entity root.
 *
 * `scenery` was the sixth discriminator and never reached the map, so every
 * lookup through `GameEntity` returned NULL for a decor. The observation
 * card, which follows that path, answered « error target id » on a perfectly
 * ordinary anvil — and only when a second entity shared the cell, which is
 * why it went unseen.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class SceneryIsAnEntityTest extends TestCase
{
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_scenery_entity';

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

    private function sceneryOnTheBoard(): int
    {
        $coordsId = (int) \Classes\View::get_coords_id(
            $this->tile(0, 0, self::PLAN)
        );

        $this->conn->executeStatement(
            "INSERT INTO players (name, race, coords_id, player_type) VALUES (?, ?, ?, 'scenery')",
            ['GmDecor', 'gm_decor_type', $coordsId]
        );

        return (int) $this->conn->lastInsertId();
    }

    /** The lookup that the observation card makes. */
    public function testADecorIsFoundThroughTheEntityRoot(): void
    {
        $id = $this->sceneryOnTheBoard();

        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
        $entity = PlayerFactory::gameEntity($id);

        $this->assertNotNull($entity, 'un décor doit répondre comme toute entité');
        $this->assertInstanceOf(Scenery::class, $entity);
    }

    /** And it is a structure: immobile, not a character. */
    public function testADecorIsAStructure(): void
    {
        $id = $this->sceneryOnTheBoard();

        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
        $entity = PlayerFactory::gameEntity($id);

        $this->assertInstanceOf(Structure::class, $entity);
        $this->assertFalse($entity->isRealPlayer());
        $this->assertFalse($entity->isNPC());
    }
}
