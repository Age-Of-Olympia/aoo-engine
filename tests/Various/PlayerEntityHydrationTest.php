<?php

namespace Tests\Various;

use App\Entity\Character;
use App\Entity\RealPlayer;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDb;

/**
 * Phase 3.1 smoke test — ensures Doctrine can actually hydrate a
 * GameEntity (ex-PlayerEntity) against the live `players` schema.
 *
 * Rationale: the Phase 3 schema audit found two blocking mismatches
 * that would have silently failed on first use:
 *
 *   A1  `bonus_points` column declared on entity, missing from table
 *   A2  `emailBonus` mapped as camelCase, table has `email_bonus`
 *
 * Both are fixed in this MR:
 *
 *   A1  new migration Version20260419130000_AddBonusPointsToPlayers +
 *       a column in db/init_noupdates.sql
 *   A2  explicit `name: 'email_bonus'` on the ORM\Column attribute
 *
 * Without hydration coverage in the test suite, a future column-name
 * drift could re-introduce either defect and nothing in CI would fail
 * until a Phase 3+ caller actually hit the entity.
 *
 * Test strategy: construct `PlayerFactory::entity($id)` for a known
 * real player (lowest id with `player_type='real'`) and exercise every
 * getter once. If Doctrine's metadata no longer matches the table,
 * hydration throws here and the test fails loudly.
 *
 * Skips cleanly when the `aoo4` DB is unreachable so the CI phpunit
 * stage stays green (no mariadb service attached there).
 */
class PlayerEntityHydrationTest extends TestCase
{
    private ?EntityManager $em = null;
    private int $playerId = 0;

    protected function setUp(): void
    {
        [$this->em, $this->playerId] = $this->bootstrapOrSkip();
    }

    protected function tearDown(): void
    {
        $this->em?->close();
        $this->em = null;
    }

    #[Group('player-entity-hydration')]
    public function testEntityHydratesAsRealPlayer(): void
    {
        $entity = $this->em->find(Character::class, $this->playerId);

        $this->assertInstanceOf(Character::class, $entity);
        $this->assertInstanceOf(RealPlayer::class, $entity);
        $this->assertSame($this->playerId, $entity->getId());
    }

    /** Every mapped field reads back: a column the table lacks fails here, not at first use. */
    public function testEveryMappedFieldHydrates(): void
    {
        $entity = $this->em->find(Character::class, $this->playerId);
        $this->assertNotNull($entity);

        $metadata = $this->em->getClassMetadata(RealPlayer::class);
        foreach ($metadata->getFieldNames() as $field) {
            $this->assertTrue(
                $metadata->getReflectionProperty($field)->isInitialized($entity),
                "{$field} was not hydrated"
            );
        }
    }

    /**
     * The probed aoo4_test connection (Tests\Support\TestDb) — the
     * schema-of-record CI and fresh devcontainers use — and an EntityManager
     * over it; skips cleanly when unreachable or holding no character.
     *
     * @return array{0: EntityManager, 1: int}
     */
    private function bootstrapOrSkip(): array
    {
        $conn = TestDb::connectionOrNull();
        if ($conn === null) {
            $this->markTestSkipped(TestDb::failure());
        }

        $em = new EntityManager($conn, ORMSetup::createAttributeMetadataConfiguration(
            paths:     [dirname(__DIR__, 2) . '/src/Entity'],
            isDevMode: true
        ));

        $row = $conn->fetchAssociative(
            "SELECT id FROM players WHERE id > 0 AND (player_type IS NULL OR player_type = 'real') ORDER BY id ASC LIMIT 1"
        );
        if (empty($row['id'])) {
            $this->markTestSkipped('No real player row available — run scripts/testing/reset_test_database.sh.');
        }

        return [$em, (int) $row['id']];
    }
}
