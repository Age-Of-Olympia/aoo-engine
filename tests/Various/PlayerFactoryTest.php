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
    /** Fixture ids, out of reach of real ones. */
    private const REAL_ID = 990201;
    private const NPC_ID = -990201;
    private const TUTORIAL_ID = 990202;

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        global $link;
        if (isset($link) && $link instanceof Connection) {
            $link->executeStatement(
                'DELETE FROM players WHERE id IN (?, ?, ?)',
                [self::REAL_ID, self::NPC_ID, self::TUTORIAL_ID]
            );
        }
    }

    /**
     * Seed one character of the given kind and return its name.
     *
     * The lookup cases used to borrow whichever row came first, which made them
     * skip on a database holding no character — and silently test a structure's
     * row on one where a decor had the lowest id.
     */
    private function seedCharacter(Connection $link, int $id, string $type, string $name): string
    {
        $link->executeStatement('DELETE FROM players WHERE id = ?', [$id]);
        $link->executeStatement(
            'INSERT INTO players (id, player_type, name, race) VALUES (?, ?, ?, ?)',
            [$id, $type, $name, 'nain']
        );

        return $name;
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

        $name = $this->seedCharacter($link, self::REAL_ID, 'real', 'GmFabriqueLegacy');

        $player = PlayerFactory::legacyByName($name);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertSame(self::REAL_ID, $player->id);
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

        $name = $this->seedCharacter($link, self::REAL_ID, 'real', 'GmFabriqueEntite');

        $entity = PlayerFactory::entityByName($name);

        $this->assertInstanceOf(\App\Entity\RealPlayer::class, $entity);
        $this->assertSame(self::REAL_ID, $entity->getId());
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
        // parent Character would, not throw.
        $this->assertNull(PlayerFactory::realPlayerById(999999999));
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdReturnsRealPlayerForRealPlayerId(): void
    {
        $link = $this->bootstrapOrSkip();

        $this->seedCharacter($link, self::REAL_ID, 'real', 'GmFabriqueReel');

        $entity = PlayerFactory::realPlayerById(self::REAL_ID);

        $this->assertInstanceOf(\App\Entity\RealPlayer::class, $entity);
        $this->assertSame(self::REAL_ID, $entity->getId());
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdRejectsNpcId(): void
    {
        // STI narrowing: passing an NPC id (player_type='npc',
        // negative id) must return null rather than hydrating the
        // NonPlayerCharacter subclass. This is the guard that keeps
        // ResetPasswordView from password-resetting an NPC "account".
        $link = $this->bootstrapOrSkip();

        $this->seedCharacter($link, self::NPC_ID, 'npc', 'GmFabriquePnj');

        $this->assertNull(
            PlayerFactory::realPlayerById(self::NPC_ID),
            'realPlayerById must not return NonPlayerCharacter rows'
        );
    }

    #[Group('player-factory')]
    public function testRealPlayerByIdRejectsTutorialPlayerId(): void
    {
        $link = $this->bootstrapOrSkip();

        $this->seedCharacter($link, self::TUTORIAL_ID, 'tutorial', 'GmFabriqueTuto');

        $this->assertNull(
            PlayerFactory::realPlayerById(self::TUTORIAL_ID),
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
