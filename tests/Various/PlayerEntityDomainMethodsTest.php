<?php

namespace Tests\Various;

use App\Entity\PlayerEntity;
use App\Entity\RealPlayer;
use App\Service\PlayerOptionsService;
use App\Service\PlayerService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3.2 — tests the three domain methods added to the
 * PlayerEntity / RealPlayer hierarchy so Phase 3.3's read-path SAR
 * can be mechanical:
 *
 *   PlayerEntity::hasOption(PlayerOptionsService, string)
 *   PlayerEntity::getCoordsPlan(Connection)
 *   RealPlayer::isInactive(PlayerService)
 *
 * Same DB-gated pattern as PlayerEntityHydrationTest: builds a
 * standalone EntityManager against aoo4_test and exercises the methods
 * end-to-end. Skips cleanly when the DB is unreachable.
 */
class PlayerEntityDomainMethodsTest extends TestCase
{
    private ?EntityManager $em = null;
    private ?Connection $conn = null;
    private int $playerId = 0;
    /** @var mixed original $GLOBALS['link'] before we overrode it */
    private $previousLink = null;

    protected function setUp(): void
    {
        [$this->em, $this->conn, $this->playerId] = $this->bootstrapOrSkip();
    }

    protected function tearDown(): void
    {
        $this->em?->close();
        $this->em = null;
        $this->conn = null;

        // Restore the bootstrap-default $link (aoo4 connection).
        // Other tests rely on require_once bootstrap.php having set
        // $GLOBALS['link']; leaving our aoo4_test connection behind
        // would cause them to hit the wrong DB.
        if ($this->previousLink !== null) {
            $GLOBALS['link'] = $this->previousLink;
        }
        $this->previousLink = null;
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-2')]
    public function testHasOptionReturnsFalseWhenOptionAbsent(): void
    {
        $entity = $this->em->find(PlayerEntity::class, $this->playerId);
        $this->assertInstanceOf(PlayerEntity::class, $entity);

        $options = new PlayerOptionsService();

        $name = 'phase32Miss_' . bin2hex(random_bytes(4));
        $this->assertFalse($entity->hasOption($options, $name));
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-2')]
    public function testHasOptionReturnsTrueWhenOptionPresent(): void
    {
        // Seed a row in players_options inside a transaction so this
        // test is self-contained even against a shared test DB. Commit
        // + delete used here (not rollback) because the global EM /
        // Classes\Db connection doesn't share the conn's transaction.
        $name = 'phase32Hit_' . bin2hex(random_bytes(4));
        $this->conn->insert('players_options', [
            'player_id' => $this->playerId,
            'name'      => $name,
        ]);

        try {
            $entity = $this->em->find(PlayerEntity::class, $this->playerId);
            $this->assertInstanceOf(PlayerEntity::class, $entity);

            $options = new PlayerOptionsService();

            $this->assertTrue($entity->hasOption($options, $name));
        } finally {
            $this->conn->delete('players_options', [
                'player_id' => $this->playerId,
                'name'      => $name,
            ]);
        }
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-2')]
    public function testGetCoordsPlanReturnsPlanForValidCoordsId(): void
    {
        $entity = $this->em->find(PlayerEntity::class, $this->playerId);
        $this->assertInstanceOf(PlayerEntity::class, $entity);

        // Expected value: read plan directly from coords, compare
        // against the method's output. Any drift (typo'd table name,
        // wrong join) fails here.
        $expected = (string) $this->conn->fetchOne(
            'SELECT plan FROM coords WHERE id = ?',
            [$entity->getCoordsId()]
        );

        $this->assertSame($expected, $entity->getCoordsPlan($this->conn));
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-2')]
    public function testGetCoordsPlanReturnsNullForOrphanedCoordsId(): void
    {
        // Synthesise an entity whose coords_id is off the end of the
        // coords table. The method should return null rather than
        // throwing — orphaned tutorial rows are the realistic trigger.
        $entity = new RealPlayer();
        $entity->setCoordsId(2_000_000_000);

        $this->assertNull($entity->getCoordsPlan($this->conn));
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-4')]
    public function testGetCoordsReturnsValueObjectMatchingDbRow(): void
    {
        $entity = $this->em->find(PlayerEntity::class, $this->playerId);
        $this->assertInstanceOf(PlayerEntity::class, $entity);

        $expected = $this->conn->fetchAssociative(
            'SELECT x, y, z, plan FROM coords WHERE id = ?',
            [$entity->getCoordsId()]
        );
        $this->assertIsArray($expected);

        $coords = $entity->getCoords($this->conn);
        $this->assertIsObject($coords);
        $this->assertSame((int) $expected['x'], $coords->x);
        $this->assertSame((int) $expected['y'], $coords->y);
        $this->assertSame((int) $expected['z'], $coords->z);
        $this->assertSame((string) $expected['plan'], $coords->plan);
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-4')]
    public function testGetCoordsReturnsNullForOrphanedCoordsId(): void
    {
        $entity = new RealPlayer();
        $entity->setCoordsId(2_000_000_000);

        $this->assertNull($entity->getCoords($this->conn));
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-4')]
    public function testGetOptionsReturnsSortedListDelegatingToService(): void
    {
        // Seed two options with non-sorted names; getOptions must return
        // them alphabetically (contract inherited from the service).
        $a = 'phase34a_' . bin2hex(random_bytes(3));
        $b = 'phase34z_' . bin2hex(random_bytes(3));

        $this->conn->insert('players_options', [
            'player_id' => $this->playerId,
            'name'      => $b,
        ]);
        $this->conn->insert('players_options', [
            'player_id' => $this->playerId,
            'name'      => $a,
        ]);

        try {
            $entity = $this->em->find(PlayerEntity::class, $this->playerId);
            $this->assertInstanceOf(PlayerEntity::class, $entity);

            $options = new PlayerOptionsService();
            $result = $entity->getOptions($options);

            $aPos = array_search($a, $result, true);
            $bPos = array_search($b, $result, true);
            $this->assertNotFalse($aPos, "seeded option {$a} must be present");
            $this->assertNotFalse($bPos, "seeded option {$b} must be present");
            $this->assertLessThan(
                $bPos,
                $aPos,
                'getOptions must preserve the service-level ascending sort'
            );
        } finally {
            $this->conn->delete('players_options', [
                'player_id' => $this->playerId,
                'name'      => $a,
            ]);
            $this->conn->delete('players_options', [
                'player_id' => $this->playerId,
                'name'      => $b,
            ]);
        }
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-2')]
    public function testIsInactiveDelegatesToPlayerService(): void
    {
        // RealPlayer::isInactive is a one-liner over
        // PlayerService::isInactive(lastLoginTime). To keep the test
        // independent of wall-clock threshold drift, we drive it with
        // two synthetic entities: one "logged in just now" (false),
        // one "logged in in 1970" (true).
        // PlayerService's constructor takes a playerId it doesn't use
        // for isInactive. Pass the test player's id to satisfy the
        // signature.
        $svc = new PlayerService($this->playerId);

        $recent = new RealPlayer();
        $recent->setLastLoginTime(time());
        $this->assertFalse($recent->isInactive($svc));

        $ancient = new RealPlayer();
        $ancient->setLastLoginTime(0);
        $this->assertTrue($ancient->isInactive($svc));
    }

    #[Group('player-entity-domain')]
    #[Group('phase-3-2')]
    public function testIsInactiveRespectsHydratedLastLoginTime(): void
    {
        // Integration path: entity loaded from DB, last_login_time
        // comes from the real row. Matches how infos.php will use the
        // method post-SAR.
        $entity = $this->em->find(RealPlayer::class, $this->playerId);
        $this->assertInstanceOf(RealPlayer::class, $entity);

        // PlayerService's constructor takes a playerId it doesn't use
        // for isInactive. Pass the test player's id to satisfy the
        // signature.
        $svc = new PlayerService($this->playerId);
        $expected = $svc->isInactive($entity->getLastLoginTime());

        $this->assertSame($expected, $entity->isInactive($svc));
    }

    /**
     * Same bootstrap pattern as PlayerEntityHydrationTest — standalone
     * EntityManager targeting aoo4_test.
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
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        // Snapshot the bootstrap-default $link now so tearDown can put
        // it back after this test overrides it with aoo4_test.
        $this->previousLink = $GLOBALS['link'] ?? null;

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

        // PlayerOptionsService uses Classes\Db via $GLOBALS['link'] (the
        // db() helper). Point that at our test connection so the
        // service reads/writes the same DB.
        $GLOBALS['link'] = $conn;

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
