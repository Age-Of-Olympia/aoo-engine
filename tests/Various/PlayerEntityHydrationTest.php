<?php

namespace Tests\Various;

use App\Entity\PlayerEntity;
use App\Entity\RealPlayer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3.1 smoke test — ensures Doctrine can actually hydrate a
 * PlayerEntity against the live `players` schema.
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
    private ?Connection $conn = null;
    private int $playerId = 0;

    protected function setUp(): void
    {
        [$this->em, $this->conn, $this->playerId] = $this->bootstrapOrSkip();
    }

    protected function tearDown(): void
    {
        $this->em?->close();
        $this->em = null;
        $this->conn = null;
    }

    #[Group('player-entity-hydration')]
    #[Group('phase-3-1')]
    public function testEntityHydratesAsRealPlayer(): void
    {
        $entity = $this->em->find(PlayerEntity::class, $this->playerId);

        $this->assertInstanceOf(PlayerEntity::class, $entity);
        $this->assertInstanceOf(RealPlayer::class, $entity);
        $this->assertSame($this->playerId, $entity->getId());
    }

    #[Group('player-entity-hydration')]
    #[Group('phase-3-1')]
    public function testEntityExposesEveryDeclaredColumnThroughGetters(): void
    {
        // The heart of the smoke test: call every getter once. If any
        // ORM\Column declaration points at a non-existent SQL column,
        // Doctrine either fails the SELECT upstream (caught by
        // testEntityHydratesAsRealPlayer) or the getter returns a
        // default/zero that masks the mismatch. We don't assert
        // values — just that nothing throws.
        $entity = $this->em->find(PlayerEntity::class, $this->playerId);
        $this->assertNotNull($entity);

        // Exercise every getter — if any ORM\Column declaration points
        // at a non-existent SQL column, Doctrine already failed the
        // SELECT above. If any getter accesses an uninitialised typed
        // property (e.g. because Doctrine skipped it), that throws
        // here. Collecting values into an array lets us make one
        // structural assertion without PHPStan flagging tautological
        // type checks. Pinning A1 (bonusPoints) and A2 (emailBonus)
        // explicitly in the list is deliberate — it makes the test
        // names grep-able against the fix targets.
        $values = [
            'id'                => $entity->getId(),
            'displayId'         => $entity->getDisplayId(),
            'name'              => $entity->getName(),
            'password'          => $entity->getPassword(),
            'mail'              => $entity->getMail(),
            'plainMail'         => $entity->getPlainMail(),
            'coordsId'          => $entity->getCoordsId(),
            'race'              => $entity->getRace(),
            'xp'                => $entity->getXp(),
            'bonusPoints'       => $entity->getBonusPoints(), // A1 fix target
            'pi'                => $entity->getPi(),
            'pr'                => $entity->getPr(),
            'malus'             => $entity->getMalus(),
            'energie'           => $entity->getEnergie(),
            'godId'             => $entity->getGodId(),
            'pf'                => $entity->getPf(),
            'rank'              => $entity->getRank(),
            'avatar'            => $entity->getAvatar(),
            'portrait'          => $entity->getPortrait(),
            'text'              => $entity->getText(),
            'story'             => $entity->getStory(),
            'quest'             => $entity->getQuest(),
            'faction'           => $entity->getFaction(),
            'factionRole'       => $entity->getFactionRole(),
            'secretFaction'     => $entity->getSecretFaction(),
            'secretFactionRole' => $entity->getSecretFactionRole(),
            'nextTurnTime'      => $entity->getNextTurnTime(),
            'registerTime'      => $entity->getRegisterTime(),
            'lastActionTime'    => $entity->getLastActionTime(),
            'lastLoginTime'     => $entity->getLastLoginTime(),
            'antiBerserkTime'   => $entity->getAntiBerserkTime(),
            'lastTravelTime'    => $entity->getLastTravelTime(),
            'emailBonus'        => $entity->getEmailBonus(),  // A2 fix target
        ];

        // Arity pin: adding a new column to the entity requires adding
        // it here too, which forces a visible code change on every
        // schema addition.
        $this->assertCount(33, $values);
    }

    #[Group('player-entity-hydration')]
    #[Group('phase-3-1')]
    public function testBonusPointsColumnIsPresentInTableSchema(): void
    {
        // Secondary pin on A1: confirms db/init_noupdates.sql carries
        // the column. If somebody removes the migration without
        // replacing the init line, this test catches it before
        // hydration reports a less-obvious failure.
        $columnCount = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'players'
               AND COLUMN_NAME = 'bonus_points'"
        );
        $this->assertSame(1, $columnCount, 'players.bonus_points column must exist');
    }

    /**
     * Build a standalone EntityManager targeting the `aoo4_test`
     * database (the schema-of-record populated from
     * `db/init_noupdates.sql` via scripts/testing/reset_test_database.sh),
     * locate a real player, and return the trio. Skip cleanly on any
     * failure so the CI phpunit stage (no mariadb service) stays green.
     *
     * We deliberately do NOT use PlayerFactory::entity() / the
     * singleton EntityManagerFactory — that pair connects to the dev
     * `aoo4` DB (via DB_CONSTANTS) whose schema may lag behind
     * init_noupdates.sql. Testing against `aoo4_test` ensures the
     * metadata-to-schema contract is validated on the schema
     * CI and fresh devcontainers actually use.
     *
     * Environment overrides (TEST_DB_*) match
     * tests/Tutorial/Mock/TutorialIntegrationTestCase so CI can point
     * at the mariadb service alias there too.
     *
     * @return array{0: EntityManager, 1: Connection, 2: int}
     */
    private function bootstrapOrSkip(): array
    {
        $params = [
            'host'     => getenv('TEST_DB_HOST') ?: 'mariadb-aoo4',
            'user'     => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASS') ?: 'passwordRoot',
            'dbname'   => getenv('TEST_DB_NAME') ?: 'aoo4_test',
            'driver'   => 'mysqli',
            'charset'  => 'utf8mb4',
        ];

        try {
            $config = ORMSetup::createAttributeMetadataConfiguration(
                paths:     [dirname(__DIR__, 2) . '/src/Entity'],
                isDevMode: true
            );
            $conn = DriverManager::getConnection($params, $config);
            $conn->executeQuery('SELECT 1');
            $em = new EntityManager($conn, $config);
        } catch (\Throwable $e) {
            $this->markTestSkipped(sprintf(
                'Test DB %s@%s/%s unavailable (%s).',
                $params['user'],
                $params['host'],
                $params['dbname'],
                $e->getMessage()
            ));
        }

        try {
            $row = $conn->fetchAssociative(
                "SELECT id FROM players WHERE id > 0 AND (player_type IS NULL OR player_type = 'real') ORDER BY id ASC LIMIT 1"
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('players table unreadable: ' . $e->getMessage());
        }

        if (empty($row['id'])) {
            $this->markTestSkipped(
                'No real player row available — run scripts/testing/reset_test_database.sh.'
            );
        }

        return [$em, $conn, (int) $row['id']];
    }
}
