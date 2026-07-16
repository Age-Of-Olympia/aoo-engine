<?php

namespace Tests\Various;

use App\Entity\Building;
use App\Entity\BuildingDetails;
use App\Entity\Character;
use App\Entity\GameEntity;
use App\Entity\Structure;
use App\Entity\UniqueObject;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Pins the Structure branch of the GameEntity STI
 * (docs/design-buildings-entities.md §4.3, roadmap step 2):
 *
 *  - hierarchy shape: Building / UniqueObject are Structures, are
 *    GameEntities, and are NOT Characters — TargetTypeCondition and the
 *    player-list isolation both stand on this;
 *  - discriminator map carries 'building' and 'unique';
 *  - type probes all answer false (a structure is never a player);
 *  - DB round trip: a players row with player_type='building' hydrates
 *    as Building through GameEntity/Structure lookups and is INVISIBLE
 *    to a Character-scoped lookup — the STI-level guarantee that
 *    character queries can't accidentally return buildings.
 */
#[Group('entities-structure')]
class StructureEntityTest extends TestCase
{
    public function testHierarchyShape(): void
    {
        foreach ([Building::class, UniqueObject::class] as $class) {
            $parents = class_parents($class);
            $this->assertContains(Structure::class, $parents, "$class must be a Structure");
            $this->assertContains(GameEntity::class, $parents, "$class must be a GameEntity");
            $this->assertNotContains(Character::class, $parents, "$class must NOT be a Character");
        }
    }

    public function testTypeProbesAllAnswerFalse(): void
    {
        foreach ([new Building(), new UniqueObject()] as $structure) {
            $this->assertFalse($structure->isRealPlayer());
            $this->assertFalse($structure->isTutorialPlayer());
            $this->assertFalse($structure->isNPC());
        }
    }

    public function testDiscriminatorMapCarriesTheStructureTypes(): void
    {
        $attrs = (new ReflectionClass(GameEntity::class))
            ->getAttributes(\Doctrine\ORM\Mapping\DiscriminatorMap::class);
        $this->assertCount(1, $attrs);

        $map = $attrs[0]->newInstance()->value;
        $this->assertSame(Building::class, $map['building'] ?? null);
        $this->assertSame(UniqueObject::class, $map['unique'] ?? null);
    }

    public function testBuildingRowHydratesAsBuildingAndStaysOutOfCharacterLookups(): void
    {
        $em = $this->bootstrapEmOrSkip();
        $conn = $em->getConnection();

        $id = null;
        try {
            $id = (int) getNextEntityId('building');
            $this->assertGreaterThanOrEqual(
                ENTITY_ID_RANGES['building']['start'],
                $id,
                'building ids must come from their reserved range'
            );

            $coordsId = (int) $conn->fetchOne("SELECT id FROM coords WHERE x = 0 AND y = 0 AND z = 0 AND plan = 'gaia' LIMIT 1");
            if ($coordsId === 0) {
                $this->markTestSkipped('No (0,0,gaia) coords row available.');
            }

            $conn->executeStatement(
                "INSERT INTO players (id, player_type, display_id, name, race, coords_id, nextTurnTime, registerTime)
                 VALUES (?, 'building', 1, 'Palissade GM', 'palissade', ?, 0, ?)",
                [$id, $coordsId, time()]
            );
            $conn->executeStatement(
                'INSERT INTO buildings (player_id, archetype, build_state) VALUES (?, ?, ?)',
                [$id, 'palissade', BuildingDetails::STATE_BUILT]
            );

            $asEntity = $em->find(GameEntity::class, $id);
            $this->assertInstanceOf(Building::class, $asEntity);
            $this->assertSame('palissade', $asEntity->getRace());

            $asStructure = $em->find(Structure::class, $id);
            $this->assertInstanceOf(Building::class, $asStructure);

            $this->assertNull(
                $em->find(Character::class, $id),
                'a building row must be invisible to Character-scoped lookups'
            );

            $details = $em->find(BuildingDetails::class, $id);
            $this->assertInstanceOf(BuildingDetails::class, $details);
            $this->assertSame('palissade', $details->getArchetype());
            $this->assertSame(BuildingDetails::STATE_BUILT, $details->getBuildState());
            $this->assertNull($details->getOwnerId());
        } finally {
            if ($id !== null) {
                $conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [$id]);
                $conn->executeStatement('DELETE FROM players WHERE id = ?', [$id]);
            }
        }
    }

    /**
     * Boot the legacy stack + Doctrine EM against aoo4, or skip. Same
     * DB-gated pattern as PlayerEntityHydrationTest; additionally
     * requires the `buildings` table (Version20260716120000).
     */
    private function bootstrapEmOrSkip(): \Doctrine\ORM\EntityManagerInterface
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        $em = \App\Entity\EntityManagerFactory::getEntityManager();

        try {
            $em->getConnection()->executeQuery('SELECT 1 FROM buildings LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('buildings table unavailable (run migrations): ' . $e->getMessage());
        }

        return $em;
    }
}
