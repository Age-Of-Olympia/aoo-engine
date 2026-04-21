<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use Classes\Player;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Smoke tests for PlayerFactory.
 *
 * Tests that don't hit the database. The factory's `legacy()`, `active()`,
 * `entity()` paths construct `Classes\Player` or call Doctrine (both DB-bound)
 * and are exercised by integration / e2e tests, not here.
 */
class PlayerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Group('player-factory')]
    public function testActiveIdReturnsZeroWhenNoSession(): void
    {
        $this->assertSame(0, PlayerFactory::activeId());
    }

    #[Group('player-factory')]
    public function testActiveIdReturnsSessionPlayerIdWhenNotInTutorial(): void
    {
        $_SESSION['playerId'] = 42;

        $this->assertSame(42, PlayerFactory::activeId());
    }

    #[Group('player-factory')]
    public function testActiveIdIgnoresTutorialFlagWithoutTutorialPlayerId(): void
    {
        // `in_tutorial` alone is not enough — both flags must be set for the
        // tutorial branch to engage. Otherwise we fall back to main playerId.
        $_SESSION['playerId'] = 7;
        $_SESSION['in_tutorial'] = true;

        $this->assertSame(7, PlayerFactory::activeId());
    }

    #[Group('player-factory')]
    public function testFactoryExposesExpectedStaticApi(): void
    {
        $class = new ReflectionClass(PlayerFactory::class);

        $this->assertTrue($class->isFinal(), 'PlayerFactory should be final');

        foreach (['legacy', 'legacyByName', 'active', 'activeId', 'entity', 'entityByName', 'realPlayerById', 'activeEntity'] as $method) {
            $this->assertTrue($class->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($class->getMethod($method)->isStatic(), "{$method} should be static");
            $this->assertTrue($class->getMethod($method)->isPublic(), "{$method} should be public");
        }
    }

    #[Group('player-factory')]
    public function testLegacyByNameReturnsNullWhenNameNotFound(): void
    {
        $this->bootstrapOrSkip();

        // Opaque name that cannot collide with any seeded player — the
        // factory must normalise the legacy `false` miss to `null`.
        $miss = 'phaseLBNMiss_' . bin2hex(random_bytes(6));

        $this->assertNull(PlayerFactory::legacyByName($miss));
    }

    #[Group('player-factory')]
    public function testLegacyByNameReturnsPlayerWithMatchingIdWhenFound(): void
    {
        $link = $this->bootstrapOrSkip();

        $row = $link->fetchAssociative(
            "SELECT id, name FROM players WHERE id > 0 AND (player_type IS NULL OR player_type = 'real') ORDER BY id ASC LIMIT 1"
        );
        if (empty($row['name'])) {
            $this->markTestSkipped('No real player available for lookup test.');
        }

        $player = PlayerFactory::legacyByName((string) $row['name']);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertSame((int) $row['id'], $player->id);
    }

    #[Group('player-factory')]
    public function testEntityByNameReturnsNullWhenNameNotFound(): void
    {
        $this->bootstrapOrSkip();

        $miss = 'phaseEBNMiss_' . bin2hex(random_bytes(6));

        $this->assertNull(PlayerFactory::entityByName($miss));
    }

    #[Group('player-factory')]
    public function testEntityByNameReturnsRealPlayerWithMatchingIdWhenFound(): void
    {
        $link = $this->bootstrapOrSkip();

        $row = $link->fetchAssociative(
            "SELECT id, name FROM players WHERE id > 0 AND player_type = 'real' ORDER BY id ASC LIMIT 1"
        );
        if (empty($row['name'])) {
            $this->markTestSkipped('No real player available for lookup test.');
        }

        $entity = PlayerFactory::entityByName((string) $row['name']);

        $this->assertInstanceOf(\App\Entity\RealPlayer::class, $entity);
        $this->assertSame((int) $row['id'], $entity->getId());
    }

    #[Group('player-factory')]
    public function testEntityByNameSignatureReturnsNullableRealPlayer(): void
    {
        $method = new \ReflectionMethod(PlayerFactory::class, 'entityByName');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'entityByName must take exactly one argument');
        $this->assertSame('string', (string) $params[0]->getType());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('?App\\Entity\\RealPlayer', (string) $returnType);
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdReturnsNullWhenIdDoesNotExist(): void
    {
        $this->bootstrapOrSkip();

        // An id high enough to never collide with seeded rows. The
        // STI-narrow lookup must produce null, just like find() on the
        // parent PlayerEntity would, not throw.
        $this->assertNull(PlayerFactory::realPlayerById(999999999));
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdReturnsRealPlayerForRealPlayerId(): void
    {
        $link = $this->bootstrapOrSkip();

        $row = $link->fetchAssociative(
            "SELECT id FROM players WHERE id > 0 AND player_type = 'real' ORDER BY id ASC LIMIT 1"
        );
        if (empty($row['id'])) {
            $this->markTestSkipped('No real player available for lookup test.');
        }

        $entity = PlayerFactory::realPlayerById((int) $row['id']);

        $this->assertInstanceOf(\App\Entity\RealPlayer::class, $entity);
        $this->assertSame((int) $row['id'], $entity->getId());
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdRejectsNpcId(): void
    {
        // STI narrowing: passing an NPC id (player_type='npc',
        // negative id) must return null rather than hydrating the
        // NonPlayerCharacter subclass. This is the guard that keeps
        // ResetPasswordView from password-resetting an NPC "account".
        $link = $this->bootstrapOrSkip();

        $row = $link->fetchAssociative(
            "SELECT id FROM players WHERE player_type = 'npc' ORDER BY id ASC LIMIT 1"
        );
        if (empty($row['id'])) {
            $this->markTestSkipped('No NPC row available for STI-narrowing test.');
        }

        $this->assertNull(
            PlayerFactory::realPlayerById((int) $row['id']),
            'realPlayerById must not return NonPlayerCharacter rows'
        );
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdRejectsTutorialPlayerId(): void
    {
        $link = $this->bootstrapOrSkip();

        $row = $link->fetchAssociative(
            "SELECT id FROM players WHERE player_type = 'tutorial' AND id > 0 ORDER BY id ASC LIMIT 1"
        );
        if (empty($row['id'])) {
            $this->markTestSkipped('No tutorial player row available for STI-narrowing test.');
        }

        $this->assertNull(
            PlayerFactory::realPlayerById((int) $row['id']),
            'realPlayerById must not return TutorialPlayer rows'
        );
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdSignatureReturnsNullableRealPlayer(): void
    {
        // Mirror of testEntityByNameSignatureReturnsNullableRealPlayer
        // for the id-lookup flavour. Pins the ?RealPlayer contract so
        // callers (ResetPasswordView, admin tooling) can rely on
        // static analysis to catch miss-handling.
        $method = new \ReflectionMethod(PlayerFactory::class, 'realPlayerById');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'realPlayerById must take exactly one argument');
        $this->assertSame('int', (string) $params[0]->getType());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('?App\\Entity\\RealPlayer', (string) $returnType);
    }

    #[Group('player-factory')]
    public function testLegacyByNameSignatureReturnsNullablePlayer(): void
    {
        // Pin the nullable-Player contract that justifies this method's
        // existence: the factory normalises Player::get_player_by_name's
        // legacy Player|false return to ?Player, so callers can use ?->
        // and static analysis catches miss-handling bugs.
        $method = new \ReflectionMethod(PlayerFactory::class, 'legacyByName');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'legacyByName must take exactly one argument');
        $this->assertSame('string', (string) $params[0]->getType());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('?Classes\\Player', (string) $returnType);
    }

    /**
     * Bootstrap the legacy environment so Player::get_player_by_name
     * (which legacyByName wraps) can hit the DB. Skips cleanly when the
     * DB is unreachable — phpunit stage stays green.
     */
    private function bootstrapOrSkip(): Connection
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }

        return $link;
    }
}
