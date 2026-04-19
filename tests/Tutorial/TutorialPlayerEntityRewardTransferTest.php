<?php

namespace Tests\Tutorial;

use App\Entity\TutorialPlayerEntity;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Phase 4.1 — contract test for the reconciled
 * `TutorialPlayerEntity::transferRewardsToRealPlayer` signature.
 *
 * Pins the shape that Phase 4.2's swap at
 * `TutorialManager::completeTutorial:460` will depend on:
 * `(Connection $conn, int $xpEarned, int $piEarned): void`,
 * matching the service-class counterpart
 * `App\Tutorial\TutorialPlayer::transferRewardsToRealPlayer`.
 *
 * The original single-arg signature read `$this->xp` / `$this->pi`,
 * which are always 0 for tutorial players (progression lives in
 * `TutorialContext`, not in the players row). Swapping the entity
 * method in naively would have silently zeroed out every tutorial
 * reward. This test ensures the fix stays applied.
 *
 * Same DB-gated pattern as TutorialPlayerCleanupIntegrationTest
 * (!376): skips cleanly when aoo4_test is unreachable, wraps each
 * test in a transaction rolled back in tearDown.
 */
class TutorialPlayerEntityRewardTransferTest extends TutorialIntegrationTestCase
{
    private ?string $previousErrorLog = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The entity method emits a diagnostic via error_log on
        // success. Route to a file and wrap in ob_start so PHPUnit's
        // beStrictAboutOutputDuringTests doesn't flag the leak (same
        // pattern MovementStepDbBranchesTest uses).
        $this->previousErrorLog = ini_get('error_log') ?: '';
        ini_set('error_log', '/tmp/phpunit-phase-4-1.log');
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        ini_set('error_log', $this->previousErrorLog ?? '');
        parent::tearDown();
    }

    #[Group('tutorial-entity-reward-transfer')]
    #[Group('phase-4-1')]
    public function testTransfersXpAndPiToRealPlayerWithoutTouchingTutorialRow(): void
    {
        [$realPlayerId, $tutPlayerId] = $this->seedRealAndTutorialPlayers();

        $realXpBefore = (int) $this->conn->fetchOne(
            'SELECT xp FROM players WHERE id = ?',
            [$realPlayerId]
        );
        $realPiBefore = (int) $this->conn->fetchOne(
            'SELECT pi FROM players WHERE id = ?',
            [$realPlayerId]
        );
        $tutXpBefore = (int) $this->conn->fetchOne(
            'SELECT xp FROM players WHERE id = ?',
            [$tutPlayerId]
        );

        // Build the entity by hand — we're not testing Doctrine
        // hydration here (Phase 3.1 covers that), we're testing the
        // method's SQL contract.
        $entity = new TutorialPlayerEntity();
        $this->setProtectedProperty($entity, 'id', $tutPlayerId);
        $entity->setRealPlayerIdRef($realPlayerId);

        $entity->transferRewardsToRealPlayer($this->conn, 100, 100);

        $this->assertSame(
            $realXpBefore + 100,
            (int) $this->conn->fetchOne('SELECT xp FROM players WHERE id = ?', [$realPlayerId]),
            'real player xp should have increased by exactly $xpEarned'
        );
        $this->assertSame(
            $realPiBefore + 100,
            (int) $this->conn->fetchOne('SELECT pi FROM players WHERE id = ?', [$realPlayerId]),
            'real player pi should have increased by exactly $piEarned'
        );
        $this->assertSame(
            $tutXpBefore,
            (int) $this->conn->fetchOne('SELECT xp FROM players WHERE id = ?', [$tutPlayerId]),
            'tutorial player row must be untouched — rewards are transferred, not moved'
        );
    }

    #[Group('tutorial-entity-reward-transfer')]
    #[Group('phase-4-1')]
    public function testThrowsWhenRealPlayerIdRefIsNull(): void
    {
        $entity = new TutorialPlayerEntity();
        // realPlayerIdRef deliberately left null.

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('real_player_id_ref is null');

        $entity->transferRewardsToRealPlayer($this->conn, 100, 100);
    }

    #[Group('tutorial-entity-reward-transfer')]
    #[Group('phase-4-1')]
    public function testSignatureMatchesServiceClassContract(): void
    {
        // Reflection pin: a future accidental revert to the single-arg
        // signature (or int→string drift on the reward args) would
        // break the Phase 4.2 swap silently. Catch it here.
        $method = new ReflectionMethod(
            TutorialPlayerEntity::class,
            'transferRewardsToRealPlayer'
        );

        $params = $method->getParameters();
        $this->assertCount(3, $params, 'expected (Connection, int $xpEarned, int $piEarned)');

        $conn = $params[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $conn);
        $this->assertSame(\Doctrine\DBAL\Connection::class, $conn->getName());

        $xp = $params[1]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $xp);
        $this->assertSame('int', $xp->getName());

        $pi = $params[2]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $pi);
        $this->assertSame('int', $pi->getName());

        $return = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $return);
        $this->assertSame('void', $return->getName());
    }

    /**
     * Seed a real player + tutorial player pair inside the
     * transaction. Returns [realPlayerId, tutorialPlayersRowId].
     *
     * Mirrors the seeding pattern from
     * `TutorialPlayerCleanupIntegrationTest` (!376). Deliberately
     * minimal — only fields needed by the method under test.
     *
     * @return array{0: int, 1: int}
     */
    private function seedRealAndTutorialPlayers(): array
    {
        $coordsId = (int) $this->conn->fetchOne('SELECT id FROM coords ORDER BY id ASC LIMIT 1');

        $this->conn->insert('players', [
            'name'        => 'Phase41Real_' . bin2hex(random_bytes(4)),
            'race'        => 'nain',
            'player_type' => 'real',
            'coords_id'   => $coordsId,
            'xp'          => 0,
            'pi'          => 0,
        ]);
        $realPlayerId = (int) $this->conn->lastInsertId();

        $this->conn->insert('players', [
            'name'        => 'Phase41Tut_' . bin2hex(random_bytes(4)),
            'race'        => 'nain',
            'player_type' => 'tutorial',
            'coords_id'   => $coordsId,
            'xp'          => 0,
            'pi'          => 0,
        ]);
        $tutPlayerId = (int) $this->conn->lastInsertId();

        return [$realPlayerId, $tutPlayerId];
    }

    /**
     * Sets a protected property on the entity without running Doctrine
     * hydration — hydration is covered by Phase 3.1's
     * PlayerEntityHydrationTest. Here we only care about the method's
     * behaviour given known inputs.
     */
    private function setProtectedProperty(object $instance, string $name, mixed $value): void
    {
        $ref = new \ReflectionProperty($instance, $name);
        $ref->setValue($instance, $value);
    }
}
